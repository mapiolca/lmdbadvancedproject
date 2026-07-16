<?php
/* Copyright (C) 2022 SuperAdmin
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
 * \file       lmdbadvancedproject/tabs/project_budgetreport
 * \ingroup    lmdbadvancedproject
 * \brief      Project budget report tab.
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
if (!$res && file_exists('../../../../main.inc.php')) {
	$res = @include '../../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once dirname(__DIR__).'/lib/budgetreport.lib.php';

$langs->loadLangs(array('projects', 'lmdbadvancedproject@lmdbadvancedproject'));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

if (!isModEnabled('lmdbadvancedproject') || !$user->hasRight('projet', 'lire') || !$user->hasRight('lmdbadvancedproject', 'budgetreport', 'read')) {
	accessforbidden();
}

$object = new Project($db);
if ($id > 0 || !empty($ref)) {
	$result = $object->fetch($id, $ref);
	if ($result <= 0) {
		accessforbidden();
	}
	$object->fetch_thirdparty();
	$id = $object->id;
} else {
	accessforbidden();
}

restrictedArea($user, 'projet', $object->id, 'projet&project');

$projectWriteAccess = $object->restrictedProjectArea($user, 'write') > 0;
$permissionToGenerate = $user->hasRight('projet', 'creer') && $projectWriteAccess;

if ($action === 'generate_budgetreport') {
	if (!$permissionToGenerate) {
		accessforbidden();
	}
	$result = $object->generateDocument('budgetreport', $langs);
	if ($result <= 0) {
		setEventMessages($object->error, $object->errors, 'errors');
	} else {
		$relativeFile = dol_sanitizeFileName($object->ref).'/'.dol_sanitizeFileName($object->ref).'_budgetreport.pdf';
		header('Location: '.DOL_URL_ROOT.'/document.php?modulepart=project&entity='.(int) $object->entity.'&file='.urlencode($relativeFile));
		exit;
	}
}

$title = $langs->trans('BudgetReportProjectTab').' - '.$object->ref.' '.$object->title;
llxHeader('', $title);

$head = project_prepare_head($object);
print dol_get_fiche_head($head, 'budgetreport', $langs->trans('Project'), -1, ($object->public ? 'projectpub' : 'project'));

if (!empty($_SESSION['pageforbacktolist']) && !empty($_SESSION['pageforbacktolist']['project'])) {
	$tmpurl = $_SESSION['pageforbacktolist']['project'];
	$tmpurl = preg_replace('/__SOCID__/', (string) $object->socid, $tmpurl);
	$linkback = '<a href="'.$tmpurl.(preg_match('/\?/', $tmpurl) ? '&' : '?').'restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
} else {
	$linkback = '<a href="'.DOL_URL_ROOT.'/projet/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
}

$morehtmlref = '<div class="refidno">';
$morehtmlref .= $object->title;
if (!empty($object->thirdparty->id)) {
	$morehtmlref .= '<br>'.$object->thirdparty->getNomUrl(1, 'project');
}
$morehtmlref .= '</div>';

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

if ($permissionToGenerate) {
	print '<div class="tabsAction">';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.(int) $object->id.'" class="inline-block">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="generate_budgetreport">';
	print '<button class="butAction" type="submit">'.$langs->trans('BudgetReportGeneratePdf').'</button>';
	print '</form>';
	print '</div>';
}

print '<div class="fichecenter">';
lmdbadvancedproject_render_project_budget_report((int) $object->id);
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
