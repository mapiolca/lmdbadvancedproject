<?php
/* Copyright (C) 2004-2017 Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2022      SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lmdbadvancedproject/admin/setup.php
 * \ingroup lmdbadvancedproject
 * \brief   Advanced Project setup page.
 */

// Load Dolibarr environment
$res = 0;
if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
}
foreach (array('../main.inc.php', '../../main.inc.php', '../../../main.inc.php', '../../../../main.inc.php') as $mainFile) {
	$resolvedMainFile = realpath(__DIR__.'/'.$mainFile);
	if (!$res && $resolvedMainFile !== false) {
		$res = @include $resolvedMainFile;
	}
}
if (!$res) {
	die('Include of main fails');
}

/** @var DoliDB $db */
/** @var User $user */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var HookManager $hookmanager */
global $db, $user, $conf, $langs, $hookmanager;

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once __DIR__.'/../lib/lmdbadvancedproject.lib.php';
require_once __DIR__.'/../class/lmdbadvancedprojectcompatibility.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

$langs->loadLangs(array('admin', 'lmdbadvancedproject@lmdbadvancedproject'));

if (!$user->admin) {
	accessforbidden();
}

$backtopage = GETPOST('backtopage', 'alpha');
$action = GETPOST('action', 'aZ09');
$help_url = '';
$page_name = 'AdvancedProjectSetup';

$switchConstants = array(
	'LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST' => 1,
	'LMDBADVANCEDPROJECT_ENABLE_SUPPLIER_INVOICE_SPLIT' => 1,
	'LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT' => 1,
);
if (isModEnabled('multicompany')) {
	$switchConstants['LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES'] = 1;
}

$switchConstant = '';
$switchValue = null;
foreach ($switchConstants as $constantName => $enabled) {
	if ($action === 'set_'.$constantName) {
		$switchConstant = $constantName;
		$switchValue = 1;
		break;
	}
	if ($action === 'del_'.$constantName) {
		$switchConstant = $constantName;
		$switchValue = 0;
		break;
	}
}

if ($switchConstant !== '' || $action === 'save_shipment_method') {
	$token = GETPOST('token', 'alpha');
	$expectedToken = currentToken();
	if ($expectedToken === '' || !hash_equals($expectedToken, $token)) {
		accessforbidden('Bad value for token');
	}
}
if ($switchConstant !== '') {
	$entity = (int) $conf->entity;

	$result = dolibarr_set_const($db, $switchConstant, (string) $switchValue, 'chaine', 0, '', $entity);
	if ($result > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}

	dol_print_error($db);
}

if ($action === 'save_shipment_method') {
	$method = GETPOST('shipment_method', 'aZ09');
	if (!in_array($method, array('supplier_tariff', 'pmp'), true)) {
		accessforbidden();
	}
	if (dolibarr_set_const($db, 'LMDBADVANCEDPROJECT_SHIPMENT_COST_METHOD', $method, 'chaine', 0, '', (int) $conf->entity) > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}
	setEventMessages($langs->trans('Error'), null, 'errors');
}

llxHeader('', $langs->trans($page_name), $help_url);

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans('BackToModuleList').'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = lmdbadvancedprojectAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, 'lmdbadvancedproject@lmdbadvancedproject');

print '<span class="opacitymedium">'.$langs->trans('AdvancedProjectSetupPage').'</span><br><br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameters').'</td>';
print '<td class="right">'.$langs->trans('Value').'</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>';
print '<label for="LMDBADVANCEDPROJECT_ENABLE_SUPPLIER_INVOICE_SPLIT">'.$langs->trans('AdvancedProjectSupplierInvoiceSplit').'</label>';
print '<br><span class="opacitymedium">'.$langs->trans('AdvancedProjectSupplierInvoiceSplitHelp').'</span>';
print '</td>';
print '<td class="right">';
print ajax_constantonoff('LMDBADVANCEDPROJECT_ENABLE_SUPPLIER_INVOICE_SPLIT', array(), $conf->entity, 0, 0, 0, 2, 0, 1);
print '</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>';
print '<label for="LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT">'.$langs->trans('AdvancedProjectCustomerInvoiceSplit').'</label>';
print '<br><span class="opacitymedium">'.$langs->trans('AdvancedProjectCustomerInvoiceSplitHelp').'</span>';
print '</td>';
print '<td class="right">';
print ajax_constantonoff('LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT', array(), $conf->entity, 0, 0, 0, 2, 0, 1);
print '</td>';
print '</tr>';
if (isModEnabled('multicompany')) {
	print '<tr class="oddeven">';
	print '<td>';
	print '<label for="LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES">'.$langs->trans('AdvancedProjectMulticompanyScope').'</label>';
	print '<br><span class="opacitymedium">'.$langs->trans('AdvancedProjectMulticompanyScopeHelp').'</span>';
	print '</td>';
	print '<td class="right">';
	print ajax_constantonoff('LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES', array(), $conf->entity, 0, 0, 0, 2, 0, 1);
	print '</td>';
	print '</tr>';
}
print '</table>';

$form = new Form($db);
print '<br><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('BudgetCostFeature').'</th><th class="right">'.$langs->trans('Value').'</th></tr>';
print '<tr class="oddeven"><td>'.$form->textwithtooltip($langs->trans('BudgetCostFeature'), $langs->trans('BudgetCostFormulaHelp')).'<br><span class="opacitymedium">'.$langs->trans('BudgetCostFeatureDescription').'</span></td><td class="right">';
if (LmdbAdvancedProjectCompatibility::shipmentCostAvailable()) {
	print ajax_constantonoff('LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST', array(), (int) $conf->entity, 0, 0, 0, 2, 0, 1);
} else {
	print $langs->trans('Unavailable');
}
print '</td></tr><tr class="oddeven"><td>'.$langs->trans('BudgetCostMethod').'</td><td class="right">';
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="save_shipment_method">';
print $form->selectarray('shipment_method', array('supplier_tariff' => $langs->trans('BudgetCostSupplierTariff'), 'pmp' => $langs->trans('BudgetCostPmp')), getDolGlobalString('LMDBADVANCEDPROJECT_SHIPMENT_COST_METHOD', 'supplier_tariff'), 0);
print ajax_combobox('shipment_method');
print ' <input class="button" type="submit" value="'.$langs->trans('Save').'">';
print '</form></td></tr></table>';
print '<div class="info">'.$langs->trans('BudgetCostFormulaHelp').'<br>'.$langs->trans('BudgetCostChronologyHelp').'<br>'.$langs->trans('BudgetCostPriceHelp').'<br>'.$langs->trans('BudgetCostHistoryHelp').'</div>';
if (!LmdbAdvancedProjectCompatibility::shipmentCostAvailable()) {
	print '<div class="warning">'.$langs->trans('BudgetCostUnavailable').'</div>';
}

if (!isModEnabled('multicompany')) {
	print '<div class="info">'.$langs->trans('AdvancedProjectMulticompanyInactive').'</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
