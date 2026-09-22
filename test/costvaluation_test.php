<?php
/* 1.4.1 analytical instructions: real native objects, relational SQLite fixtures.
 * This is not a concurrent MySQL or operational Multicompany acceptance test.
 */
require __DIR__.'/costexports_test.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
class CostFixtureUser extends User
{
	public $denied = array();
	public function hasRight($module, $level1, $level2 = null)
	{
		return !in_array(implode('.', array_filter(array($module, $level1, $level2))), $this->denied, true);
	}
}
$user = new CostFixtureUser($db); $user->id = 1; $user->socid = 0;
foreach (array('product', 'projet', 'societe', 'c_units', 'c_effectif', 'c_stcomm', 'c_forme_juridique', 'c_departements', 'c_regions', 'c_typent', 'c_incoterms', 'c_payment_term', 'c_paiement', 'c_availability', 'c_input_reason', 'product_fournisseur_price') as $table) {
	$schema = file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_'.$table.'.sql');
	preg_match_all('/^\s*([a-z][a-z0-9_]*)\s+(integer|int|varchar|char|text|mediumtext|longtext|double|real|float|tinyint|smallint|boolean|datetime|timestamp|date)\b/mi', $schema, $columns, PREG_SET_ORDER);
	$db->query('CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX.$table.' (rowid INTEGER PRIMARY KEY)');
	$existing = array_map(static function ($row) { return $row->name; }, $db->query('PRAGMA table_info('.MAIN_DB_PREFIX.$table.')')->rows);
	foreach ($columns as $column) {
		if (in_array($column[1], $existing, true)) { continue; }
		$numeric = in_array(strtolower($column[2]), array('integer', 'int', 'double', 'real', 'float', 'tinyint', 'smallint', 'boolean'), true);
		$db->query('ALTER TABLE '.MAIN_DB_PREFIX.$table.' ADD COLUMN '.$column[1].($numeric ? ' REAL DEFAULT 0' : " TEXT DEFAULT ''"));
	}
}
$extrafields = (object) array('attributes' => array());
foreach (array('product', 'projet', 'societe', 'commande', 'commandedet') as $table) {
	$extrafields->attributes[$table] = array('loaded' => true, 'label' => array());
	$db->query('CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX.$table.'_extrafields (fk_object INTEGER, tms TEXT)');
}
$db->query('UPDATE '.MAIN_DB_PREFIX."projet SET public=1, fk_soc=1");
$db->query('UPDATE '.MAIN_DB_PREFIX."product SET cost_price=18,pmp=20");
$db->query('UPDATE '.MAIN_DB_PREFIX."societe SET nom='Supplier',fournisseur=1");
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_soc=1');
$conf->global->LMDBADVANCEDPROJECT_SHIPMENT_COST_METHOD = 'pmp';
$mc->scope = '1,2';
$valuation = new LmdbAdvancedProjectCostValuation($db);
foreach (array('' => null, 'abc' => null, '-1' => null, '0' => 0.0, '12.125' => 12.125, 'INF' => null) as $input => $expected) {
	check(LmdbAdvancedProjectCostValuation::amount((string) $input), $expected, 'Validated free unit cost');
}
$quote = $valuation->prepare(1, 1);
check(isset($quote['choices']['native'], $quote['choices']['pmp']), true, 'Native product and PMP choices');
check($valuation->save($quote, 'free', '15', false, 'first'), 1, 'Project instruction saved');
check($valuation->save($quote, 'free', '15', false, 'first'), 0, 'Identical replay does not duplicate');
$valued = $service->load(array(1, 2));
check($valued['products']['1:1']['provisional_cost'], 90, 'Six remaining units use frozen price');
check($valued['products']['1:1']['invoice_cost'], 48, 'Partial invoice conserved');
check($valued['products']['2:1']['invoice_cost'], 72, 'Other project conserved');
check($valued['products']['1:1']['can_value'], false, 'Existing instruction cannot be replaced');
check($db->num_rows($db->query('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbap_cost_instruction')), 1, 'Unique instruction');

// A second project with missing costs, existing before the modal opens.
insertFixture('commande', array('rowid'=>2,'ref'=>'CO2','entity'=>2,'fk_projet'=>2,'fk_soc'=>2,'date_commande'=>'2026-02-01','fk_statut'=>1));
insertFixture('commandedet', array('rowid'=>2,'fk_commande'=>2,'fk_product'=>1,'fk_unit'=>1,'qty'=>10,'total_ht'=>200));
insertFixture('expedition', array('rowid'=>2,'ref'=>'SH2','entity'=>2,'fk_projet'=>2,'date_expedition'=>'2026-03-01 00:00:00','date_valid'=>'2026-03-01 00:00:00','fk_statut'=>1));
insertFixture('expeditiondet', array('rowid'=>2,'fk_expedition'=>2,'fk_elementdet'=>2,'element_type'=>'commande','fk_product'=>1,'qty'=>10));
check($service->load(array(2))['products']['2:1']['provisional_cost'], null, 'Same product remains missing in another project');
$quote2 = (new LmdbAdvancedProjectCostValuation($db))->prepare(2, 1);
check(array_keys($quote2['targets']), array(2), 'Existing instruction excluded from propagation');
check($valuation->save($quote2, 'free', '0', true, 'zero'), 1, 'Explicit zero accepted');
check($service->load(array(2))['products']['2:1']['provisional_cost'], 0, 'Zero is known');
check($service->load(array(1))['products']['1:1']['provisional_cost'], 90, 'Propagation never overwrites existing price');

// Future shipment inherits only the instruction for its existing project.
insertFixture('expedition', array('rowid'=>3,'ref'=>'SH3','entity'=>1,'fk_projet'=>1,'date_expedition'=>'2026-05-01 00:00:00','date_valid'=>'2026-05-01 00:00:00','fk_statut'=>1));
insertFixture('expeditiondet', array('rowid'=>3,'fk_expedition'=>3,'fk_elementdet'=>1,'element_type'=>'commande','fk_product'=>1,'qty'=>2));
check($service->load(array(1))['products']['1:1']['provisional_cost'], 120, 'Future shipment of treated project inherits frozen cost');

// Reset only fixture instructions to exercise independent confirmations.
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbap_cost_instruction');
$quote = (new LmdbAdvancedProjectCostValuation($db))->prepare(1, 1);
check(count($quote['targets']), 2, 'Propagation finds eligible projects independently of page filters');
$db->query('UPDATE '.MAIN_DB_PREFIX.'product SET cost_price=19');
try { $valuation->save($quote, 'native', '', false, 'changed'); check(true, false, 'Changed price rejected'); }
catch (RuntimeException $e) { check($e->getMessage(), 'BudgetCostConflict', 'Changed source produces explicit conflict'); }
check($db->num_rows($db->query('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbap_cost_instruction')), 0, 'Conflict rolls back');
$quote = (new LmdbAdvancedProjectCostValuation($db))->prepare(1, 1);
check($valuation->save($quote, 'native', '', true, 'both'), 2, 'Atomic propagation to two eligible projects');
check($service->load(array(2))['products']['2:1']['provisional_cost'], 76, 'Propagated net cost preserves invoice coverage');

// Stored analytical evidence survives provider deactivation and is not a product update.
$conf->stock->enabled = 0; $conf->modules['stock']=0;
check($service->load(array(1))['products']['1:1']['provisional_cost'], 152, 'Saved cost independent of provider activation');
check((float) $db->query('SELECT cost_price FROM '.MAIN_DB_PREFIX.'product WHERE rowid=1')->rows[0]->cost_price, 19, 'Native product not modified');
$conf->stock->enabled = 1; $conf->modules['stock']=1;
foreach (array('lmdbadvancedproject.budgetreport.read', 'projet.lire', 'product.read') as $denied) {
	$user->denied = array($denied);
	try { (new LmdbAdvancedProjectCostValuation($db))->prepare(1, 1); check(true, false, 'Permission denied'); }
	catch (RuntimeException $e) { check($e->getMessage(), 'BudgetCostAccessDenied', 'Functional permission enforced directly'); }
}
$user->denied = array();
$conf->global->MAIN_USE_ADVANCED_PERMS = 1; $user->denied = array('product.product_advance.read_prices');
check((new LmdbAdvancedProjectCostValuation($db))->product(1), null, 'Advanced price permission required');
$conf->global->MAIN_USE_ADVANCED_PERMS = 0; $user->denied = array();

$current = (object) array('fk_product'=>1,'entity'=>1,'from_qty'=>10,'fk_soc'=>1,'fk_cat'=>0,'fk_cat_propal'=>0,'fk_cat_order'=>0,'fk_cat_invoice'=>0,'fk_cat_contract'=>0);
$old = clone $current; $old->rowid=1; $old->datec='2026-01-01 00:00:00'; $old->cost_price=10; $old->cost_price_source='custom';
$new = clone $old; $new->rowid=2; $new->datec='2026-03-01 00:00:00'; $new->cost_price=12;
check(LmdbAdvancedProjectCostValuation::historicalRow(array($new,$old),$current,'2026-02-01')->rowid, 1, 'Latest historical cost before shipment');
check(LmdbAdvancedProjectCostValuation::historicalRow(array($old,$new),$current,$new->datec)->rowid, 2, 'Same-date history included');
check(LmdbAdvancedProjectCostValuation::historicalRow(array($old),$current,'2025-01-01'), null, 'Future history excluded');
$new->cost_price=null;
check(LmdbAdvancedProjectCostValuation::historicalRow(array($old,$new),$current,'2026-04-01'), null, 'New missing value blocks older positive price');
$new->cost_price=12; $new->cost_price_source='product';
check(LmdbAdvancedProjectCostValuation::historicalRow(array($new),$current,'2026-04-01'), null, 'No invented historical native price');
$new->cost_price_source='dynamicprices';
check(LmdbAdvancedProjectCostValuation::historicalRow(array($new),$current,'2026-04-01'), null, 'No invented historical DynamicPrices amount');
foreach (array('from_qty','fk_soc','fk_cat','fk_cat_order') as $field) {
	$changed = clone $current; $changed->$field++;
	check(LmdbAdvancedProjectCostValuation::historicalRow(array($old),$changed,'2026-04-01'), null, 'Changed targeting or quantity tier not reconstructed');
}
echo $checks." assertions passed including immutable valuation, permissions, propagation and historical rules.\n";
