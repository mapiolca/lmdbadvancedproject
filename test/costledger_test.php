<?php
/* CLI regression tests. Native rounding functions are used; no live database. */
if (PHP_SAPI !== 'cli') { exit(1); }
$root = getenv('DOLIBARR_ROOT') ?: dirname(__DIR__, 2).'/dolibarr/htdocs';
define('DOL_DOCUMENT_ROOT', realpath($root));
require DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require __DIR__.'/../class/lmdbadvancedprojectproductcost.class.php';
$conf = (object) array('global' => (object) array('MAIN_MAX_DECIMALS_UNIT' => 5, 'MAIN_MAX_DECIMALS_TOT' => 2));
$langs = new class { public function transnoentitiesnoconv($s) { return $s; } };
$checks = 0;
function check($actual, $expected, string $message): void {
	global $checks;
	$checks++;
	$ok = is_numeric($actual) && is_numeric($expected) ? abs($actual - $expected) < 1e-7 : $actual === $expected;
	if (!$ok) { throw new RuntimeException($message.': '.var_export($actual, true).' expected '.var_export($expected, true)); }
}
function source(string $kind, float $qty, float $amount, string $date, ?float $price = null, int $id = 1, int $project = 1, int $unit = 1): array {
	return array('key' => $kind.':'.sprintf('%020d', $id), 'kind' => $kind, 'project' => $project, 'product' => 1,
		'unit' => $unit, 'qty' => $qty, 'amount' => $amount, 'date' => $date, 'document_id' => $id, 'ref' => $kind.$id,
		'entity' => 1, 'product_ref' => 'TEST', 'label' => 'Test', 'product_type' => 0, 'unit_label' => 'unit '.$unit,
		'category' => 'uncategorized', 'category_label' => 'Other', 'price' => $price, 'price_source' => 'test', 'price_date' => $date, 'date_fallback' => false, 'issues' => array());
}
function report(array $sources, string $start = '', string $end = ''): array { return LmdbAdvancedProjectCostLedger::build($sources, $start, $end); }
function total(array $report): float { return array_sum(array_column($report['products'], 'total')); }
$shipping = source('shipment', 10, 0, '2026-03-12', 10);
$invoice = source('invoice', 10, 120, '2026-04-12');
check(total(report(array($shipping))), 100, 'Uninvoiced shipment');
check(total(report(array($shipping, $invoice))), 120, 'Later invoice replaces provisional cost');
check(total(report(array($shipping, $invoice), '2026-03-01', '2026-03-31')), 100, 'March');
check(total(report(array($shipping, $invoice), '2026-04-01', '2026-04-30')), 20, 'April regularization');
$invoice['amount'] = 80;
check(total(report(array($shipping, $invoice), '2026-04-01', '2026-04-30')), -20, 'Negative regularization');
$invoice['date'] = '2026-02-12';
check(total(report(array($shipping, $invoice))), 80, 'Invoice before shipment');
check(total(report(array($shipping, $invoice), '2026-03-01', '2026-03-31')), 0, 'Earlier invoiced quantities available');
$ordered = source('ordered', 12, 108, '2026-02-10');
$partial = source('invoice', 4, 48, '2026-04-12');
$example = report(array($ordered, $shipping, $partial));
check(total($example), 126, '12 ordered / 10 shipped / 4 invoiced: 48 + 60 + 18');
check($example['products']['1:1']['remaining_qty'], 2, 'Two units still committed');
check($example['products']['1:1']['uncovered_qty'], 6, 'Six units provisional');
check($example['products']['1:1']['order_cost'], 18, 'Unrepresented commitment only');
$first = source('shipment', 3, 0, '2026-03-01', 7, 1);
$second = source('shipment', 7, 0, '2026-03-15', 13, 2);
check(total(report(array($second, $partial, $first))), 126, 'FIFO prices: invoice48 plus6 at13');
$excess = source('invoice', 15, 180, '2026-04-12');
check(total(report(array($shipping, $excess))), 180, 'Excess invoice quantity not duplicated');
check(total(report(array($shipping, $excess, source('shipment', 6, 0, '2026-05-01', 11, 2)))), 191, 'Unused invoice covers later shipment');
$missing = $shipping; $missing['price'] = null;
check(report(array($missing))['complete'], false, 'Missing historical cost');
check(report(array($missing, $excess))['complete'], true, 'Fully invoiced missing estimate cancels over full history');
check(report(array($missing, $excess), '2026-04-01', '2026-04-30')['complete'], false, 'Unknown earlier reversal in selected period');
$zero = $shipping; $zero['price'] = 0.0;
check(report(array($zero))['complete'], true, 'Explicit zero is a valid price');
$otherProject = $excess; $otherProject['project'] = 2;
check(total(report(array($shipping, $otherProject))), 280, 'Separate project coverage');
$otherUnit = $excess; $otherUnit['unit'] = 2;
check(total(report(array($shipping, $otherUnit))), 280, 'Separate unit coverage');
check(report(array($shipping, $otherUnit))['complete'], false, 'Unit anomaly');
$credit = source('invoice', 4, -48, '2026-04-15'); $credit['issues'][] = 'BudgetCostCreditQuantityUnknown';
check(total(report(array($shipping, $credit))), 52, 'Credit is financial, not physical coverage');
check(report(array($shipping, $credit))['products']['1:1']['uncovered_qty'], 10, 'Credit never implies returns');
foreach (array(0, 2, 3, 5) as $precision) {
	$conf->global->MAIN_MAX_DECIMALS_TOT = $precision;
	$lines = array(source('shipment', 3, 0, '2026-03-01', 0.33333));
	for ($id = 1; $id <= 3; $id++) { $lines[] = source('invoice', 1, 1, '2026-04-0'.$id, null, $id); }
	check(total(report($lines)), 3, 'No rounding residue after partial releases at precision '.$precision);
	$lines[] = source('ordered', 3, 1, '2026-02-01');
	check(total(report($lines)), 3, 'No commitment rounding residue at precision '.$precision);
}
$conf->global->MAIN_MAX_DECIMALS_TOT = 2;
// Property: adjacent periods sum to the complete report for arbitrary orderings.
mt_srand(450021);
for ($case = 0; $case < 200; $case++) {
	$lines = array();
	foreach (range(1, 15) as $id) {
		$kind = array('ordered', 'invoice', 'shipment')[mt_rand(0, 2)];
		$qty = (float) mt_rand(1, 12); $unit = (float) mt_rand(0, 15);
		$lines[] = source($kind, $qty, $qty * $unit, '2026-0'.mt_rand(1, 3).'-'.sprintf('%02d', mt_rand(1, 28)), $unit, $id);
	}
	$all = report($lines);
	$sum = 0;
	foreach (range(1, 3) as $month) { $sum += total(report($lines, '2026-0'.$month.'-01', '2026-0'.$month.'-31')); }
	check($sum, total($all), 'Period additivity '.$case);
	$reverse = array_reverse($lines);
	check(total(report($reverse)), total($all), 'Stable chronological replay '.$case);
	check($all['products']['1:1']['remaining_qty'], max(0, $all['products']['1:1']['ordered_qty'] - max($all['products']['1:1']['invoiced_qty'], $all['products']['1:1']['shipped_qty'])), 'Conservation of commitments '.$case);
}
$candidate = array('id' => 1, 'date' => '2026-01-01', 'observed' => '2026-01-01', 'price' => 10, 'priority' => 2, 'history' => 1, 'currency' => 'EUR', 'status' => 'known', 'deleted' => 0);
$new = $candidate; $new['id'] = 2; $new['price'] = 15; $new['date'] = $new['observed'] = '2026-02-01';
check(LmdbAdvancedProjectProductCost::selectTariff(array($candidate, $new), '2026-03-01', '2026-03-01')['price'], 15, 'Latest supplier, not cheapest');
$new['observed'] = '2026-04-01';
check(LmdbAdvancedProjectProductCost::selectTariff(array($candidate, $new), '2026-05-01', '2026-03-01')['price'], 10, 'Future shipment excludes later recorded price');
$new['observed'] = '2026-02-01'; $new['price'] = null; $new['priority'] = 0;
check(LmdbAdvancedProjectProductCost::selectTariff(array($candidate, $new), '2026-03-01', '2026-03-01')['price'], null, 'Unknown recent discount blocks stale known tariff');
$new['deleted'] = 1;
check(LmdbAdvancedProjectProductCost::selectTariff(array($candidate, $new), '2026-03-01', '2026-03-01')['price'], 10, 'Deleted supplier tariff excluded');
echo $checks." assertions passed (native Dolibarr rounding, no live instance).\n";
