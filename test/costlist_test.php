<?php
// State filtering is applied to reconciled rows before counts and pagination.
require __DIR__.'/costcategories_test.php';

$samples = array(
	1 => array('ref'=>'A1', 'line'=>source('ordered', 2, 10, '2026-03-01')),
	2 => array('ref'=>'A2', 'line'=>source('shipment', 2, 0, '2026-03-01', null)),
	3 => array('ref'=>'A3', 'line'=>source('ordered', 2, 0, '2026-03-01')),
	4 => array('ref'=>'A4', 'line'=>source('customer', 2, 20, '2026-03-01')),
	5 => array('ref'=>'A10', 'line'=>source('shipment', 2, 0, '2026-03-01', null)),
);
$samples[1]['line']['issues'][] = 'BudgetCostIncompatibleUnits';
$products = array();
foreach ($samples as $id => $sample) {
	$line = $sample['line']; $line['product'] = $id; $line['product_ref'] = $sample['ref'];
	$products += report(array($line))['products'];
}
$before = serialize($products);
$rows = lmdbadvancedproject_prepare_product_cost_rows($products);
check(array_column($rows, 'ref'), array('A2','A10','A1','A3','A4'), 'Default state order with natural references inside a state');
check(array_column($rows, 'valuation_state'), array('incomplete','incomplete','unit_mismatch','complete','not_applicable'), 'Four distinct states including known zero');
check(serialize($products), $before, 'Presentation filtering does not mutate the shared report or valuation eligibility');
$selected = lmdbadvancedproject_prepare_product_cost_rows($products, array('unit_mismatch','incomplete'));
check(count($selected), 3, 'Multiselect combines states before counting');
check(array_column(array_slice($selected, 0, 2), 'ref'), array('A2','A10'), 'First page contains incomplete valuations');
check(array_column(array_slice($selected, 2, 2), 'ref'), array('A1'), 'Second page contains the unit anomaly');
check(array_column(lmdbadvancedproject_prepare_product_cost_rows($products, array('complete')), 'ref'), array('A3'), 'Complete filter retains a known zero');
check(array_column(lmdbadvancedproject_prepare_product_cost_rows($products, array('not_applicable')), 'ref'), array('A4'), 'No-cost filter retains a customer-only product');
check(lmdbadvancedproject_prepare_product_cost_rows($products, array('unknown')), array(), 'Unknown filter does not select a row');
check(lmdbadvancedproject_prepare_product_cost_rows(array()), array(), 'Empty list remains empty');
check(array_column(lmdbadvancedproject_prepare_product_cost_rows($products, array(), 'issues', 'DESC'), 'valuation_state'), array('not_applicable','complete','unit_mismatch','incomplete','incomplete'), 'State column supports reverse order');
check(array_column(lmdbadvancedproject_prepare_product_cost_rows($products, array(), 'ref'), 'ref'), array('A1','A2','A3','A4','A10'), 'Manual reference sort overrides default state sort');
check(array_column(lmdbadvancedproject_prepare_product_cost_rows($products, array(), 'total', 'DESC'), 'ref')[0], 'A1', 'Manual amount sort remains available');
$both = source('shipment', 2, 0, '2026-03-01', null); $both['issues'][] = 'BudgetCostIncompatibleUnits';
$bothRows = lmdbadvancedproject_prepare_product_cost_rows(report(array($both))['products']);
check($bothRows[0]['valuation_state'], 'unit_mismatch', 'Unit anomaly remains identifiable even with a missing price');
$_GET['search_states'] = array('unit_mismatch','not_applicable');
check(GETPOST('search_states', 'array:aZ09'), array('unit_mismatch','not_applicable'), 'Native request filter preserves state identifiers');
unset($_GET['search_states']);

// Render the real Dolibarr selector with its normal Select2 configuration.
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
$previousConf = clone $conf;
$conf->global = clone $conf->global;
$conf->use_javascript_ajax = 1;
$conf->global->MAIN_USE_JQUERY_MULTISELECT = 'select2';
$options = array();
foreach (lmdbadvancedproject_product_cost_states() as $code => $state) { $options[$code] = $langs->trans($state['label']); }
$html = Form::multiselectarray('search_states', $options, array('incomplete', 'unit_mismatch'), 0, 0, 'minwidth150');
$dom = new DOMDocument();
$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($dom);
check($xpath->query('//select[@name="search_states[]" and @multiple]/option')->length, 4, 'Native multiselect submits four state choices as an array');
$selectedOptions = array();
foreach ($xpath->query('//select/option[@selected]') as $option) { $selectedOptions[] = $option->getAttribute('value'); }
check($selectedOptions, array('incomplete', 'unit_mismatch'), 'Native selector restores both selected states');
check($xpath->query('//input[@name="search_states_multiselect"]')->length, 1, 'Native empty-selection marker is present');
check(strpos($html, "$('#search_states').select2(") !== false, true, 'Native helper initializes Select2');
$conf->use_javascript_ajax = 0;
$plain = Form::multiselectarray('search_states', $options, array(), 0, 0, 'minwidth150');
$dom->loadHTML($plain, LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($dom);
check($xpath->query('//select[@multiple]/option[@selected]')->length, 0, 'Cleared selection remains empty without JavaScript');
$conf = $previousConf;

echo $checks." assertions passed including state filtering and native list sorting.\n";
