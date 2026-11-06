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
check(count($values),7,'Native settings inserted once');
$db->query("UPDATE ".MAIN_DB_PREFIX."const SET value='' WHERE name='LMDBADVANCEDPROJECT_SHIPMENT_COST_METHOD'");
check($module->delete_const(),0,'Native deactivation constant path');
check($module->insert_const(),0,'Native reactivation constant path');
check($db->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST'")->rows[0]->value,'0','Explicit disabled setting retained');
check($db->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='LMDBADVANCEDPROJECT_SHIPMENT_COST_METHOD'")->rows[0]->value,'','Explicit empty setting retained');
check($db->num_rows($db->query("SELECT * FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'LMDBADVANCEDPROJECT_%'")),7,'No duplicate constants on reactivation');
$db->query("UPDATE ".MAIN_DB_PREFIX."const SET value='either' WHERE name='LMDBADVANCEDPROJECT_AUTO_CLOSE_RULE'");
$db->query("UPDATE ".MAIN_DB_PREFIX."const SET value='45' WHERE name='LMDBADVANCEDPROJECT_AUTO_CLOSE_DAYS'");
$db->query("UPDATE ".MAIN_DB_PREFIX."const SET value='7' WHERE name='LMDBADVANCEDPROJECT_AUTO_CLOSE_USER'");
check($module->delete_const(),0,'Native automatic closure deactivation');
check($module->insert_const(),0,'Native automatic closure reactivation');
foreach (array('RULE'=>'either','DAYS'=>'45','USER'=>'7') as $setting=>$expected) {
	check($db->query("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='LMDBADVANCEDPROJECT_AUTO_CLOSE_".$setting."'")->rows[0]->value, $expected, 'Automatic closure '.$setting.' preserved');
}
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'cronjob (rowid INTEGER PRIMARY KEY, module_name TEXT, datec TEXT, datestart TEXT, dateend TEXT, label TEXT, jobtype TEXT, classesname TEXT, objectname TEXT, methodename TEXT, command TEXT, params TEXT, note TEXT, frequency INTEGER, unitfrequency INTEGER, priority INTEGER, status INTEGER, entity INTEGER, test TEXT)');
check($module->insert_cronjobs(),0,'Native scheduled job registration');
check($module->cronjobs[0]['status'],0,'Automatic closure job initially disabled');
$db->query('UPDATE '.MAIN_DB_PREFIX.'cronjob SET status=1,frequency=5,unitfrequency=86400');
check($module->delete_cronjobs(),0,'Native deactivation preserves conditional jobs');
check($module->insert_cronjobs(),0,'Native reactivation retains existing job');
$jobs=$db->query('SELECT * FROM '.MAIN_DB_PREFIX.'cronjob')->rows;
check(count($jobs),1,'No duplicate scheduled job');
check((int)$jobs[0]->frequency,5,'Administrator frequency preserved');
check((int)$jobs[0]->unitfrequency,86400,'Administrator frequency unit preserved');
check((int)$jobs[0]->status,1,'Administrator activation preserved');
check((int)$jobs[0]->entity,1,'Job registered in owner entity');
echo $checks." assertions passed including native constant preservation.\n";
