<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Rebuild analytical costs from currently valid documents, in business-date order.
 * No accounting or stock mutation is performed here.
 *
 * @phpstan-type CostLine array{key:string,kind:string,project:int,product:int,unit:int,qty:float,amount:?float,date:string,document_id:int,ref:string,entity:int,product_ref:string,label:string,product_type:int,unit_label:string,category:string,category_label:string,price:?float,price_source:string,price_date:string,date_fallback:bool,currency?:string,price_status?:string,issues:list<string>}
 * @phpstan-type CostEvent array{line:CostLine,cause:CostLine,kind:string,date:string,qty:float,amount:?float,reason:string,unknown_key:string}
 * @phpstan-type ProductCostRow array{project:int,product:int,entity:int,entities:array<int,int>,ref:string,label:string,type:int,unit:string,units:array<int,string>,customer_qty:float,ordered_qty:float,shipped_qty:float,invoiced_qty:float,uncovered_qty:float,remaining_qty:float,invoice_cost:float,shipment_cost:float,order_cost:float,provisional_cost:?float,total:float,issues:list<string>,lines:list<CostLine>,events:list<CostEvent>}
 * @phpstan-type CostReport array{products:array<string,ProductCostRow>,events:list<CostEvent>,issues:list<string>,complete:bool}
 */
