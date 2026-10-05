<?php
/* Margin permissions and real spreadsheet round trips; no ERP writes. */
require __DIR__.'/projectsummary_test.php';
$user->denied = array();
$user->admin = 1;
$conf->format_date_short = '%d/%m/%Y';
$langs->translations['BudgetReportGrossMargin'] = 'Marge brute';
$langs->translations['BudgetReportGrossMarginRate'] = 'Taux de marge brute';
$sample = lmdbadvancedproject_load_budget_report_data(1, $filters);
foreach (array(array(200, 50, 150, '75%'), array(200, 200, 0, '0%'), array(200, 250, -50, '-25%'), array(0, 50, -50, '-'), array(-200, 50, -250, '-')) as $case) {
	list($orders, $spent, $margin, $rate) = $case;
	$sample['totalorders'] = $orders;
	$sample['totalspent'] = $spent;
	$sample['budgetReportProjectId'] = 1;
	foreach (array(false, true) as $compact) {
		ob_start(); lmdbadvancedproject_render_budget_summary($sample, $compact); $html = (string) ob_get_clean();
		check(substr_count($html, 'budgetreport-summary-cell"'), 6, 'Six authorized tiles');
		check(strpos($html, 'Marge brute') > strpos($html, $langs->trans('BudgetReportLeftToSpend')), true, 'Margin follows remaining budget');
		check(strpos($html, lmdbadvancedproject_format_price($margin)) !== false, true, 'Screen margin amount');
		check(strpos($html, '('.$rate.')') !== false, true, 'Screen signed or undefined percentage');
	}
	$export = new LmdbAdvancedProjectBudgetReportExport($langs, $sample);
	$book = $build->invoke($export, false);
	check($book->getSheet(0)->getCell('F8')->getValue(), $margin, 'Numeric margin KPI');
	check($book->getSheet(0)->getCell('G8')->getValue(), $orders > 0 ? $margin / $orders : '-', 'Numeric ratio or undefined KPI');
	check($book->getSheet(0)->getStyle('G8')->getNumberFormat()->getFormatCode(), '0%', 'Percentage display matches integer screen');
	$book->disconnectWorksheets();
}
// Total margin percentage comes from total amounts, not average project rates.
$sample['budgetReportProjectId'] = 0;
$sample['totalorders'] = 1000;
$sample['totalspent'] = 300;
$sample['projects'] = array(
	1 => array('project_ref'=>'P1','title'=>'One','orders'=>100,'invoiced'=>50,'budget'=>80,'spent'=>50),
	2 => array('project_ref'=>'P2','title'=>'Two','orders'=>900,'invoiced'=>100,'budget'=>500,'spent'=>250),
);
foreach (array(true, false) as $allowed) {
	$user->denied = $allowed ? array() : array('margins.liretous');
	ob_start(); lmdbadvancedproject_render_budget_summary($sample, true); $html = (string) ob_get_clean();
	check(strpos($html, 'Marge brute') !== false, $allowed, 'Admin has no implicit margin right');
	check(substr_count($html, 'budgetreport-summary-cell"'), $allowed ? 6 : 5, 'Denied margin leaves five tiles');
	$export = new LmdbAdvancedProjectBudgetReportExport($langs, $sample);
	$book = $build->invoke($export, false);
	$sheet = $book->getSheet(0);
	check($sheet->getCell('F7')->getValue(), $allowed ? 'Marge brute' : $langs->transnoentities('BudgetReportTimeSpentHours'), 'KPI shifts when margin is denied');
	check($sheet->getCell('G11')->getValue(), $allowed ? 'Marge brute' : $langs->transnoentities('BudgetReportBalance'), 'Global columns shift when denied');
	if ($allowed) {
		check($sheet->getCell('G14')->getValue(), 700, 'Global total margin amount');
		check($sheet->getCell('H14')->getValue(), .7, 'Global total uses weighted ratio');
	}
	foreach (array('Xlsx', 'Ods') as $format) {
		$class = 'PhpOffice\\PhpSpreadsheet\\Writer\\'.$format;
		$file = __DIR__.'/.cache/margin-'.($allowed ? 'allowed' : 'denied').'.'.strtolower($format);
		$writer = new $class($book); $writer->save($file);
		$loaded = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
		if ($allowed) {
			check($loaded->getSheet(0)->getCell('F8')->getValue(), 700, $format.' margin amount read back');
			check($loaded->getSheet(0)->getCell('G8')->getValue(), .7, $format.' margin ratio read back');
		} else {
			foreach ($loaded->getAllSheets() as $loadedSheet) {
				check(strpos(json_encode($loadedSheet->toArray()), 'Marge brute') === false, true, $format.' no margin in any sheet');
				check(strpos(json_encode($loadedSheet->toArray()), 'Taux de marge brute') === false, true, $format.' no ratio in any sheet');
			}
		}
		$loaded->disconnectWorksheets();
	}
	$book->disconnectWorksheets();
}
$user->denied = array();
$conf->format_date_short = '%d/%m/%Y';
// Render the real global page to test its legacy margin columns too.
foreach (array(true, false) as $allowed) {
	$user->denied = $allowed ? array('facture.read') : array('facture.read', 'margins.liretous');
	ob_start(); lmdbadvancedproject_render_budget_report(0, $filters); $html = (string) ob_get_clean();
	check(strpos($html, 'Marge brute') !== false, $allowed, 'Global HTML obeys margin permission');
}
$user->denied = array();
$projectData = lmdbadvancedproject_load_budget_report_data(1, $filters);
$globalData = lmdbadvancedproject_load_budget_report_data(0, $filters);
$projectMargin = (float) price2num($projectData['totalorders'] - $projectData['totalspent'], 'MT');
$globalMargin = (float) price2num($globalData['projects'][1]['orders'] - $globalData['projects'][1]['spent'], 'MT');
check($projectMargin, $globalMargin, 'Project and global use identical retained sources');
$projectExport = new LmdbAdvancedProjectBudgetReportExport($langs, $projectData);
$book = $build->invoke($projectExport, false);
check($book->getSheet(0)->getCell('F8')->getValue(), $projectMargin, 'Project export matches actual global margin');
$book->disconnectWorksheets();
if (getenv('LMDBAP_MARGIN_HTML')) {
	foreach (file(__DIR__.'/../langs/fr_FR/lmdbadvancedproject.lang', FILE_IGNORE_NEW_LINES) as $entry) {
		if (strpos($entry, '=') !== false && substr($entry, 0, 1) !== '#') {
			list($key, $value) = explode('=', $entry, 2); $langs->translations[trim($key)] = trim($value);
		}
	}
	foreach (array('totaltime', 'totalvendinv', 'totalexpenses', 'totalshipmentcost', 'totalTimeHours', 'totalsupplierordersorderedremaining', 'totalsupplierordersdeliveredremaining', 'totalcustomerinvoices') as $key) { $sample[$key] = 0; }
	$sample['totalorders'] = 9000;
	$sample['totalspent'] = 0;
	$sample['budget'] = 4999.20;
	$sample['balance'] = 4999.20;
	$sample['productCosts']['complete'] = false;
	ob_start(); lmdbadvancedproject_render_budget_summary($sample, true); $compactHtml = (string) ob_get_clean();
	ob_start(); lmdbadvancedproject_render_budget_summary($sample, false); $fullHtml = (string) ob_get_clean();
	$user->denied = array('margins.liretous');
	ob_start(); lmdbadvancedproject_render_budget_summary($sample, true); $deniedHtml = (string) ob_get_clean();
	$user->denied = array();
	$style = preg_replace('/<\?php.*?\?>/s', '', file_get_contents(__DIR__.'/../css/budgetreport.css.php'));
	file_put_contents(getenv('LMDBAP_MARGIN_HTML'), '<!doctype html><meta charset="utf-8"><style>body{font:14px Arial;margin:16px}table{border-collapse:collapse}.opacitymedium{opacity:.65}.center{text-align:center}.valignmiddle{vertical-align:middle}</style><style>'.$style.'</style><h2>Fiche projet</h2><div style="max-width:760px">'.$compactHtml.'</div><h2>Rapport</h2>'.$fullHtml.'<h2>Sans droit</h2><div style="max-width:760px">'.$deniedHtml.'</div>');
}
echo $checks." assertions passed including gross margins and permissions.\n";
