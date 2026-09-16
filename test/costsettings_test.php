<?php
// Exercise native persistence of the descriptor's constants, without module deployment.
require __DIR__.'/costsources_test.php';
require_once __DIR__.'/../core/modules/modLmdbAdvancedProject.class.php';
foreach (array('type','note','visible') as $column) { $db->query('ALTER TABLE '.MAIN_DB_PREFIX.'const ADD COLUMN '.$column.' TEXT'); }
$module=new modLmdbAdvancedProject($db);
check($module->numero,450021,'Existing module identifier retained');
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
