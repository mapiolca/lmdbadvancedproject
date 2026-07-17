<?php

require_once DOL_DOCUMENT_ROOT.'/core/modules/project/modules_project.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/lmdbadvancedproject/lib/budgetreport.lib.php');

/**
 * Native project PDF model for the complete budget report.
 */
class pdf_budgetreport extends ModelePDFProjects
{
	/** @var DoliDB */
	public $db;

	/** @var string */
	public $name;

	/** @var string */
	public $description;

	/** @var int */
	public $update_main_doc_field = 0;

	/** @var string */
	public $type = 'pdf';

	/** @var string */
	public $version = 'dolibarr';

	/** @var Societe */
	public $emetteur;

	/** @var float */
	private $footerHeight;

	/**
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $mysoc;

		$langs->loadLangs(array('main', 'projects', 'companies', 'lmdbadvancedproject@lmdbadvancedproject'));
		$this->db = $db;
		$this->name = 'budgetreport';
		$this->description = $langs->trans('BudgetReportPdfModelDescription');
		$format = array('width' => 210, 'height' => 297);
		$this->page_largeur = max($format['width'], $format['height']);
		$this->page_hauteur = min($format['width'], $format['height']);
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->emetteur = $mysoc;
		$this->footerHeight = $this->marge_basse + 14 + max(5, getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5));
		if (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS')) {
			$this->footerHeight += 8;
		}
	}

	/**
	 * Generate the project budget report.
	 *
	 * @param Project   $object Project
	 * @param Translate $outputlangs Output language
	 * @param string $srctemplatepath Source template path
	 * @param int    $hidedetails Hide details
	 * @param int    $hidedesc Hide descriptions
	 * @param int    $hideref Hide references
	 * @return int 1 on success, <= 0 on error
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		global $action, $conf, $hookmanager, $langs, $user;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(array('main', 'dict', 'companies', 'projects', 'lmdbadvancedproject@lmdbadvancedproject'));
		if (!isset($object->thirdparty) || !is_object($object->thirdparty)) {
			$object->fetch_thirdparty();
		}
		if (empty($conf->project->multidir_output[$object->entity])) {
			$this->error = $langs->transnoentities('ErrorConstantNotDefined', 'PROJECT_OUTPUTDIR');
			return -1;
		}

		$objectref = dol_sanitizeFileName($object->ref);
		$dir = $conf->project->multidir_output[$object->entity].'/'.$objectref;
		$file = $dir.'/'.$objectref.'_budgetreport.pdf';
		if (dol_mkdir($dir) < 0) {
			$this->error = $langs->transnoentities('ErrorCanNotCreateDir', $dir);
			return -1;
		}

		if (!is_object($hookmanager)) {
			require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
			$hookmanager = new HookManager($this->db);
		}
		$hookmanager->initHooks(array('pdfgeneration'));
		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return -1;
		}

		$data = lmdbadvancedproject_load_budget_report_data((int) $object->id);
		$pdf = pdf_getInstance($this->format);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetAutoPageBreak(true, $this->footerHeight);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
		$pdf->SetTitle($outputlangs->convToOutputCharset($outputlangs->transnoentities('BudgetReportArea').' '.$object->ref));
		$pdf->SetSubject($outputlangs->transnoentities('BudgetReportPdfModelDescription'));
		$pdf->SetCreator('Dolibarr '.DOL_VERSION);
		$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
		if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
			$pdf->SetCompression(false);
		}

		$this->addPage($pdf, $object, $outputlangs, false);
		$this->drawSummary($pdf, $data, $outputlangs);
		$this->addPage($pdf, $object, $outputlangs, true);
		$this->drawCategorySummary($pdf, $object, $data, $outputlangs);
		$this->drawTimeMatrix($pdf, $object, $data, $outputlangs);

		$this->_pagefoot($pdf, $object, $outputlangs, 0);
		if (method_exists($pdf, 'AliasNbPages')) {
			$pdf->AliasNbPages();
		}
		$pdf->Close();
		$pdf->Output($file, 'F');
		dolChmod($file);

		$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
		$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);
		if ($reshook < 0) {
			$this->error = $hookmanager->error;
			$this->errors = $hookmanager->errors;
			return -1;
		}

		$this->result = array('fullpath' => $file);
		return 1;
	}

	/** @param TCPDF $pdf @param Project $object @param Translate $outputlangs @param bool $finishPrevious @return void */
	private function addPage($pdf, $object, $outputlangs, $finishPrevious)
	{
		if ($finishPrevious && $pdf->getNumPages() > 0) {
			$this->_pagefoot($pdf, $object, $outputlangs, 1);
		}
		$pdf->AddPage('L', $this->format);
		$pdf->setPageOrientation('L', true, $this->footerHeight);
		$pdf->SetFillColor(255, 255, 255);
		$pdf->Rect(0, 0, $this->page_largeur, $this->page_hauteur, 'F');
		$pdf->setPageMark();
		$this->_pagehead($pdf, $object, $outputlangs);
		$pdf->SetY(34);
	}

