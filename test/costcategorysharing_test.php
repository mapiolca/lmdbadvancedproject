<?php
// Execute the real Multicompany getEntity() contract with isolated sharing configuration.
require __DIR__.'/costcategories_test.php';
$multicompanyRoot = getenv('MULTICOMPANY_ROOT') ?: dirname(__DIR__, 2).'/multicompany';
$conf->file = (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT, 'alt0' => dirname($multicompanyRoot)));
require_once $multicompanyRoot.'/class/actions_multicompany.class.php';
require_once __DIR__.'/../core/modules/modLmdbAdvancedProject.class.php';
$mc = new ActionsMulticompany($db);
$mc->entities = array_fill_keys(array_keys($mc->sharingelements), '1,2');
$mc->entities['product'] = '2';
$mc->customdicts = array('c_commercial_category' => array('type' => 'dictionary'));
$conf->entity = 2;
$conf->modules['multicompany'] = 1;
$conf->global->MULTICOMPANY_C_COMMERCIAL_CATEGORY_CUSTOM_ENABLED = 0;
$db->query('UPDATE '.MAIN_DB_PREFIX.'product SET entity=2');

// Default native dictionaries belong to entity 1, even without sharing its products.
check((string) getEntity('c_commercial_category'), '1', 'Native default dictionary is in the master entity');
$master = lmdbadvancedproject_load_budget_report_data(1, $filters);
check($master['budgetReportForecast']['categories']['cat_1']['order_budget'], 150, 'Master category follows a product in entity 2');
check($master['totalspent'], 126, 'Master dictionary access does not alter costs');
$descriptor = new modLmdbAdvancedProject($db);
$dictionaryRows = $db->query($descriptor->dictionaries['tabsql'][0])->rows;
check(count($dictionaryRows), 4, 'Administration exposes the master and shared product dictionaries');

// A shared product also retains its own dictionary category outside the master entity.
insertFixture('c_commercial_category', array('rowid'=>6, 'entity'=>3, 'code'=>'SHARED_PRODUCT', 'label'=>'Produit partagé', 'active'=>1));
$mc->entities['product'] = '2,3';
$db->query('UPDATE '.MAIN_DB_PREFIX.'product SET entity=3');
$db->query('UPDATE '.MAIN_DB_PREFIX."product_extrafields SET lmdb_commercial_category='6'");
$sharedProduct = lmdbadvancedproject_load_budget_report_data(1, $filters);
check($sharedProduct['budgetReportForecast']['categories']['cat_6']['order_budget'], 150, 'Shared product retains its category from entity 3');
check($sharedProduct['budgetReportForecast']['categories']['cat_6']['supplier_expenses'], 126, 'Shipment reconciliation retains the shared product category');

// Native customization overrides product sharing and restricts the dictionary to entity 2.
$conf->global->MULTICOMPANY_C_COMMERCIAL_CATEGORY_CUSTOM_ENABLED = 1;
$mc->dict['c_commercial_category'] = true;
check((string) getEntity('c_commercial_category'), '2', 'Native customized dictionary uses the consultation entity');
$separated = lmdbadvancedproject_load_budget_report_data(1, $filters);
check(array_keys($separated['budgetReportForecast']['categories']), array('uncategorized'), 'Dictionary separation rejects foreign product and line categories');
check($separated['totalspent'], 126, 'Dictionary separation does not change financial amounts');
$descriptor = new modLmdbAdvancedProject($db);
$dictionaryRows = $db->query($descriptor->dictionaries['tabsql'][0])->rows;
check(count($dictionaryRows), 2, 'Administration obeys dictionary separation');
foreach ($dictionaryRows as $row) { check((int) $row->entity, 2, 'No foreign dictionary row in separated administration'); }
$db->query('UPDATE '.MAIN_DB_PREFIX."product_extrafields SET lmdb_commercial_category='3'");
$localCategory = lmdbadvancedproject_load_budget_report_data(1, $filters);
check($localCategory['budgetReportForecast']['categories']['cat_3']['order_budget'], 150, 'A shared product can use the authorized local category');

// Switching customization off restores the original shared classification without data writes.
$conf->global->MULTICOMPANY_C_COMMERCIAL_CATEGORY_CUSTOM_ENABLED = 0;
$mc->dict = array();
$db->query('UPDATE '.MAIN_DB_PREFIX."product_extrafields SET lmdb_commercial_category='6'");
$restored = lmdbadvancedproject_load_budget_report_data(1, $filters);
check($restored['budgetReportForecast']['categories']['cat_6']['supplier_expenses'], 126, 'Disabling separation restores the shared category');
echo $checks." assertions passed including the native Multicompany dictionary contract.\n";
