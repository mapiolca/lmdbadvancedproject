<?php
// Exercise native persistence of the descriptor's constants, without module deployment.
require __DIR__.'/costsources_test.php';
require_once __DIR__.'/../core/modules/modLmdbAdvancedProject.class.php';
foreach (array('type','note','visible') as $column) { $db->query('ALTER TABLE '.MAIN_DB_PREFIX.'const ADD COLUMN '.$column.' TEXT'); }
$module=new modLmdbAdvancedProject($db);
check($module->numero,450021,'Existing module identifier retained');
check($module->config_page_url, array('setup.php@lmdbadvancedproject'), 'Single native settings entry');
check(implode('.', $module->phpmin), LmdbAdvancedProjectCompatibility::MIN_PHP_VERSION, 'Descriptor and compatibility share PHP minimum');
check(implode('.', $module->need_dolibarr_version), LmdbAdvancedProjectCompatibility::MIN_DOLIBARR_VERSION, 'Descriptor and compatibility share Dolibarr minimum');
check($module->license, 'GPL-3.0-or-later', 'About license is provided by descriptor');
$features = LmdbAdvancedProjectCompatibility::getFeatures();
check($features['shipment_cost']['available'], LmdbAdvancedProjectCompatibility::shipmentCostAvailable(), 'Compatibility uses live shipment predicate');
check($features['cost_valuation']['available'], LmdbAdvancedProjectCompatibility::costValuationAvailable(), 'Compatibility uses live valuation predicate');
check($features['dynamicprices_cost']['available'], false, 'Optional provider absent is unavailable');
check($features['pricelist_cost']['available'], false, 'Optional PriceList absent is unavailable');
$conf->global->LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST=0;
check(LmdbAdvancedProjectCompatibility::getFeatures()['cost_valuation']['available'], false, 'Disabled valuation is unavailable in compatibility');
$conf->global->LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST=1;
check($module->insert_const(),0,'Native constant initialization');
$values=$db->query("SELECT name,value FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'LMDBADVANCEDPROJECT_%'")->rows;
check(count($values),4,'Native settings inserted once');
$db->query("UPDATE ".MAIN_DB_PREFIX."const SET value='' WHERE name='LMDBADVANCEDPROJECT_SHIPMENT_COST_METHOD'");
check($module->delete_const(),0,'Native deactivation constant path');
check($module->insert_const(),0,'Native reactivation constant path');
check($db->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST'")->rows[0]->value,'0','Explicit disabled setting retained');
check($db->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='LMDBADVANCEDPROJECT_SHIPMENT_COST_METHOD'")->rows[0]->value,'','Explicit empty setting retained');
check($db->num_rows($db->query("SELECT * FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'LMDBADVANCEDPROJECT_%'")),4,'No duplicate constants on reactivation');
echo $checks." assertions passed including native constant preservation.\n";