	/** @param TCPDF $pdf @param array<string,mixed> $data @param Translate $outputlangs @return void */
	private function drawSummary($pdf, $data, $outputlangs)
	{
		$left = $this->marge_gauche;
		$usable = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
		$labels = array('BudgetReportMarket', 'BudgetReportInvoiced', 'BudgetReportBudget', 'BudgetReportSpent', 'BudgetReportLeftToSpend');
		$values = array($data['totalorders'], $data['totalcustomerinvoices'], $data['budget'], $data['totalspent'], $data['balance']);
		$tileWidth = $usable / count($labels);
		$pdf->SetFont('', '', 7);
		foreach ($labels as $index => $label) {
			$x = $left + ($index * $tileWidth);
			$pdf->SetFillColor(238, 244, 250);
			$pdf->SetDrawColor(190, 205, 220);
			$pdf->Rect($x, 36, $tileWidth - 2, 17, 'DF');
			$pdf->SetXY($x + 2, 38);
			$pdf->SetFont('', 'B', 7);
			$pdf->MultiCell($tileWidth - 6, 3, $outputlangs->convToOutputCharset($outputlangs->transnoentities($label)), 0, 'C');
			$pdf->SetXY($x + 2, 45);
			$pdf->SetFont('', '', 9);
			$pdf->MultiCell($tileWidth - 6, 4, $outputlangs->convToOutputCharset(lmdbadvancedproject_format_price($values[$index], $outputlangs)), 0, 'C');
		}
		$pdf->SetFont('', '', 7);
		$pdf->SetXY($left, 55);
		$pdf->MultiCell($usable, 4, $outputlangs->convToOutputCharset(
			$outputlangs->transnoentities('BudgetReportTimeSpentTotal').': '.lmdbadvancedproject_format_price($data['totaltime'], $outputlangs).' · '.lmdbadvancedproject_format_hours($data['totalTimeHours'], true, $outputlangs)
		), 0, 'L');

		$this->drawPie($pdf, 12, 64, 82, 54, $data['labels'], $data['budgets'], $outputlangs->transnoentities($data['budgetChartTitleKey']), $outputlangs);
		$this->drawPie($pdf, 106, 64, 82, 54, $data['spentLabels'], $data['spentValues'], $outputlangs->transnoentities('BudgetReportBudgetVsSpent'), $outputlangs);
		$this->drawMonthlyChart($pdf, 198, 64, 88, 54, $data['monthAxis'], $outputlangs);
	}

