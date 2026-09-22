<?php
/* Execute read-only optional provider contracts, using their source checkout.
 * SQL fixture only, no external calculation, ERP connection or provider write.
 */
require __DIR__.'/costvaluation_test.php';
$dynamicRoot = getenv('DYNAMICPRICES_ROOT') ?: dirname(__DIR__, 2).'/dynamicprices';
$priceListRoot = getenv('PRICELIST_ROOT') ?: dirname(__DIR__, 2).'/pricelist';
foreach (array($dynamicRoot.'/class/dynamicpricescostservice.class.php', $priceListRoot.'/class/pricelist.class.php') as $file) {
	if (!is_file($file)) { fwrite(STDERR, "Optional provider sources missing; set DYNAMICPRICES_ROOT and PRICELIST_ROOT.\n"); exit(1); }
	require_once $file;
}
foreach (array('dynamicprices_product_cost'=>$dynamicRoot, 'pricelist'=>$priceListRoot, 'pricelist_log'=>$priceListRoot) as $table=>$directory) {
	$schema = file_get_contents($directory.'/sql/llx_'.$table.'.sql');
	preg_match_all('/^\s*`?([a-z][a-z0-9_]*)`?\s+(integer|int|varchar|char|text|double|real|float|tinyint|smallint|boolean|datetime|timestamp|date)\b/mi', $schema, $columns, PREG_SET_ORDER);
	$db->query('CREATE TABLE '.MAIN_DB_PREFIX.$table.' (rowid INTEGER PRIMARY KEY)');
	foreach ($columns as $column) {
		if ($column[1] === 'rowid') { continue; }
		$numeric = in_array(strtolower($column[2]),array('integer','int','double','real','float','tinyint','smallint','boolean'),true);
		$db->query('ALTER TABLE '.MAIN_DB_PREFIX.$table.' ADD COLUMN '.$column[1].($numeric ? ' REAL' : ' TEXT'));
	}
}
$conf->dynamicsprices = (object) array('enabled'=>1); $conf->modules['dynamicsprices']=1;
$conf->global->DYNAMICPRICES_COST_ENABLE = 1;
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbap_cost_instruction');
insertFixture('dynamicprices_product_cost', array('rowid'=>1,'entity'=>1,'fk_product'=>1,'dynamic_cost_price'=>13,'currency_code'=>'EUR','price_base_type'=>'HT','date_calculation'=>'2026-02-15 00:00:00','calculation_status'=>1,'status'=>1));
check(LmdbAdvancedProjectCompatibility::costProviderAvailable('dynamicsprices'), true, 'Real DynamicPrices read contract available');
check((new LmdbAdvancedProjectCostValuation($db))->automatic(1,1,0,0,'2026-03-01')['price'], 13, 'Earlier DynamicPrices automatic cost');
check((new LmdbAdvancedProjectCostValuation($db))->automatic(1,1,0,0,'2026-02-01'), null, 'Later DynamicPrices cannot value old shipment');
check((new LmdbAdvancedProjectCostValuation($db))->dynamicPrice(1,1)['price'], 13, 'Current cost remains explicitly selectable');
check($service->load(array(1))['products']['1:1']['provisional_cost'], 104, 'Automatic DynamicPrices integrated before FIFO');
foreach (array('calculation_status'=>-1,'status'=>0,'dynamic_cost_price'=>0,'currency_code'=>'USD','price_base_type'=>'TTC') as $field=>$invalid) {
	$oldValue = $db->query('SELECT '.$field.' FROM '.MAIN_DB_PREFIX.'dynamicprices_product_cost WHERE rowid=1')->rows[0]->$field;
	$db->query('UPDATE '.MAIN_DB_PREFIX.'dynamicprices_product_cost SET '.$field."='".$db->escape($invalid)."' WHERE rowid=1");
	check((new LmdbAdvancedProjectCostValuation($db))->dynamicPrice(1,1), null, 'Unusable dynamic cost excluded: '.$field);
	$db->query('UPDATE '.MAIN_DB_PREFIX.'dynamicprices_product_cost SET '.$field."='".$db->escape($oldValue)."' WHERE rowid=1");
}
$user->denied = array('dynamicsprices.cost.read');
check((new LmdbAdvancedProjectCostValuation($db))->dynamicPrice(1,1), null, 'DynamicPrices permission enforced');
$user->denied = array();

$db->query("UPDATE ".MAIN_DB_PREFIX."dynamicprices_product_cost SET date_calculation='2026-06-01 00:00:00'");
$quote=(new LmdbAdvancedProjectCostValuation($db))->prepare(1,1);
check(isset($quote['choices']['dynamicprices']),true,'Later DynamicPrices proposed for explicit choice');
check((new LmdbAdvancedProjectCostValuation($db))->save($quote,'dynamicprices','',false,'explicit-dynamic'),1,'Explicit later DynamicPrices frozen');
check($service->load(array(1))['products']['1:1']['provisional_cost'],104,'Explicit later cost fills old missing shipments');
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'lmdbap_cost_instruction');
$db->query("UPDATE ".MAIN_DB_PREFIX."dynamicprices_product_cost SET date_calculation='2026-02-15 00:00:00'");

