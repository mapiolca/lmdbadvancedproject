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
if (!$res && file_exists('../main.inc.php')) {
	$res = @include '../main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once __DIR__.'/lib/budgetreport.lib.php';

$langs->loadLangs(array('projects', 'lmdbadvancedproject@lmdbadvancedproject'));

if (!isModEnabled('lmdbadvancedproject')) {
	accessforbidden();
}
if (!$user->hasRight('projet', 'lire') || !$user->hasRight('lmdbadvancedproject', 'budgetreport', 'read')) {
	accessforbidden();
}

$format = strtolower(GETPOST('format', 'alpha'));
$projectId = GETPOSTINT('project_id');
if (!in_array($format, array('ods', 'xlsx'), true)) {
	accessforbidden();
}
if (!class_exists('ZipArchive')) {
	setEventMessages($langs->trans('BudgetReportExportZipMissing'), null, 'errors');
	header('Location: '.($projectId > 0 ? dol_buildpath('/lmdbadvancedproject/tabs/project_budgetreport.php?id='.$projectId, 1) : dol_buildpath('/lmdbadvancedproject/budgetreportindex.php', 1)));
	exit;
}

$autoloadPath = DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
if (!is_readable($autoloadPath)) {
	setEventMessages($langs->trans('BudgetReportExportLibraryMissing'), null, 'errors');
	header('Location: '.($projectId > 0 ? dol_buildpath('/lmdbadvancedproject/tabs/project_budgetreport.php?id='.$projectId, 1) : dol_buildpath('/lmdbadvancedproject/budgetreportindex.php', 1)));
	exit;
}

$project = null;
if ($projectId > 0) {
	$project = new Project($db);
	$result = $project->fetch($projectId);
	if ($result <= 0 || $project->restrictedProjectArea($user, 'read') <= 0) {
		accessforbidden();
	}
	$project->fetch_thirdparty();
}

$filters = lmdbadvancedproject_normalize_budget_report_filters(array(
	'date_start' => lmdbadvancedproject_get_budget_report_request_date('date_start'),
	'date_end' => lmdbadvancedproject_get_budget_report_request_date('date_end'),
	'ignore_started_before' => GETPOST('ignore_started_before', 'alpha'),
	'ignore_ended_after' => GETPOST('ignore_ended_after', 'alpha'),
	'exclude_content_outside_period' => GETPOST('exclude_content_outside_period', 'alpha'),
	'project_status' => GETPOST('project_status', 'alpha'),
));
$data = lmdbadvancedproject_load_budget_report_data($projectId, $filters);

require_once __DIR__.'/class/budgetreportexport.class.php';

$basename = $projectId > 0 && is_object($project)
	? 'budget-report-'.dol_sanitizeFileName($project->ref)
	: 'budget-report-'.dol_print_date(dol_now(), '%Y%m%d');
$mime = $format === 'xlsx'
	? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
	: 'application/vnd.oasis.opendocument.spreadsheet';

header('Content-Type: '.$mime);
header('Content-Disposition: attachment; filename="'.$basename.'.'.$format.'"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

$export = new LmdbAdvancedProjectBudgetReportExport($langs, $data, $project);
$export->output($format);

$db->close();
