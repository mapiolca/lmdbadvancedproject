<?php
/* Whole report selection and real native spreadsheet serialization fixtures. */
require __DIR__.'/costsources_test.php';
// Legacy native dependencies emit PHP 8.5 deprecations; keep warnings/errors visible.
error_reporting(E_ALL & ~E_DEPRECATED);
foreach (array('projet','commande','commandedet','commande_fournisseur','commande_fournisseurdet','facture_fourn','facture_fourn_det','facture','facturedet','projet_task','element_time','user','expensereport','expensereport_det','extrafields','c_tva','c_country') as $table) {
	$schema = file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_'.$table.'.sql');
	preg_match_all('/^\s*([a-z][a-z0-9_]*)\s+(integer|int|varchar|char|text|double|real|float|tinyint|smallint|boolean|datetime|timestamp|date)\b/mi', $schema, $columns, PREG_SET_ORDER);
	$db->query('CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX.$table.' (rowid INTEGER PRIMARY KEY)');
	$existing = array_map(static function ($row) { return $row->name; }, $db->query('PRAGMA table_info('.MAIN_DB_PREFIX.$table.')')->rows);
	foreach ($columns as $column) {
		if (in_array($column[1], $existing,true)) { continue; }
		$numeric = in_array(strtolower($column[2]),array('integer','int','double','real','float','tinyint','smallint','boolean'),true);
		$db->query('ALTER TABLE '.MAIN_DB_PREFIX.$table.' ADD COLUMN '.$column[1].($numeric ? ' REAL DEFAULT 0' : " TEXT DEFAULT ''"));
	}
}
$db->connection->sqliteCreateFunction('DATE_FORMAT', static function ($date, $format) { return $date ? date(str_replace(array('%Y','%m','%d'),array('Y','m','d'),$format),strtotime($date)) : ''; });
$db->connection->sqliteCreateFunction('GREATEST', static function (...$values) { return max($values); });
$db->query('UPDATE '.MAIN_DB_PREFIX."projet SET ref='P1',title='Project',dateo='2026-02-01',datee='2026-05-01',fk_statut=1");
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET total_ht=200');
$db->query('UPDATE '.MAIN_DB_PREFIX.'commandedet SET buy_price_ht=15');
$db->query('ALTER TABLE '.MAIN_DB_PREFIX."lmdbadvancedproject_supplier_invoice_parts ADD COLUMN date TEXT");
$conf->tzuserinputkey='tzuser';
$conf->format_date_hour_short='%Y-%m-%d %H:%M';
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande_fournisseur SET total_ht=108');
$db->query('UPDATE '.MAIN_DB_PREFIX.'facture_fourn SET total_ht=120');
$filters=array('date_start'=>'2026-01-01','date_end'=>'2026-12-31','project_status'=>'both','exclude_content_outside_period'=>'1');
$mc->scope='1';
if (getenv('LMDBAP_LEGACY_OUTPUT')) {
	$conf->global->LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST=0;
	$legacyData=lmdbadvancedproject_load_budget_report_data(1,$filters);
	unset($legacyData['productCosts'],$legacyData['totalshipmentcost']);
	file_put_contents(getenv('LMDBAP_LEGACY_OUTPUT'),json_encode($legacyData));
	exit(0);
}
$data=lmdbadvancedproject_load_budget_report_data(1,$filters);
check($data['totalspent'],126,'Project report has identical ledger total');
check($data['budgetReportForecast']['totals']['supplier_expenses'],126,'Categories use same product costs');
check($data['projects'][1]['spent'],126,'Project margins include shipment delta');
$global=lmdbadvancedproject_load_budget_report_data(0,$filters);
check($global['totalspent'],126,'Global report equals project');
$period=$filters; $period['date_start']='2026-04-01'; $period['date_end']='2026-04-30';
$april=lmdbadvancedproject_load_budget_report_data(1,$period);
check($april['totalspent'],8,'Screen April movement equals 48 actual less 40 provisional');
check(array_sum($april['mospents']),8,'Monthly graph includes correction');
$conf->global->LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST=0;
$legacy=lmdbadvancedproject_load_budget_report_data(1,$filters);
check($legacy['totalshipmentcost'],0,'Disabled report contains no shipment costs');
check(count($legacy['productCosts']['products']),0,'Disabled report does not load the new ledger');
$conf->global->LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST=1;
define('PHPEXCELNEW_PATH', DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/PhpSpreadsheet/');
require_once __DIR__.'/../class/budgetreportexport.class.php';
$export=new LmdbAdvancedProjectBudgetReportExport($langs,$data);
$build=new ReflectionMethod($export,'buildSpreadsheet');
$book=$build->invoke($export,true);
check($book->getSheetCount(),5,'Product and contribution worksheets included');
check($book->getSheet(0)->getCell('D8')->getValue(),126,'Spreadsheet main total equals screen');
check($book->getSheet(3)->getCell('P6')->getValue(),126,'Product worksheet retained cost');
$eventTotal=0;
for($row=2;$row<=$book->getSheet(4)->getHighestRow();$row++) { $value=$book->getSheet(4)->getCell('G'.$row)->getValue(); if(is_numeric($value)) {$eventTotal+=(float)$value;} }
check($eventTotal,126,'Exported dated movements sum to screen total');
foreach(array('Xlsx','Ods') as $format) {
	$writerClass='PhpOffice\\PhpSpreadsheet\\Writer\\'.$format;
	$file=__DIR__.'/.cache/cost-report.'.strtolower($format);
	$writer=new $writerClass($book);
	if($format==='Xlsx') {$writer->setIncludeCharts(true);}
	$writer->save($file);
	$loaded=\PhpOffice\PhpSpreadsheet\IOFactory::load($file);
	check((float)$loaded->getSheet(0)->getCell('D8')->getValue(),126,$format.' read-back total');
	check((float)$loaded->getSheet(3)->getCell('P6')->getValue(),126,$format.' read-back product total');
	check((float)$loaded->getSheet(3)->getCell('L6')->getValue(),60,$format.' read-back outstanding provisional balance');
	$loaded->disconnectWorksheets();
}
$book->disconnectWorksheets();
echo $checks." assertions passed including whole reports, categories and XLSX/ODS round trips.\n";
