<?php

// Load Dolibarr environment.
$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i], $tmp2[$j]) && $tmp[$i] === $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, $i + 1).'/main.inc.php')) {
	$res = @include substr($tmp, 0, $i + 1).'/main.inc.php';
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
require_once '../class/lmdbadvancedprojectcompatibility.class.php';

$langs->loadLangs(array('admin', 'lmdbadvancedproject@lmdbadvancedproject'));
if (!$user->admin) {
	accessforbidden();
}

$backtopage = GETPOST('backtopage', 'alpha');
$pageName = 'Compatibility';
$features = LmdbAdvancedProjectCompatibility::getFeatures();

llxHeader('', $langs->trans($pageName));
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?search_keyword=lmdbadvancedproject').'">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans($pageName), $linkback, 'title_setup');
$head = lmdbadvancedprojectAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $langs->trans($pageName), 0, 'lmdbadvancedproject@lmdbadvancedproject');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('BudgetReportDetectedDolibarrVersion').'</td><td>'.dol_escape_htmltag(DOL_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('BudgetReportDetectedPhpVersion').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('BudgetReportMinimumDolibarrVersion').'</td><td>20.0</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('BudgetReportMinimumPhpVersion').'</td><td>8.0</td></tr>';
print '</table>';

print '<br><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Feature').'</th><th>'.$langs->trans('Description').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('MinimumVersion').'</th><th>'.$langs->trans('Details').'</th></tr>';
foreach ($features as $feature) {
	$available = !empty($feature['available']);
	$details = array();
	foreach ($feature['details'] as $detailLabel => $detailAvailable) {
		$details[] = dol_escape_htmltag($detailLabel).': '.$langs->trans($detailAvailable ? 'Available' : 'Unavailable');
	}
	if (!$available && !empty($feature['reason'])) {
		$details[] = $langs->trans($feature['reason']);
	}
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($feature['label']).'</td>';
	print '<td>'.$langs->trans($feature['description']).'</td>';
	print '<td>'.($available ? '<span class="badge badge-status4">'.$langs->trans('Available').'</span>' : '<span class="badge badge-status8">'.$langs->trans('Unavailable').'</span>').'</td>';
	print '<td>Dolibarr '.dol_escape_htmltag($feature['min_dolibarr']).' / PHP '.dol_escape_htmltag($feature['min_php']).'</td>';
	print '<td>'.implode('<br>', $details).'</td>';
	print '</tr>';
}
print '</table>';

print dol_get_fiche_end();
llxFooter();
$db->close();
