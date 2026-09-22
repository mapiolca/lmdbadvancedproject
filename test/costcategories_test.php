<?php
/* Commercial categories must follow the shipped product throughout reconciliation. */
require __DIR__.'/costexports_test.php';

$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'c_commercial_category (rowid INTEGER PRIMARY KEY, entity INTEGER, code TEXT, label TEXT, active INTEGER)');
foreach (array('product', 'commandedet', 'commande_fournisseurdet', 'facture_fourn_det') as $element) {
	$db->query('CREATE TABLE '.MAIN_DB_PREFIX.$element.'_extrafields (fk_object INTEGER PRIMARY KEY, lmdb_commercial_category TEXT)');
	insertFixture($element.'_extrafields', array('fk_object'=>1, 'lmdb_commercial_category'=>$element === 'product' ? '1' : '2'));
}
insertFixture('c_commercial_category', array('rowid'=>1, 'entity'=>1, 'code'=>'PRODUCT', 'label'=>'Matériel électrique', 'active'=>1));
insertFixture('c_commercial_category', array('rowid'=>2, 'entity'=>1, 'code'=>'LINE', 'label'=>'Catégorie de ligne', 'active'=>1));
insertFixture('c_commercial_category', array('rowid'=>3, 'entity'=>2, 'code'=>'PRODUCT', 'label'=>'Autre entité', 'active'=>1));

$categorized = lmdbadvancedproject_load_budget_report_data(1, $filters);
foreach ($categorized['productCosts']['events'] as $event) {
	check($event['line']['category'], 'cat_1', 'Product category takes priority for '.$event['kind'].'/'.$event['reason']);
}
check($categorized['budgetReportForecast']['categories']['cat_1']['supplier_expenses'], 126, 'Commercial category includes shipments, invoices and commitments');
check($categorized['budgetReportForecast']['categories']['cat_1']['order_budget'], 150, 'Budget and shipment costs use the same category');
check(count($categorized['budgetReportForecast']['categories']), 1, 'No artificial line or uncategorized category');
check($categorized['totalspent'], 126, 'Category joins do not multiply costs');
$aprilCategories = lmdbadvancedproject_load_budget_report_data(1, $period);
check($aprilCategories['budgetReportForecast']['categories']['cat_1']['supplier_expenses'], 8, 'Invoice regularization stays in the product category');
check(array_sum($aprilCategories['mospents']), 8, 'Monthly series retains the category correction');

// No customer order line: the category still comes from the actual shipped product.
$db->query('UPDATE '.MAIN_DB_PREFIX."expedition SET fk_projet=1");
$db->query('UPDATE '.MAIN_DB_PREFIX."expeditiondet SET fk_elementdet=NULL, element_type=NULL");
$standalone = $service->load(array(1));
foreach ($standalone['events'] as $event) {
	if ($event['kind'] === 'shipment') { check($event['line']['category'], 'cat_1', 'Shipment without order inherits product category'); }
}
check(total($standalone), 126, 'Standalone shipment keeps identical totals');

// Historical text codes resolve within the product owner entity, even with sharing.
$db->query('UPDATE '.MAIN_DB_PREFIX."product_extrafields SET lmdb_commercial_category='PRODUCT'");
$mc->scope = '1,2';
$shared = $service->load(array(1));
check(total($shared), 126, 'Same category code in another entity cannot duplicate shipment costs');
$sharedReport = lmdbadvancedproject_load_budget_report_data(1, $filters);
check($sharedReport['budgetReportForecast']['categories']['cat_1']['order_budget'], 150, 'Shared category codes do not duplicate customer budget');
check(count($sharedReport['budgetReportForecast']['categories']), 1, 'Full shared report uses the same product owner category');
foreach ($shared['events'] as $event) {
	check($event['line']['category'], 'cat_1', 'Category belongs to the product owner entity');
}
$mc->scope = '1';

// Missing or inaccessible categories do not invent a product classification.
$db->query('UPDATE '.MAIN_DB_PREFIX."product_extrafields SET lmdb_commercial_category='3'");
foreach ($service->load(array(1))['events'] as $event) {
	if ($event['kind'] === 'shipment') { check($event['line']['category'], 'uncategorized', 'Foreign product category is not disclosed'); }
}
$db->query('UPDATE '.MAIN_DB_PREFIX.'product_extrafields SET lmdb_commercial_category=NULL');
foreach ($service->load(array(1))['events'] as $event) {
	if ($event['kind'] === 'shipment') { check($event['line']['category'], 'uncategorized', 'Uncategorized standalone shipment stays explicit'); }
}
$db->query('UPDATE '.MAIN_DB_PREFIX."expeditiondet SET fk_elementdet=1, element_type='commande'");
foreach ($service->load(array(1))['events'] as $event) {
	if ($event['kind'] === 'shipment') { check($event['line']['category'], 'cat_2', 'Existing order-line fallback remains available'); }
}
$db->query('UPDATE '.MAIN_DB_PREFIX."product_extrafields SET lmdb_commercial_category='1'");

// The serialized project summary uses the same category and reconciled amount.
$project = new Project($db); $project->id=1; $project->entity=1; $project->ref='P1'; $project->title='Test';
$conf->format_date_short='%Y-%m-%d';
$categoryExport = new LmdbAdvancedProjectBudgetReportExport($langs, $categorized, $project);
$categoryBook = $build->invoke($categoryExport, true);
foreach (array('Xlsx', 'Ods') as $format) {
	$writerClass = 'PhpOffice\\PhpSpreadsheet\\Writer\\'.$format;
	$categoryFile = __DIR__.'/.cache/cost-categories.'.strtolower($format);
	$writer = new $writerClass($categoryBook); $writer->save($categoryFile);
	$loaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($categoryFile);
	$categoryRows = array();
	foreach ($loaded->getSheet(0)->toArray() as $row) {
		if ($row[0] === 'Matériel électrique') { $categoryRows[] = $row; }
	}
	check(count($categoryRows), 1, $format.' contains product commercial category');
	check((float) $categoryRows[0][3], 126, $format.' category total includes shipment reconciliation');
	$loaded->disconnectWorksheets();
}
$categoryBook->disconnectWorksheets();

// Customer quantities alone must not claim a complete valuation, including exports.
$db->query('UPDATE '.MAIN_DB_PREFIX.'expedition SET fk_statut=0');
$db->query('UPDATE '.MAIN_DB_PREFIX.'facture_fourn SET fk_statut=0');
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande_fournisseur SET fk_statut=1');
$unvalued = lmdbadvancedproject_load_budget_report_data(1, $filters);
check($unvalued['productCosts']['products']['1:1']['has_cost'], false, 'Customer/pending quantities have no cost to value');
$statusExport = new LmdbAdvancedProjectBudgetReportExport($langs, $unvalued);
$statusBook = $build->invoke($statusExport, false);
check($statusBook->getSheet(3)->getCell('A1')->getValue(), 'BudgetCostNotApplicable', 'Spreadsheet heading does not claim complete valuation');
check($statusBook->getSheet(3)->getCell('Q6')->getValue(), 'BudgetCostNotApplicable', 'Spreadsheet row carries the neutral status');
$statusBook->disconnectWorksheets();
$db->query('UPDATE '.MAIN_DB_PREFIX.'expedition SET fk_statut=3');
$db->query('UPDATE '.MAIN_DB_PREFIX.'facture_fourn SET fk_statut=1');
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande_fournisseur SET fk_statut=3');
echo $checks." assertions passed including shipment commercial categories and serialized summaries.\n";