	/** @param TCPDF $pdf @param float $x @param float $y @param float $w @param float $h @param array<int,string> $labels @param array<int,float> $values @param string $title @param Translate $outputlangs @return void */
	private function drawPie($pdf, $x, $y, $w, $h, $labels, $values, $title, $outputlangs)
	{
		$pdf->SetFont('', 'B', 8);
		$pdf->SetXY($x, $y);
		$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($title), 0, 'C');
		$total = array_sum($values);
		if ($total <= 0) {
			$pdf->SetFont('', '', 7);
			$pdf->SetXY($x, $y + 20);
			$pdf->MultiCell($w, 4, $outputlangs->transnoentities('NoRecordFound'), 0, 'C');
			return;
		}
		$colors = array(array(79, 129, 189), array(192, 80, 77), array(155, 187, 89), array(128, 100, 162), array(247, 150, 70), array(75, 172, 198), array(166, 166, 166), array(146, 208, 80));
		$cx = $x + 24;
		$cy = $y + 29;
		$radius = 17;
		$start = 0.0;
		foreach ($values as $index => $value) {
			if ($value <= 0) {
				continue;
			}
			$end = $start + ((float) $value / $total * 360);
			$color = $colors[$index % count($colors)];
			$pdf->SetFillColor($color[0], $color[1], $color[2]);
			$pdf->PieSector($cx, $cy, $radius, $start, $end, 'FD', false, 0, 2);
			$start = $end;
		}
		$pdf->SetFont('', '', 5.5);
		$legendY = $y + 8;
		foreach ($labels as $index => $label) {
			if ($legendY > $y + $h - 4) {
				break;
			}
			$color = $colors[$index % count($colors)];
			$pdf->SetFillColor($color[0], $color[1], $color[2]);
			$pdf->Rect($x + 46, $legendY, 2.5, 2.5, 'F');
			$pdf->SetXY($x + 50, $legendY - 0.5);
			$pdf->MultiCell($w - 50, 3, $outputlangs->convToOutputCharset(dol_trunc($label, 32)), 0, 'L');
			$legendY += 4.5;
		}
	}

	/** @param TCPDF $pdf @param float $x @param float $y @param float $w @param float $h @param array<string,mixed> $monthAxis @param Translate $outputlangs @return void */
	private function drawMonthlyChart($pdf, $x, $y, $w, $h, $monthAxis, $outputlangs)
	{
		$pdf->SetFont('', 'B', 8);
		$pdf->SetXY($x, $y);
		$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentities('BudgetReportBudgetVsSpentByMonth')), 0, 'C');
		if (empty($monthAxis)) {
			$pdf->SetFont('', '', 7);
			$pdf->SetXY($x, $y + 20);
			$pdf->MultiCell($w, 4, $outputlangs->transnoentities('NoRecordFound'), 0, 'C');
			return;
		}
		$chartX = $x + 7;
		$chartY = $y + 10;
		$chartW = $w - 10;
		$chartH = $h - 18;
		$amountMaximum = 0.0;
		$hoursMaximum = 0.0;
		foreach ($monthAxis as $month) {
			$amountMaximum = max($amountMaximum, (float) $month['budget'], (float) $month['spent']);
			$hoursMaximum = max($hoursMaximum, (float) $month['time_hours']);
		}
		$amountMaximum = max(1.0, $amountMaximum);
		$hoursMaximum = max(1.0, $hoursMaximum);
		$count = count($monthAxis);
		$step = $chartW / $count;
		$pdf->SetDrawColor(160, 160, 160);
		$pdf->Line($chartX, $chartY + $chartH, $chartX + $chartW, $chartY + $chartH);
		$previous = null;
		$previousTime = null;
		$index = 0;
		foreach ($monthAxis as $month) {
			$barHeight = ((float) $month['budget'] / $amountMaximum) * $chartH;
			$barX = $chartX + ($index * $step) + ($step * 0.15);
			$pdf->SetFillColor(151, 187, 225);
			$pdf->Rect($barX, $chartY + $chartH - $barHeight, max(0.8, $step * 0.45), $barHeight, 'F');
			$pointX = $chartX + ($index * $step) + ($step * 0.55);
			$pointY = $chartY + $chartH - (((float) $month['spent'] / $amountMaximum) * $chartH);
			if (is_array($previous)) {
				$pdf->SetLineStyle(array('width' => 0.3, 'dash' => 0, 'color' => array(192, 80, 77)));
				$pdf->Line($previous[0], $previous[1], $pointX, $pointY);
			}
			$pdf->SetFillColor(192, 80, 77);
			$pdf->Circle($pointX, $pointY, 0.7, 0, 360, 'F');
			$previous = array($pointX, $pointY);
			$timePointY = $chartY + $chartH - (((float) $month['time_hours'] / $hoursMaximum) * $chartH);
			if (is_array($previousTime)) {
				$pdf->SetLineStyle(array('width' => 0.3, 'dash' => '2,1', 'color' => array(75, 172, 198)));
				$pdf->Line($previousTime[0], $previousTime[1], $pointX, $timePointY);
			}
			$pdf->SetFillColor(75, 172, 198);
			$pdf->Circle($pointX, $timePointY, 0.6, 0, 360, 'F');
			$previousTime = array($pointX, $timePointY);
			if ($count <= 12 || $index % (int) ceil($count / 12) === 0) {
				$pdf->SetFont('', '', 4.5);
				$pdf->StartTransform();
				$pdf->Rotate(45, $pointX, $chartY + $chartH + 2);
				$pdf->Text($pointX, $chartY + $chartH + 2, $outputlangs->convToOutputCharset($month['label']));
				$pdf->StopTransform();
			}
			$index++;
		}
		$pdf->SetLineStyle(array('width' => 0.2, 'dash' => 0, 'color' => array(0, 0, 0)));
	}

	/** @param TCPDF $pdf @param Project $object @param array<string,mixed> $data @param Translate $outputlangs @return void */
	private function drawCategorySummary($pdf, $object, $data, $outputlangs)
	{
		$pdf->SetFont('', 'B', 10);
		$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('BudgetReportCategorySummary')), 0, 'L');
		$headers = array('LMDB_CommercialCategoryExtrafield', 'BudgetReportOrderAmount', 'BudgetReportOrderBudget', 'BudgetReportSupplierExpenses', 'BudgetReportForecastGap');
		$widths = array(80, 42, 42, 42, 42);
		$this->drawTableHeader($pdf, $headers, $widths, $outputlangs);
		$forecast = $data['budgetReportForecast'];
		if (empty($forecast['categories'])) {
			$pdf->SetFont('', '', 7);
			$pdf->MultiCell(array_sum($widths), 6, $outputlangs->transnoentities('NoRecordFound'), 1, 'L');
			return;
		}
		foreach ($forecast['categories'] as $category) {
			if ($pdf->GetY() > $this->page_hauteur - $this->footerHeight - 16) {
				$this->addPage($pdf, $object, $outputlangs, true);
				$this->drawTableHeader($pdf, $headers, $widths, $outputlangs);
			}
			$values = array($category['label'], lmdbadvancedproject_format_price($category['order_amount'], $outputlangs), lmdbadvancedproject_format_price($category['order_budget'], $outputlangs), lmdbadvancedproject_format_price($category['supplier_expenses'], $outputlangs), lmdbadvancedproject_format_price($category['forecast_gap'], $outputlangs));
			$this->drawTableRow($pdf, $values, $widths, $outputlangs);
		}
	}

	/** @param TCPDF $pdf @param Project $object @param array<string,mixed> $data @param Translate $outputlangs @return void */
	private function drawTimeMatrix($pdf, $object, $data, $outputlangs)
	{
		$months = array_keys($data['monthAxis']);
		$blocks = empty($months) ? array(array()) : array_chunk($months, 10);
		foreach ($blocks as $monthBlock) {
			$this->addPage($pdf, $object, $outputlangs, true);
			$pdf->SetFont('', 'B', 10);
			$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('BudgetReportTimeBreakdownByMonth')), 0, 'L');
			$firstWidth = 78;
			$totalWidth = 20;
			$monthWidth = count($monthBlock) > 0 ? ($this->page_largeur - $this->marge_gauche - $this->marge_droite - $firstWidth - $totalWidth) / count($monthBlock) : 0;
			$headers = array($outputlangs->transnoentities('Task'));
			$widths = array($firstWidth);
			foreach ($monthBlock as $monthKey) {
				$headers[] = $data['monthAxis'][$monthKey]['label'];
				$widths[] = $monthWidth;
			}
			$headers[] = $outputlangs->transnoentities('BudgetReportTotal');
			$widths[] = $totalWidth;
			$this->drawTableHeader($pdf, $headers, $widths, $outputlangs, false);
			foreach ($data['timeBreakdown']['rows'] as $timeRow) {
				if ($pdf->GetY() > $this->page_hauteur - $this->footerHeight - 16) {
					$this->addPage($pdf, $object, $outputlangs, true);
					$pdf->SetFont('', 'B', 9);
					$pdf->MultiCell(0, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('BudgetReportTimeBreakdownByMonth')), 0, 'L');
					$this->drawTableHeader($pdf, $headers, $widths, $outputlangs, false);
				}
				$values = array($timeRow['ref'].' - '.$timeRow['label']);
				foreach ($monthBlock as $monthKey) {
					$values[] = lmdbadvancedproject_format_hours($timeRow['months'][$monthKey], false, $outputlangs);
				}
				$values[] = lmdbadvancedproject_format_hours($timeRow['total_hours'], false, $outputlangs);
				$this->drawTableRow($pdf, $values, $widths, $outputlangs);
			}
			$totals = array($outputlangs->transnoentities('BudgetReportTotal'));
			foreach ($monthBlock as $monthKey) {
				$totals[] = lmdbadvancedproject_format_hours($data['timeBreakdown']['column_totals'][$monthKey], false, $outputlangs);
			}
			$totals[] = lmdbadvancedproject_format_hours($data['timeBreakdown']['total_hours'], false, $outputlangs);
			$this->drawTableRow($pdf, $totals, $widths, $outputlangs, true);
		}
	}

	/** @param TCPDF $pdf @param array<int,string> $headers @param array<int,float> $widths @param Translate $outputlangs @param bool $translate @return void */
	private function drawTableHeader($pdf, $headers, $widths, $outputlangs, $translate = true)
	{
		$x = $this->marge_gauche;
		$y = $pdf->GetY();
		$pdf->SetFillColor(79, 129, 189);
		$pdf->SetTextColor(255, 255, 255);
		$pdf->SetFont('', 'B', 6.5);
		foreach ($headers as $index => $header) {
			$text = $translate ? $outputlangs->transnoentities($header) : $header;
			$pdf->SetXY($x, $y);
			$pdf->MultiCell($widths[$index], 7, $outputlangs->convToOutputCharset($text), 1, 'C', true, 0);
			$x += $widths[$index];
		}
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetY($y + 7);
	}

	/** @param TCPDF $pdf @param array<int,string> $values @param array<int,float> $widths @param Translate $outputlangs @param bool $bold @return void */
	private function drawTableRow($pdf, $values, $widths, $outputlangs, $bold = false)
	{
		$x = $this->marge_gauche;
		$y = $pdf->GetY();
		$firstValue = isset($values[0]) ? $outputlangs->convToOutputCharset((string) $values[0]) : '';
		$firstWidth = isset($widths[0]) ? (float) $widths[0] : 20.0;
		$height = max(7.0, min(14.0, (float) $pdf->getStringHeight($firstWidth, $firstValue)));
		$pdf->SetFont('', $bold ? 'B' : '', 6.5);
		foreach ($values as $index => $value) {
			$pdf->SetXY($x, $y);
			$pdf->MultiCell($widths[$index], $height, $outputlangs->convToOutputCharset((string) $value), 1, $index === 0 ? 'L' : 'R', false, 0, '', '', true, 0, false, true, $height, 'M');
			$x += $widths[$index];
		}
		$pdf->SetY($y + $height);
	}

	/** @param TCPDF $pdf @param Project $object @param Translate $outputlangs @return void */
	protected function _pagehead($pdf, $object, $outputlangs)
	{
		global $conf, $mysoc;

		pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
		$logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
		if (!empty($mysoc->logo) && is_readable($logo)) {
			$pdf->Image($logo, $this->marge_gauche, $this->marge_haute, 0, pdf_getHeightForLogo($logo));
		} else {
			$pdf->SetFont('', 'B', 9);
			$pdf->SetXY($this->marge_gauche, $this->marge_haute);
			$pdf->MultiCell(90, 4, $outputlangs->convToOutputCharset($mysoc->name), 0, 'L');
		}
		$pdf->SetTextColor(0, 0, 60);
		$pdf->SetFont('', 'B', 13);
		$pdf->SetXY($this->page_largeur - $this->marge_droite - 145, $this->marge_haute);
		$pdf->MultiCell(145, 5, $outputlangs->convToOutputCharset($outputlangs->transnoentities('BudgetReportPdfTitle')), 0, 'R');
		$pdf->SetFont('', '', 8);
		$pdf->SetXY($this->page_largeur - $this->marge_droite - 145, $this->marge_haute + 7);
		$pdf->MultiCell(145, 4, $outputlangs->convToOutputCharset($object->ref.' - '.$object->title), 0, 'R');
		$pdf->SetXY($this->page_largeur - $this->marge_droite - 145, $this->marge_haute + 13);
		$dates = $outputlangs->transnoentities('DateStart').' : '.dol_print_date($object->date_start, 'day', false, $outputlangs).' · '.$outputlangs->transnoentities('DateEnd').' : '.dol_print_date($object->date_end, 'day', false, $outputlangs);
		$pdf->MultiCell(145, 4, $outputlangs->convToOutputCharset($dates), 0, 'R');
		if (isset($object->thirdparty) && is_object($object->thirdparty)) {
			$pdf->SetXY($this->page_largeur - $this->marge_droite - 145, $this->marge_haute + 19);
			$pdf->MultiCell(145, 4, $outputlangs->convToOutputCharset($outputlangs->transnoentities('ThirdParty').' : '.$object->thirdparty->name), 0, 'R');
		}
		$pdf->SetTextColor(0, 0, 0);
	}

	/** @param TCPDF $pdf @param Project $object @param Translate $outputlangs @param int $hidefreetext @return int */
	protected function _pagefoot($pdf, $object, $outputlangs, $hidefreetext = 0)
	{
		$showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
		$pdf->setPageMark();
		$pdf->SetFillColor(255, 255, 255);
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetAutoPageBreak(false, 0);
		$result = pdf_pagefoot($pdf, $outputlangs, 'PROJECT_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
		$pdf->SetAutoPageBreak(true, $this->footerHeight);

		return $result;
	}
}
