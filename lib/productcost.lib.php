<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
	 * @param array<string,mixed> $forecast
	 * @param array<string,mixed> $report
	 * @return void */
function lmdbadvancedproject_add_product_cost_forecast(array &$forecast, array $report): void
{
	foreach ($report['events'] as $event) {
		$source = $event['line'];
		$key = $source['category'];
		if (!isset($forecast['categories'][$key])) {
			$forecast['categories'][$key] = array('label' => $source['category_label'], 'translation_key' => $key === 'uncategorized' ? 'BudgetReportUncategorized' : '',
				'order_amount' => 0.0, 'order_budget' => 0.0, 'supplier_expenses' => 0.0, 'order_lines' => array(), 'supplier_lines' => array());
		}
		$amount = $event['amount'] ?? 0.0;
		$forecast['categories'][$key]['supplier_expenses'] += $amount;
		$forecast['totals']['supplier_expenses'] += $amount;
		$type = $event['kind'] === 'invoice' ? 'supplier_invoice' : ($event['kind'] === 'shipment' ? 'shipment' : 'supplier_order_'.$event['kind']);
		$forecast['categories'][$key]['supplier_lines'][] = array('type' => $type, 'document_id' => $source['document_id'],
			'ref' => $source['ref'], 'date' => $event['date'], 'label' => $source['label'], 'label_full' => $source['label'],
			'document_status' => null, 'document_paid' => 0, 'document_billed' => 0, 'qty' => $event['qty'], 'amount' => $amount, 'budget' => 0,
			'cost_source' => $source, 'cost_reason' => $event['reason'], 'cost_missing' => $event['amount'] === null);
	}
}

/**
	 * @param array<string,mixed> $report
	 * @return void */
function lmdbadvancedproject_print_cost_notice(array $report): void
{
	global $langs;
	if (getDolGlobalInt('LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST') && !LmdbAdvancedProjectCompatibility::shipmentCostAvailable()) {
		print '<div class="warning">'.$langs->trans('BudgetCostUnavailable').'</div>';
	}
	if (LmdbAdvancedProjectCompatibility::isShipmentCostEnabled()) {
		print '<div class="info">'.$langs->trans('BudgetCostChronologyHelp').' '.$langs->trans('BudgetCostQuantityHelp').'</div>';
	}
	if (!$report['complete']) {
		print '<div class="warning"><strong>'.$langs->trans('BudgetCostIncomplete').'</strong><br>';
		foreach ($report['issues'] as $issue) {
			print $langs->trans($issue).'<br>';
		}
		print '</div>';
	}
}

/** Shared column definitions for the native product list and spreadsheet exports.
 *
	 * @return array<string,string>
 */
function lmdbadvancedproject_product_cost_columns(): array
{
	return array('ref' => 'Ref', 'label' => 'Label', 'type' => 'Type', 'unit' => 'Unit',
		'customer_qty' => 'BudgetCostCustomerQty', 'ordered_qty' => 'BudgetCostOrderedQty', 'shipped_qty' => 'BudgetCostShippedQty',
		'invoiced_qty' => 'BudgetCostInvoicedQty', 'uncovered_qty' => 'BudgetCostUncoveredQty', 'remaining_qty' => 'BudgetCostRemainingQty',
		'provisional_cost' => 'BudgetCostProvisionalBalance', 'invoice_cost' => 'BudgetCostInvoiceCost', 'shipment_cost' => 'BudgetCostShipmentsNet', 'order_cost' => 'BudgetCostCommitmentsNet', 'total' => 'BudgetCostRetained', 'issues' => 'Status');
}

/** Native document links, without exposing a source document to unauthorized users.
 *
	 * @param array<string,mixed> $line
	 * @return string
 */
function lmdbadvancedproject_cost_document_link(array $line): string
{
	global $db, $user;
	$label = dol_escape_htmltag($line['ref']);
	$object = null;
	if ($line['kind'] === 'customer' && $user->hasRight('commande', 'lire')) {
		require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		$object = new Commande($db);
	} elseif (in_array($line['kind'], array('invoice', 'purchase_coverage'), true) && $user->hasRight('fournisseur', 'facture', 'lire')) {
		require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
		$object = new FactureFournisseur($db);
	} elseif (in_array($line['kind'], array('ordered', 'delivered', 'supplier_pending'), true) && $user->hasRight('fournisseur', 'commande', 'lire')) {
		require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.commande.class.php';
		$object = new CommandeFournisseur($db);
	} elseif (in_array($line['kind'], array('shipment', 'anomaly'), true) && $user->hasRight('expedition', 'lire')) {
		require_once DOL_DOCUMENT_ROOT.'/expedition/class/expedition.class.php';
		$object = new Expedition($db);
	}
	if ($object !== null) {
		$object->id = $line['document_id'];
		$object->ref = $line['ref'];
		$object->entity = $line['entity'];
		return $object->getNomUrl(1);
	}
	return $label;
}

/**
	 * @param array<string,mixed> $row
	 * @return string HTML for a native tooltip. */
function lmdbadvancedproject_product_cost_tooltip(array $row): string
{
	global $langs, $db;
	$parts = array($langs->trans('BudgetCostFormulaHelp'), $langs->trans('BudgetCostQuantityHelp'));
	foreach ($row['lines'] as $line) {
		$parts[] = lmdbadvancedproject_cost_document_link($line).' — '.dol_print_date($db->jdate($line['date']), 'day').' — '.$langs->trans('Qty').': '.price($line['qty']);
		if ($line['kind'] === 'supplier_pending') { $parts[] = $langs->trans('BudgetCostSupplierPending'); }
		if ($line['kind'] === 'shipment') {
			if ($line['date_fallback']) {
				$parts[] = $langs->trans('BudgetCostValidationDateFallback');
			}
			$parts[] = $langs->trans('Currency').': '.dol_escape_htmltag($line['currency'] ?? '');
			if (!empty($line['price_status']) && $line['price_status'] !== 'known') { $parts[] = $langs->trans($line['price_status']); }
			$parts[] = $line['price'] === null ? $langs->trans('BudgetCostMissingPrice')
				: $langs->trans($line['price_source']).': '.price($line['price']).' ('.dol_print_date($db->jdate($line['price_date']), 'dayhour').')';
		}
	}
	foreach ($row['events'] as $event) {
		$parts[] = lmdbadvancedproject_cost_document_link($event['cause']).' — '.dol_print_date($db->jdate($event['date']), 'day').' — '.$langs->trans('BudgetCostReason_'.$event['reason']).': '
			.($event['amount'] === null ? $langs->trans('BudgetCostMissingPrice') : price($event['amount']));
	}
	foreach ($row['issues'] as $issue) {
		$parts[] = $langs->trans($issue);
	}
	return implode('<br>', $parts);
}
