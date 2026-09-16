<?php
/* SQL/reconciliation regression tests with an in-memory relational fixture.
 * This adapter is not a Dolibarr/Multicompany operational instance.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
require __DIR__.'/costledger_test.php';
define('DOL_VERSION', getenv('DOLIBARR_VERSION') ?: '20.0.0');
define('MAIN_DB_PREFIX', 'test_long_module_prefix_');
if (!defined('MODULE_MAPPING')) { define('MODULE_MAPPING', array()); }
define('DOL_URL_ROOT', '');
define('DOL_APPLICATION_TITLE', 'Dolibarr');
require_once (getenv('LMDBAP_BASE_BUDGET_LIB') ?: __DIR__.'/../lib/budgetreport.lib.php');
class FixtureDB {
	public $connection;
	public $lastError = '';
	public function __construct() { $this->connection = new PDO('sqlite::memory:'); $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }
	public function query($sql) {
		$sql = preg_replace("/SHOW TABLES LIKE '([^']+)'/", "SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '$1'", $sql);
		$sql = str_replace(' FOR UPDATE', '', $sql);
		$sql = str_replace('HAVING amount_ht > 0', 'AND amount_ht > 0', $sql);
		$sql = str_replace('ON DUPLICATE KEY UPDATE fingerprint = fingerprint', 'ON CONFLICT(fingerprint) DO NOTHING', $sql);
		try { $stmt = $this->connection->query($sql); return (object) array('rows' => $stmt->fetchAll(PDO::FETCH_OBJ), 'index' => 0); }
		catch (PDOException $e) { $this->lastError = $e->getMessage(); throw new RuntimeException($this->lastError."\n".$sql); }
	}
	public function fetch_object($result) { return $result->rows[$result->index++] ?? false; }
	public function num_rows($result) { return count($result->rows); }
	public function free($result) { }
	public function fetch_row($result) { $row=$this->fetch_object($result); return $row ? array_values((array)$row) : false; }
	public function encrypt($text, $mode=0) { return "'".$this->escape($text)."'"; }
	public function decrypt($column) { return $column; }
	public function prefix() { return MAIN_DB_PREFIX; }
	public function escape($text) { return str_replace("'", "''", (string) $text); }
	public function sanitize($text) { if (!preg_match('/^[A-Za-z0-9_,.]+$/', $text)) { throw new RuntimeException('Invalid entity scope'); } return $text; }
	public function idate($timestamp) { return date('Y-m-d H:i:s', $timestamp); }
	public function jdate($date) { return $date ? strtotime($date) : null; }
	public function lasterror() { return $this->lastError; }
}
$db = new FixtureDB();
$langs = new class {
	public $translations = array(); public $charset_output = 'UTF-8'; public $defaultlang = 'en_US';
	public function trans($key, ...$args) { return $this->transnoentities($key, ...$args); }
	public function transnoentitiesnoconv($key) { return array('SeparatorDecimal' => '.', 'SeparatorThousand' => ',')[$key] ?? $this->transnoentities($key); }
	public function transnoentities($key, ...$args) { return $this->translations[$key] ?? array('BudgetReportExportSheetReport'=>'Report','BudgetReportExportSheetTime'=>'Time','BudgetReportExportSheetCharts'=>'Charts','BudgetCostProductList'=>'Products','BudgetCostContributions'=>'Contributions')[$key] ?? $key; }
	public function convToOutputCharset($text) { return $text; }
	public function getCurrencySymbol($currency) { return $currency; }
	public function loadLangs($langs) { }
	public function load($langs) { }
};
$user = new class {
	public $id = 1; public $socid = 0; public $allowed = true;
	public function hasRight(...$right) { return $this->allowed; }
	public function getFullName($langs) { return 'Test'; }
};
$conf->entity = 1; $conf->currency = 'EUR'; $conf->modules = array_fill_keys(array('lmdbadvancedproject','projet','expedition','product','stock'), 1);
foreach ($conf->modules as $module => $enabled) { $conf->$module = (object) array('enabled' => 1); }
$conf->global->LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST = 1;
$conf->global->LMDBADVANCEDPROJECT_ENABLE_SUPPLIER_INVOICE_SPLIT = 1;
$conf->global->LMDBADVANCEDPROJECT_SHIPMENT_COST_METHOD = 'supplier_tariff';
$hookmanager = new class { public $resPrint = ''; public function initHooks($contexts) { } public function executeHooks(...$args) { return 0; } };
$mc = new class { public $scope = '1'; public function getEntity(...$args) { return $this->scope; } };
$object = null; $action = '';
$schemas = array(
	'const' => 'rowid INTEGER PRIMARY KEY, entity INTEGER, name TEXT, value TEXT',
	'element_element' => 'rowid INTEGER PRIMARY KEY, fk_source INTEGER, fk_target INTEGER, sourcetype TEXT, targettype TEXT',
	'projet' => 'rowid INTEGER PRIMARY KEY, entity INTEGER',
	'product' => 'rowid INTEGER PRIMARY KEY, entity INTEGER, ref TEXT, label TEXT, fk_product_type INTEGER, fk_unit INTEGER',
	'c_units' => 'rowid INTEGER PRIMARY KEY, label TEXT',
	'societe' => 'rowid INTEGER PRIMARY KEY, entity INTEGER',
	'commande' => 'rowid INTEGER PRIMARY KEY, ref TEXT, entity INTEGER, fk_projet INTEGER, date_commande TEXT, fk_statut INTEGER',
	'commandedet' => 'rowid INTEGER PRIMARY KEY, fk_commande INTEGER, fk_product INTEGER, fk_unit INTEGER, qty REAL, total_ht REAL',
	'commande_fournisseur' => 'rowid INTEGER PRIMARY KEY, ref TEXT, entity INTEGER, fk_projet INTEGER, date_commande TEXT, date_creation TEXT, fk_statut INTEGER',
	'commande_fournisseurdet' => 'rowid INTEGER PRIMARY KEY, fk_commande INTEGER, fk_product INTEGER, fk_unit INTEGER, qty REAL, total_ht REAL',
	'facture_fourn' => 'rowid INTEGER PRIMARY KEY, ref TEXT, entity INTEGER, fk_projet INTEGER, datef TEXT, fk_statut INTEGER, type INTEGER',
	'facture_fourn_det' => 'rowid INTEGER PRIMARY KEY, fk_facture_fourn INTEGER, fk_product INTEGER, fk_unit INTEGER, qty REAL, total_ht REAL',
	'lmdbadvancedproject_supplier_invoice_parts' => 'rowid INTEGER PRIMARY KEY, entity INTEGER, fk_projet INTEGER, fk_facture_fourn INTEGER, fk_facture_fourn_det INTEGER, qty REAL, total_ht REAL',
	'expedition' => 'rowid INTEGER PRIMARY KEY, ref TEXT, entity INTEGER, fk_projet INTEGER, date_expedition TEXT, date_valid TEXT, fk_statut INTEGER',
	'expeditiondet' => 'rowid INTEGER PRIMARY KEY, fk_expedition INTEGER, fk_elementdet INTEGER, element_type TEXT, fk_product INTEGER, qty REAL',
	'product_fournisseur_price' => 'rowid INTEGER PRIMARY KEY, entity INTEGER, fk_product INTEGER, fk_soc INTEGER, datec TEXT, unitprice REAL, quantity REAL, remise_percent REAL, remise REAL, fk_supplier_price_expression INTEGER',
	'product_fournisseur_price_log' => 'rowid INTEGER PRIMARY KEY, fk_product_fournisseur INTEGER, datec TEXT',
);
foreach ($schemas as $table => $columns) { $db->query('CREATE TABLE '.MAIN_DB_PREFIX.$table.' ('.$columns.')'); }
// Translate only DDL syntax for the test backend; production SQL stays MySQL.
foreach (glob(__DIR__.'/../sql/llx_lmdbap_*.sql') as $file) {
	if (strpos($file, '.key.sql') !== false) { continue; }
	$sql = preg_replace('/^--.*$/m', '', file_get_contents($file));
	$sql = str_replace('llx_', MAIN_DB_PREFIX, $sql);
	$sql = str_replace('AUTO_INCREMENT', '', $sql);
	$sql = preg_replace('/UNIQUE KEY \w+ \(/', 'UNIQUE (', $sql);
	$sql = preg_replace('/,\s*KEY \w+ \([^)]*\)/', '', $sql);
	$sql = str_replace('ENGINE=innodb', '', $sql);
	$db->query($sql); $db->query($sql);
}
foreach (glob(__DIR__.'/../sql/llx_lmdbap_*.key.sql') as $file) {
	$sql = str_replace('llx_', MAIN_DB_PREFIX, file_get_contents($file));
	preg_match_all('/ALTER TABLE (\w+) ADD (UNIQUE KEY|KEY|INDEX) (\w+) (\([^;]+\));/', $sql, $indexes, PREG_SET_ORDER);
	foreach ($indexes as $index) {
		check(strlen($index[3]) <= 64, true, 'MySQL index identifier length');
		$ddl = 'CREATE '.($index[2] === 'UNIQUE KEY' ? 'UNIQUE ' : '').'INDEX IF NOT EXISTS '.$index[3].' ON '.$index[1].' '.$index[4];
		$db->query($ddl); $db->query($ddl);
	}
}
function insertFixture(string $table, array $values): void {
	global $db;
	$sql = 'INSERT INTO '.MAIN_DB_PREFIX.$table.' ('.implode(',', array_keys($values)).') VALUES (';
	$sql .= implode(',', array_map(static function ($value) use ($db) { return $value === null ? 'NULL' : "'".$db->escape($value)."'"; }, $values)).')';
	$db->query($sql);
}
foreach (array(1,2) as $entity) {
	insertFixture('const', array('entity'=>$entity, 'name'=>'MAIN_MONNAIE', 'value'=>'EUR'));
	insertFixture('projet', array('rowid' => $entity, 'entity' => $entity));
	insertFixture('societe', array('rowid' => $entity, 'entity' => $entity));
}
insertFixture('product', array('rowid' => 1, 'entity' => 1, 'ref' => 'A', 'label' => 'A', 'fk_product_type' => 0, 'fk_unit' => 1));
insertFixture('c_units', array('rowid' => 1, 'label' => 'unit'));
insertFixture('commande', array('rowid'=>1,'ref'=>'CO1','entity'=>1,'fk_projet'=>1,'date_commande'=>'2026-02-01 00:00:00','fk_statut'=>1));
insertFixture('commandedet', array('rowid'=>1,'fk_commande'=>1,'fk_product'=>1,'fk_unit'=>1,'qty'=>10,'total_ht'=>200));
insertFixture('commande_fournisseur', array('rowid'=>1,'ref'=>'SO1','entity'=>1,'fk_projet'=>1,'date_commande'=>'2026-02-01 00:00:00','date_creation'=>'2026-02-01','fk_statut'=>3));
insertFixture('commande_fournisseurdet', array('rowid'=>1,'fk_commande'=>1,'fk_product'=>1,'fk_unit'=>1,'qty'=>12,'total_ht'=>108));
insertFixture('expedition', array('rowid'=>1,'ref'=>'SH1','entity'=>1,'fk_projet'=>null,'date_expedition'=>'2026-03-01 00:00:00','date_valid'=>'2026-03-01 00:00:00','fk_statut'=>1));
insertFixture('expeditiondet', array('rowid'=>1,'fk_expedition'=>1,'fk_elementdet'=>1,'element_type'=>'commande','fk_product'=>1,'qty'=>10));
insertFixture('product_fournisseur_price', array('rowid'=>1,'entity'=>1,'fk_product'=>1,'fk_soc'=>1,'datec'=>'2026-01-01 00:00:00','unitprice'=>12.5,'quantity'=>10,'remise_percent'=>20,'remise'=>0,'fk_supplier_price_expression'=>0));
insertFixture('facture_fourn', array('rowid'=>1,'ref'=>'INV1','entity'=>1,'fk_projet'=>1,'datef'=>'2026-04-01 00:00:00','fk_statut'=>1,'type'=>0));
insertFixture('facture_fourn_det', array('rowid'=>1,'fk_facture_fourn'=>1,'fk_product'=>1,'fk_unit'=>1,'qty'=>10,'total_ht'=>120));
insertFixture('lmdbadvancedproject_supplier_invoice_parts', array('rowid'=>1,'entity'=>1,'fk_projet'=>1,'fk_facture_fourn'=>1,'fk_facture_fourn_det'=>1,'qty'=>4,'total_ht'=>48));
insertFixture('lmdbadvancedproject_supplier_invoice_parts', array('rowid'=>2,'entity'=>1,'fk_projet'=>2,'fk_facture_fourn'=>1,'fk_facture_fourn_det'=>1,'qty'=>6,'total_ht'=>72));
$service = new LmdbAdvancedProjectProductCost($db);
$result = $service->load(array(1,2));
check(total($result), 126, 'SQL source fallback, discounted tariff, allocated invoice not recomputed');
check(count($result['products']), 1, 'Unauthorized project entity excluded');
$mc->scope = '1,2';
$result = $service->load(array(1,2));
check(total($result), 198, 'Authorized shared projects retain allocated quantities');
check($result['products']['2:1']['invoice_cost'], 72, 'Allocation to second project');
check(total($service->load(array(1), array(), array('ref' => 'B'))), 0, 'SQL reference filter');
check(total($service->load(array(1), array(), array('type' => '1'))), 0, 'SQL product/service filter');
check(total($service->load(array(1), array(), array('entities' => array(1)))), 126, 'SQL environment filter retains coverage');
$filters = array('date_start'=>'2026-04-01','date_end'=>'2026-04-30','exclude_content_outside_period'=>'1');
check(total($service->load(array(1), $filters)), 8, 'Partial invoice April movement 48 - 40');
$db->query('UPDATE '.MAIN_DB_PREFIX.'expedition SET fk_projet = 2');
check(in_array('BudgetCostProjectConflict', $service->load(array(1))['issues'], true), true, 'Conflict visible from source order project');
check(in_array('BudgetCostProjectConflict', $service->load(array(2))['issues'], true), true, 'Conflict visible from shipment project');
$db->query('UPDATE '.MAIN_DB_PREFIX.'expedition SET fk_projet = NULL');
foreach (array(0,-1) as $status) {
	$db->query('UPDATE '.MAIN_DB_PREFIX.'expedition SET fk_statut = '.$status);
	check($service->load(array(1))['products']['1:1']['shipped_qty'], 0, 'Draft/cancelled excluded');
}
$db->query('UPDATE '.MAIN_DB_PREFIX.'expedition SET fk_statut = 3');
check($service->load(array(1))['products']['1:1']['shipped_qty'], 10, 'Closed shipment included');
$conf->global->LMDBADVANCEDPROJECT_SHIPMENT_COST_METHOD = 'pmp';
check($service->load(array(1))['complete'], false, 'No current PMP substituted for missing historic snapshot');
$conf->global->LMDBADVANCEDPROJECT_SHIPMENT_COST_METHOD = 'supplier_tariff';
$conf->global->LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST = 0;
check($service->load(array(1))['events'], array(), 'Switch off');
check(LmdbAdvancedProjectProductCost::legacyLineFilter('ffd'), '', 'Legacy SQL unchanged when switch off');
$conf->global->LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST = 1;
$user->allowed = false;
try { $service->load(array(1)); throw new LogicException('Access should be denied'); }
catch (RuntimeException $e) { check($e->getMessage(), 'BudgetCostAccessDenied', 'Direct service rights denial'); }
$user->allowed = true;
$service->captureTariff(1, $user); $service->captureTariff(1, $user);
check($db->num_rows($db->query('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbap_tariff_history')), 1, 'Tariff replay idempotent');
echo $checks." total assertions passed including source SQL, sharing-scope fixtures and native split exclusion.\n";

insertFixture('element_element', array('rowid'=>1,'fk_source'=>1,'fk_target'=>1,'sourcetype'=>'order_supplier','targettype'=>'invoice_supplier'));
check($service->load(array(1))['products']['1:1']['remaining_qty'], 2, 'Linked split: ten units invoiced across projects leave two committed');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbadvancedproject_supplier_invoice_parts SET qty=8,total_ht=96 WHERE rowid=2');
check($service->load(array(1))['products']['1:1']['remaining_qty'], 0, 'Linked split fully invoiced elsewhere does not leave original commitments');
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'element_element');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbadvancedproject_supplier_invoice_parts SET qty=6,total_ht=72 WHERE rowid=2');
echo "Linked allocation tests passed.\n";

$db->query('UPDATE '.MAIN_DB_PREFIX.'commande_fournisseur SET fk_statut=1');
check($service->load(array(1))['products']['1:1']['ordered_qty'],12,'Pending supplier order quantities visible');
check($service->load(array(1))['products']['1:1']['remaining_qty'],0,'Pending supplier order not committed');
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande_fournisseur SET fk_statut=3');
