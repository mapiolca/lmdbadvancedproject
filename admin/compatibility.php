<?php

// Load Dolibarr environment.
$res = 0;
if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
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
foreach (array('../../main.inc.php', '../../../main.inc.php') as $mainFile) {
	$resolvedMainFile = realpath(__DIR__.'/'.$mainFile);
	if (!$res && $resolvedMainFile !== false) {
		$res = @include $resolvedMainFile;
	}
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
/** @var DoliDB $db */
/** @var User $user */
/** @var Translate $langs */
global $db, $user, $langs;
require_once __DIR__.'/../lib/lmdbadvancedproject.lib.php';
require_once __DIR__.'/../class/lmdbadvancedprojectcompatibility.class.php';
require_once __DIR__.'/../core/modules/modLmdbAdvancedProject.class.php';

$langs->loadLangs(array('admin', 'lmdbadvancedproject@lmdbadvancedproject'));
if (!$user->admin || !isModEnabled('lmdbadvancedproject')) {
	accessforbidden();
}

$pageName = 'Compatibility';
$moduleDescriptor = new modLmdbAdvancedProject($db);
$features = LmdbAdvancedProjectCompatibility::getFeatures();

llxHeader('', $langs->trans($pageName));
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=lmdbadvancedproject">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('AdvancedProjectSetup'), $linkback, 'title_setup');
$head = lmdbadvancedprojectAdminPrepareHead();
print dol_get_fiche_head($head, 'compatibility', $langs->trans('AdvancedProjectSetup'), -1, 'lmdbadvancedproject@lmdbadvancedproject');
print '<div class="underbanner opacitymedium">'.$langs->trans('BudgetCompatibilityHelp').'</div><br>';

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('BudgetReportDetectedDolibarrVersion').'</td><td>'.dol_escape_htmltag(DOL_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('BudgetReportDetectedPhpVersion').'</td><td>'.dol_escape_htmltag(PHP_VERSION).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('BudgetReportMinimumDolibarrVersion').'</td><td>'.dol_escape_htmltag(implode('.', $moduleDescriptor->need_dolibarr_version)).'</td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans('BudgetReportMinimumPhpVersion').'</td><td>'.dol_escape_htmltag(implode('.', $moduleDescriptor->phpmin)).'</td></tr>';
print '</table></div>';

print '<br><div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Feature').'</th><th>'.$langs->trans('Description').'</th><th>'.$langs->trans('Status').'</th><th>'.$langs->trans('BudgetCompatibilityThreshold').'</th><th>'.$langs->trans('BudgetCompatibilityNativeAvailability').'</th><th>'.$langs->trans('MoreInformation').'</th></tr>';
foreach ($features as $feature) {
	$available = !empty($feature['available']);
	$details = array();
	foreach ($feature['details'] as $detailLabel => $detailAvailable) {
		$details[] = $langs->trans($detailLabel).': '.$langs->trans($detailAvailable ? 'Available' : 'NotAvailable');
	}
	if (!$available && !empty($feature['reason'])) {
		$details[] = $langs->trans($feature['reason']);
	}
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($feature['label']).'</td>';
	print '<td>'.$langs->trans($feature['description']).'</td>';
	$statusLabel = $langs->trans($available ? 'Available' : 'NotAvailable');
	print '<td>'.dolGetStatus($statusLabel, $statusLabel, '', $available ? 'status4' : 'status8', 5).'</td>';
	print '<td>'.$langs->trans('BudgetCompatibilityThresholdValue', dol_escape_htmltag($feature['module_available_from']), dol_escape_htmltag($feature['min_php'])).'</td>';
	print '<td>'.$langs->trans($feature['core_contract']).'</td>';
	print '<td>'.implode('<br>', $details).'</td>';
	print '</tr>';
}
print '</table></div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
