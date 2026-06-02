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
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
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
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once '../lib/lmdbadvancedproject.lib.php';

$langs->loadLangs(array('admin', 'lmdbadvancedproject@lmdbadvancedproject'));

if (!function_exists('lmdbadvancedproject_is_multicompany_enabled')) {
	/**
	 * Check if Dolibarr Multicompany module is enabled.
	 *
	 * @return bool
	 */
	function lmdbadvancedproject_is_multicompany_enabled()
	{
		global $conf;

		if (function_exists('isModEnabled')) {
			return isModEnabled('multicompany');
		}

		return !empty($conf->multicompany->enabled);
	}
}

if (!$user->admin) {
	accessforbidden();
}

$backtopage = GETPOST('backtopage', 'alpha');
$action = GETPOST('action', 'aZ09');
$help_url = '';
$page_name = 'AdvancedProjectSetup';

$switchConstants = array(
	'LMDBADVANCEDPROJECT_ENABLE_SUPPLIER_INVOICE_SPLIT' => 1,
	'LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT' => 1,
	'LMDBADVANCEDPROJECT_WORKFLOW_CLOSE_PROJECT_ON_DELIVERED_ORDERS' => 1,
);
if (lmdbadvancedproject_is_multicompany_enabled()) {
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

if ($switchConstant !== '') {
	$token = GETPOST('token', 'alpha');
	$expectedToken = '';
	if (function_exists('currentToken')) {
		$expectedToken = currentToken();
	} elseif (!empty($_SESSION['newtoken'])) {
		$expectedToken = $_SESSION['newtoken'];
	}

	if (!empty($expectedToken) && $token !== $expectedToken) {
		accessforbidden('Bad value for token');
	}

	$entityIsSet = function_exists('GETPOSTISSET') ? GETPOSTISSET('entity') : (isset($_GET['entity']) || isset($_POST['entity']));
	$entity = $entityIsSet ? GETPOST('entity', 'int') : $conf->entity;
	if ($entity < 0) {
		$entity = $conf->entity;
	}

	$result = dolibarr_set_const($db, $switchConstant, (int) $switchValue, 'chaine', 0, '', $entity);
	if ($result > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	}

	dol_print_error($db);
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
if (lmdbadvancedproject_is_multicompany_enabled()) {
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
print '<tr class="liste_titre">';
print '<td colspan="2">'.$langs->trans('AdvancedProjectWorkflow').'</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td>';
print '<label for="LMDBADVANCEDPROJECT_WORKFLOW_CLOSE_PROJECT_ON_DELIVERED_ORDERS">'.$langs->trans('AdvancedProjectWorkflowCloseProjectOnDeliveredOrders').'</label>';
print '<br><span class="opacitymedium">'.$langs->trans('AdvancedProjectWorkflowCloseProjectOnDeliveredOrdersHelp').'</span>';
print '</td>';
print '<td class="right">';
print ajax_constantonoff('LMDBADVANCEDPROJECT_WORKFLOW_CLOSE_PROJECT_ON_DELIVERED_ORDERS', array(), $conf->entity, 0, 0, 0, 2, 0, 1);
print '</td>';
print '</tr>';
print '</table>';

if (!lmdbadvancedproject_is_multicompany_enabled()) {
	print '<div class="info">'.$langs->trans('AdvancedProjectMulticompanyInactive').'</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
