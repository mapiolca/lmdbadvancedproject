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
 * \file    lmdbadvancedproject/admin/about.php
 * \ingroup lmdbadvancedproject
 * \brief   About page of module Advanced Project.
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
require_once __DIR__.'/../core/modules/modLmdbAdvancedProject.class.php';

$langs->loadLangs(array('admin', 'install', 'products', 'lmdbadvancedproject@lmdbadvancedproject'));

if (!$user->admin || !isModEnabled('lmdbadvancedproject')) {
	accessforbidden();
}

$moduleDescriptor = new modLmdbAdvancedProject($db);
llxHeader('', $langs->trans('AdvancedProjectAbout'));
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?search_keyword=lmdbadvancedproject">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('AdvancedProjectSetup'), $linkback, 'title_setup');
$head = lmdbadvancedprojectAdminPrepareHead();
print dol_get_fiche_head($head, 'about', $langs->trans('AdvancedProjectSetup'), -1, 'lmdbadvancedproject@lmdbadvancedproject');
print '<div class="underbanner opacitymedium">'.$langs->trans('AdvancedProjectAbout').'</div><br>';

// Same native two-column layout as Diffusion; every identifying value comes from the descriptor.
print '<div class="fichecenter"><div class="fichehalfleft"><div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent"><tr class="liste_titre"><th colspan="2">'.$langs->trans('Module').'</th></tr>';
$dependencies = array();
foreach ($moduleDescriptor->depends as $scope => $dependency) {
	// Native descriptors can specify alternatives, and dependencies by country.
	$dependencyLabel = is_array($dependency) ? implode(' / ', $dependency) : $dependency;
	$dependencies[] = (is_string($scope) ? $scope.': ' : '').$dependencyLabel;
}
$metadata = array(
	'Module' => $moduleDescriptor->getName(), 'Version' => $moduleDescriptor->version,
	'Family' => $moduleDescriptor->family, 'Description' => $langs->transnoentities($moduleDescriptor->description),
	'Publisher' => $moduleDescriptor->editor_name, 'License' => $moduleDescriptor->license,
	'BudgetReportMinimumDolibarrVersion' => implode('.', $moduleDescriptor->need_dolibarr_version),
	'BudgetReportMinimumPhpVersion' => implode('.', $moduleDescriptor->phpmin),
	'DependsOn' => $dependencies ? implode(', ', $dependencies) : $langs->transnoentities('None'),
	'RequiredBy' => $moduleDescriptor->requiredby ? implode(', ', $moduleDescriptor->requiredby) : $langs->transnoentities('None'),
);
foreach ($metadata as $label => $value) {
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans($label).'</td><td>'.dol_escape_htmltag($value).'</td></tr>';
}
print '<tr class="oddeven"><td>'.$langs->trans('BudgetAboutOptionalModules').'</td><td>'.$langs->trans('BudgetAboutOptionalModulesHelp').'</td></tr>';
print '</table></div></div>';

print '<div class="fichehalfright"><div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent"><tr class="liste_titre"><th colspan="2">'.$langs->trans('Links').'</th></tr>';
$links = array(
	'BudgetAboutDocumentation' => dol_buildpath('/lmdbadvancedproject/README.md', 1),
	'BudgetAboutValuationGuide' => dol_buildpath('/lmdbadvancedproject/doc/COST_VALUATION.md', 1),
	'BudgetAboutChangeLog' => dol_buildpath('/lmdbadvancedproject/ChangeLog.md', 1),
	'BudgetAboutSource' => $moduleDescriptor->source_url,
	'WebSite' => $moduleDescriptor->editor_url,
);
foreach ($links as $label => $url) {
	print '<tr class="oddeven"><td class="titlefield">'.$langs->trans($label).'</td><td><a href="'.dol_escape_htmltag($url).'" target="_blank" rel="noopener noreferrer">'.$langs->trans('Link').'</a></td></tr>';
}
print '<tr class="oddeven"><td>'.$langs->trans('BudgetAboutSupport').'</td><td><a href="mailto:'.dol_escape_htmltag($moduleDescriptor->editor_email).'">'.dol_escape_htmltag($moduleDescriptor->editor_email).'</a></td></tr>';
print '</table></div></div></div><div class="clearboth"></div><br>';

print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Features').'</th></tr>';
foreach (array('BudgetAboutFeatureReports', 'BudgetAboutFeatureProjectSummary', 'BudgetAboutFeatureAllocations', 'BudgetAboutFeatureCosts', 'BudgetAboutFeatureExports') as $feature) {
	print '<tr class="oddeven"><td>'.$langs->trans($feature).'</td></tr>';
}
print '</table></div>';

print dol_get_fiche_end();
llxFooter();
$db->close();