// Native PriceList selects the correct tier using the full order quantity.
$conf->pricelist = (object) array('enabled'=>1); $conf->modules['pricelist']=1;
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'categorie (rowid INTEGER PRIMARY KEY,entity INTEGER,type INTEGER,label TEXT)');
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'categorie_societe (fk_soc INTEGER,fk_categorie INTEGER)');
check(LmdbAdvancedProjectCompatibility::costProviderAvailable('pricelist'), true, 'Real PriceList contract available');
$price = array('entity'=>1,'fk_product'=>1,'from_qty'=>1,'price'=>50,'cost_price'=>8,'cost_price_source'=>'custom','use_product_cost_price'=>0,'fk_user_creation'=>1);
insertFixture('pricelist', array('rowid'=>1)+$price);
$price['from_qty']=10; $price['cost_price']=9;
insertFixture('pricelist', array('rowid'=>2)+$price);
unset($price['fk_user_creation']);
$history = array('entity'=>1,'fk_pricelist'=>2,'datec'=>'2026-01-01 00:00:00','change_type'=>'create','fk_user'=>1)+$price;
insertFixture('pricelist_log', array('rowid'=>1)+$history);
$choices = (new LmdbAdvancedProjectCostValuation($db))->priceListChoices(1,1,1);
check($choices['pricelist:1:1']['price'], 9, 'Current native PriceList tier cost');
check($choices['pricelist:1:1']['id'], 2, 'Order quantity picks ten-unit tier');
check($choices['pricelist_history:1']['price'], 9, 'Actually stored historical cost offered');
check((new LmdbAdvancedProjectCostValuation($db))->automatic(1,1,1,1,'2026-03-01')['source'], 'pricelist_history', 'PriceList history before DynamicPrices');
$history['datec']='2026-04-01 00:00:00'; $history['cost_price']=null;
insertFixture('pricelist_log', array('rowid'=>2)+$history);
check((new LmdbAdvancedProjectCostValuation($db))->automatic(1,1,1,1,'2026-05-01')['source'], 'dynamicprices', 'Latest absent PriceList cost falls through, not to older history');
$db->query('UPDATE '.MAIN_DB_PREFIX.'pricelist SET from_qty=9 WHERE rowid=2');
check((new LmdbAdvancedProjectCostValuation($db))->priceListChoices(1,1,1,'2026-03-01'), array(), 'Changed tier leaves historical cost unresolved');
$db->query('UPDATE '.MAIN_DB_PREFIX.'pricelist SET from_qty=10 WHERE rowid=2');

// Supplemental evidence is attached to the native validation snapshot.
$conf->stock->enabled=0; $conf->modules['stock']=0;
$service->captureShipment(1,$user);
$snapshot = $db->query('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbap_cost_fallback')->rows;
check(count($snapshot),1,'Supplemental validation snapshot created');
check((float)$snapshot[0]->snapshot_unit_ht,9,'PriceList historical amount frozen');
$conf->pricelist->enabled=0; $conf->dynamicsprices->enabled=0; $conf->modules['pricelist']=$conf->modules['dynamicsprices']=0;
$db->query('UPDATE '.MAIN_DB_PREFIX.'pricelist_log SET cost_price=99');
$report = $service->load(array(1));
$first = array_values(array_filter($report['products']['1:1']['lines'], static function ($line) { return $line['kind']==='shipment' && $line['document_id']===1; }));
check($first[0]['price'],9,'Snapshot retained after provider deactivation and changes');
check($first[0]['price_source'],'BudgetCostSource_pricelist_history','Snapshot provenance retained');
check((new LmdbAdvancedProjectCostValuation($db))->save((new LmdbAdvancedProjectCostValuation($db))->prepare(1,1),'free','15',false,'mixed-snapshots'),1,'Fill other missing shipments of a partly frozen product');
$known=array_values(array_filter($service->load(array(1))['products']['1:1']['lines'],static function($line){return $line['kind']==='shipment' && $line['document_id']===1;}));
check($known[0]['price'],9,'New instruction preserves known supplemental snapshot');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbadvancedproject_supplier_invoice_parts SET qty=12,total_ht=144 WHERE rowid=1');
check($service->load(array(1))['products']['1:1']['provisional_cost'],0,'Full invoice coverage removes provisional balance');
check($service->load(array(1))['products']['1:1']['shipment_cost'],0,'Full invoice coverage reverses all provisional amounts');
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbadvancedproject_supplier_invoice_parts SET qty=4,total_ht=48 WHERE rowid=1');
$conf->stock->enabled=1; $conf->modules['stock']=1;

// Native supplier selection includes net discount and enforces supplier access.
$conf->fournisseur=(object)array('enabled'=>1); $conf->modules['fournisseur']=1;
$db->query('UPDATE '.MAIN_DB_PREFIX.'societe SET status=1');
$db->query('UPDATE '.MAIN_DB_PREFIX."product_fournisseur_price SET ref_fourn='SUP-A',remise_percent=20,remise=1,unitprice=12.5");
$choices = (new LmdbAdvancedProjectCostValuation($db))->choices($service->load(array(1))['products']['1:1']);
check($choices['supplier:1']['price'],9,'Native supplier price normalized net HT');
$user->denied=array('fournisseur.lire');
check(isset((new LmdbAdvancedProjectCostValuation($db))->choices($service->load(array(1))['products']['1:1'])['supplier:1']),false,'Supplier source permission enforced');
$user->denied=array();
echo $checks." assertions passed including actual optional provider read contracts.\n";
