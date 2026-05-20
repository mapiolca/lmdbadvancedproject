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

if ($action === 'set_multicompany_scope') {
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

	$value = GETPOST('LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES', 'int') ? 1 : 0;
	$result = dolibarr_set_const($db, 'LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES', $value, 'chaine', 0, '', $conf->entity);
	if ($result > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF']);
		exit;
	} else {
		dol_print_error($db);
	}
}

llxHeader('', $langs->trans($page_name), $help_url);

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans('BackToModuleList').'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = lmdbadvancedprojectAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, 'lmdbadvancedproject@lmdbadvancedproject');

print '<span class="opacitymedium">'.$langs->trans('AdvancedProjectSetupPage').'</span><br><br>';

if (lmdbadvancedproject_is_multicompany_enabled()) {
	$newToken = function_exists('newToken') ? newToken() : (empty($_SESSION['newtoken']) ? '' : $_SESSION['newtoken']);
	$multicompanyAllEntities = empty($conf->global->LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES) ? 0 : 1;

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.$newToken.'">';
	print '<input type="hidden" name="action" value="set_multicompany_scope">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('Parameters').'</td>';
	print '<td class="right">'.$langs->trans('Value').'</td>';
	print '</tr>';
	print '<tr class="oddeven">';
	print '<td>';
	print '<label for="LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES">'.$langs->trans('AdvancedProjectMulticompanyScope').'</label>';
	print '<br><span class="opacitymedium">'.$langs->trans('AdvancedProjectMulticompanyScopeHelp').'</span>';
	print '</td>';
	print '<td class="right">';
	print '<input type="checkbox" class="flat" id="LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES" name="LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES" value="1"'.($multicompanyAllEntities ? ' checked' : '').'> ';
	print '<label for="LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES">'.$langs->trans('AdvancedProjectMulticompanyAllEntities').'</label>';
	print '</td>';
	print '</tr>';
	print '</table>';
	print '<div class="tabsAction">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
	print '</div>';
	print '</form>';
} else {
	print '<div class="info">'.$langs->trans('AdvancedProjectMulticompanyInactive').'</div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