class LmdbAdvancedProjectCostLedger
{
	/**
	 * Quantities are matched within the same project/product/unit only. Invoices
	 * consume provisional shipments FIFO; unused invoiced quantities cover later
	 * shipments. Supplier commitments cover only max(ordered-max(invoiced,shipped),0).
	 *
	 *
	 * @param list<CostLine> $lines Validated, authorized source lines
	 *
	 * @param string $start Inclusive observation date, empty for no lower bound
	 *
	 * @param string $end Inclusive observation date, empty for no upper bound
	 *
	 * @return CostReport
	 */
	public static function build(array $lines, string $start = '', string $end = ''): array
	{
		$priority = array('invoice' => 0, 'purchase_coverage' => 0, 'customer' => 1, 'anomaly' => 1, 'supplier_pending' => 2, 'ordered' => 2, 'delivered' => 2, 'shipment' => 3);
		usort($lines, static function (array $a, array $b) use ($priority): int {
			return array($a['date'], $priority[$a['kind']], $a['key']) <=> array($b['date'], $priority[$b['kind']], $b['key']);
		});
		$products = array();
		/** @var array<string,array{product_key:string,invoiced:float,external_invoiced:float,shipped:float,invoice_available:float,order_consumed:float,orders:list<array{line:CostLine,remaining:float}>,shipments:list<array{line:CostLine,remaining:float}>}> $groups */
		$groups = array();
		$events = array();
		foreach ($lines as $line) {
			if ($end !== '' && substr($line['date'], 0, 10) > $end) {
				continue;
			}
			$key = $line['project'].':'.$line['product'];
			if (!isset($products[$key])) {
				$products[$key] = array(
					'project' => $line['project'], 'product' => $line['product'], 'entity' => $line['entity'],
					'ref' => $line['product_ref'], 'label' => $line['label'], 'type' => $line['product_type'],
					'entities' => array(), 'unit' => $line['unit_label'], 'units' => array(), 'customer_qty' => 0.0, 'ordered_qty' => 0.0,
					'shipped_qty' => 0.0, 'invoiced_qty' => 0.0, 'uncovered_qty' => 0.0, 'remaining_qty' => 0.0,
					'provisional_cost' => 0.0, 'invoice_cost' => 0.0, 'shipment_cost' => 0.0, 'order_cost' => 0.0, 'total' => 0.0,
					'issues' => array(), 'lines' => array(), 'events' => array(),
				);
			}
			$products[$key]['units'][$line['unit']] = $line['unit_label'];
			$products[$key]['entities'][$line['entity']] = $line['entity'];
			$products[$key]['lines'][] = $line;
			$products[$key]['issues'] = array_values(array_unique(array_merge($products[$key]['issues'], $line['issues'])));
			if ($line['kind'] === 'anomaly') { continue; }
			if ($line['kind'] === 'supplier_pending') {
				$products[$key]['ordered_qty'] += $line['qty'];
				continue; // Native order not yet sent: visible quantity, no commitment.
			}
			$groupKey = $key.':'.$line['unit'];
			if (!isset($groups[$groupKey])) {
				$groups[$groupKey] = array('product_key' => $key, 'invoiced' => 0.0, 'external_invoiced' => 0.0, 'shipped' => 0.0, 'invoice_available' => 0.0, 'order_consumed' => 0.0, 'orders' => array(), 'shipments' => array());
			}
			$group = &$groups[$groupKey];
			$qty = max(0.0, $line['qty']);
			if ($line['kind'] === 'purchase_coverage') {
				$group['external_invoiced'] += $qty;
			} elseif ($line['kind'] === 'customer') {
				$products[$key]['customer_qty'] += $line['qty'];
			} elseif ($line['kind'] === 'invoice') {
				self::emit($events, $line, 'invoice', $line['date'], $line['qty'], $line['amount'], 'invoice');
				// Credit notes and ambiguous negative/zero quantities remain financial
				// contributions; they never invent a physical return or stock coverage.
				if (in_array('BudgetCostCreditQuantityUnknown', $line['issues'], true) || $line['qty'] <= 0) {
					$qty = 0.0;
				}
				$products[$key]['invoiced_qty'] += $qty;
				$group['invoiced'] += $qty;
				$shipment = null;
				foreach ($group['shipments'] as &$shipment) {
					$covered = min($qty, $shipment['remaining']);
					if ($covered > 0) {
						$amount = $shipment['line']['price'] === null ? null
							: (float) price2num(($shipment['remaining'] - $covered) * $shipment['line']['price'], 'MT') - (float) price2num($shipment['remaining'] * $shipment['line']['price'], 'MT');
						self::emit($events, $shipment['line'], 'shipment', $line['date'], -$covered, $amount, 'regularization', $line);
						$shipment['remaining'] -= $covered;
						$qty -= $covered;
					}
					if ($qty <= 0) {
						break;
					}
				}
				unset($shipment);
				$group['invoice_available'] += $qty;
			} elseif ($line['kind'] === 'shipment') {
				$products[$key]['shipped_qty'] += $qty;
				$group['shipped'] += $qty;
				$covered = min($qty, $group['invoice_available']);
				$group['invoice_available'] -= $covered;
				$remaining = $qty - $covered;
				if ($remaining > 0) {
					$group['shipments'][] = array('line' => $line, 'remaining' => $remaining);
					$amount = $line['price'] === null ? null : (float) price2num($remaining * $line['price'], 'MT');
					self::emit($events, $line, 'shipment', $line['date'], $remaining, $amount, 'shipment');
				}
			} else {
				$products[$key]['ordered_qty'] += $qty;
				if ($qty > 0) {
					$group['orders'][] = array('line' => $line, 'remaining' => $qty);
					self::emit($events, $line, $line['kind'], $line['date'], $qty, $line['amount'], 'commitment');
				}
			}

			$toConsume = max(0.0, max($group['invoiced'] + $group['external_invoiced'], $group['shipped']) - $group['order_consumed']);
			foreach ($group['orders'] as &$order) {
				$consumed = min($toConsume, $order['remaining']);
				if ($consumed > 0) {
					// Difference of rounded balances avoids cents left after partial releases.
					$before = $order['line']['amount'] === null ? null : (float) price2num($order['line']['amount'] * $order['remaining'] / $order['line']['qty'], 'MT');
					$order['remaining'] -= $consumed;
					$after = $order['line']['amount'] === null ? null : (float) price2num($order['line']['amount'] * $order['remaining'] / $order['line']['qty'], 'MT');
					self::emit($events, $order['line'], $order['line']['kind'], $line['date'], -$consumed, $after === null || $before === null ? null : $after - $before, 'commitment_release', $line);
					$group['order_consumed'] += $consumed;
					$toConsume -= $consumed;
				}
				if ($toConsume <= 0) {
					break;
				}
			}
			unset($order, $group);
		}
		foreach ($groups as $group) {
			foreach ($group['shipments'] as $shipment) {
				$products[$group['product_key']]['uncovered_qty'] += $shipment['remaining'];
				if ($shipment['remaining'] > 0) {
					if ($shipment['line']['price'] === null) {
						$products[$group['product_key']]['provisional_cost'] = null;
					} elseif ($products[$group['product_key']]['provisional_cost'] !== null) {
						$products[$group['product_key']]['provisional_cost'] += (float) price2num($shipment['remaining'] * $shipment['line']['price'], 'MT');
					}
				}
			}
			foreach ($group['orders'] as $order) {
				$products[$group['product_key']]['remaining_qty'] += $order['remaining'];
			}
		}
		$periodEvents = array();
		$unknown = array();
		foreach ($events as $event) {
			if ($start !== '' && substr($event['date'], 0, 10) < $start) {
				continue;
			}
			$key = $event['line']['project'].':'.$event['line']['product'];
			$periodEvents[] = $event;
			$products[$key]['events'][] = $event;
			if ($event['amount'] === null) {
				$unknown[$key][$event['unknown_key']] = ($unknown[$key][$event['unknown_key']] ?? 0.0) + $event['qty'];
			} else {
				$bucket = $event['kind'] === 'invoice' ? 'invoice_cost' : ($event['kind'] === 'shipment' ? 'shipment_cost' : 'order_cost');
				$products[$key][$bucket] += $event['amount'];
				$products[$key]['total'] += $event['amount'];
			}
		}
		$issues = array();
		foreach ($products as $key => &$product) {
			foreach ($unknown[$key] ?? array() as $quantity) {
				if (abs($quantity) > 1.0e-8) {
					$product['issues'][] = 'BudgetCostMissingPrice';
				}
			}
			if (count($product['units']) > 1) {
				$product['issues'][] = 'BudgetCostIncompatibleUnits';
			}
			$product['issues'] = array_values(array_unique($product['issues']));
			$issues = array_merge($issues, $product['issues']);
		}
		unset($product);
		$issues = array_values(array_unique($issues));
		return array('products' => $products, 'events' => $periodEvents, 'issues' => $issues, 'complete' => !$issues);
	}

	/**
	 * @param list<CostEvent> $events
	 * @param CostLine $line
	 * @param CostLine|null $cause
	 * @return void */
	private static function emit(array &$events, array $line, string $kind, string $date, float $qty, ?float $amount, string $reason, ?array $cause = null): void
	{
		$events[] = array('line' => $line, 'cause' => $cause ?? $line, 'kind' => $kind, 'date' => $date, 'qty' => $qty, 'amount' => $amount, 'reason' => $reason, 'unknown_key' => $line['key']);
	}
}
