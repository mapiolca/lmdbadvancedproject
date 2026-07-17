<?php

require_once DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php';
require_once DOL_DOCUMENT_ROOT.'/includes/Psr/autoloader.php';
require_once PHPEXCELNEW_PATH.'Spreadsheet.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Ods;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Build budget report spreadsheet exports from the shared screen data model.
 */
class LmdbAdvancedProjectBudgetReportExport
{
	/** @var Translate */
	private $outputlangs;

	/** @var array<string,mixed> */
	private $data;

	/** @var Project|null */
	private $project;

	/**
	 * @param Translate           $outputlangs Output language
	 * @param array<string,mixed> $data Report data
	 * @param Project|null        $project Project for an individual report
	 */
	public function __construct($outputlangs, $data, $project = null)
	{
		$this->outputlangs = $outputlangs;
		$this->data = $data;
		$this->project = is_object($project) ? $project : null;
	}

	/**
	 * Stream an ODS or XLSX file.
	 *
	 * @param string $format ods|xlsx
	 * @return void
	 */
	public function output($format)
	{
		$format = strtolower((string) $format);
		$withCharts = $format === 'xlsx';
		$spreadsheet = $this->buildSpreadsheet($withCharts);
		if ($withCharts) {
			$writer = new Xlsx($spreadsheet);
			$writer->setIncludeCharts(true);
		} else {
			$writer = new Ods($spreadsheet);
		}
		$writer->save('php://output');
		$spreadsheet->disconnectWorksheets();
	}

	/**
	 * Build the complete workbook.
	 *
	 * @param bool $withCharts Add native spreadsheet charts
	 * @return Spreadsheet
	 */
	private function buildSpreadsheet($withCharts)
	{
		global $conf, $user;

		$spreadsheet = new Spreadsheet();
		$spreadsheet->getProperties()
			->setCreator($user->getFullName($this->outputlangs).' - '.DOL_APPLICATION_TITLE)
			->setTitle($this->outputlangs->transnoentities('BudgetReportArea'))
			->setSubject($this->outputlangs->transnoentities('BudgetReportArea'));

		$reportSheet = $spreadsheet->getActiveSheet();
		$reportSheet->setTitle($this->sheetTitle($this->outputlangs->transnoentities('BudgetReportExportSheetReport')));
		$timeSheet = $spreadsheet->createSheet();
		$timeSheet->setTitle($this->sheetTitle($this->outputlangs->transnoentities('BudgetReportExportSheetTime')));
		$dataSheet = $spreadsheet->createSheet();
		$dataSheet->setTitle($this->sheetTitle($this->outputlangs->transnoentities('BudgetReportExportSheetCharts')));

		$this->fillReportSheet($reportSheet, $withCharts);
		$this->fillTimeSheet($timeSheet);
		$this->fillChartDataSheet($dataSheet);

		if ($withCharts) {
			$this->addCharts($reportSheet, $dataSheet);
			$dataSheet->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN);
		}

		foreach ($spreadsheet->getAllSheets() as $sheet) {
			$sheet->setShowGridlines(false);
			$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
			$sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
			$sheet->getPageMargins()->setTop(0.4)->setRight(0.3)->setBottom(0.4)->setLeft(0.3);
		}

		$spreadsheet->setActiveSheetIndex(0);

		return $spreadsheet;
	}

	/**
	 * Fill report context, KPIs and main summary.
	 *
	 * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet Worksheet
	 * @param bool $withCharts Charts occupy rows 10 to 43
	 * @return void
	 */
	private function fillReportSheet($sheet, $withCharts)
	{
		global $conf;

		$currencyFormat = '#,##0.00 "'.(empty($conf->currency) ? '' : $conf->currency).'"';
		$sheet->setCellValue('A1', $this->outputlangs->transnoentities('BudgetReportArea'));
		$sheet->mergeCells('A1:F1');
		$this->styleTitle($sheet, 'A1:F1');

		$row = 3;
		if (is_object($this->project)) {
			$this->setText($sheet, 'A'.$row, $this->outputlangs->transnoentities('Project'));
			$this->setText($sheet, 'B'.$row, $this->project->ref.' - '.$this->project->title);
			$row++;
			$this->setText($sheet, 'A'.$row, $this->outputlangs->transnoentities('ThirdParty'));
			$this->setText($sheet, 'B'.$row, is_object($this->project->thirdparty) ? $this->project->thirdparty->name : '');
			$row++;
		} elseif (!empty($this->data['filters'])) {
			$statusLabels = array('open' => 'BudgetReportStatusOpen', 'closed' => 'BudgetReportStatusClosed', 'both' => 'BudgetReportStatusBoth');
			$filterRows = array(
				array('BudgetReportFilterDateStart', $this->data['filters']['date_start']),
				array('BudgetReportFilterDateEnd', $this->data['filters']['date_end']),
				array('BudgetReportFilterStatus', $this->outputlangs->transnoentities($statusLabels[$this->data['filters']['project_status']])),
				array('BudgetReportIgnoreStartedBefore', $this->outputlangs->transnoentities($this->data['filters']['ignore_started_before'] === '1' ? 'Yes' : 'No')),
				array('BudgetReportIgnoreEndedAfter', $this->outputlangs->transnoentities($this->data['filters']['ignore_ended_after'] === '1' ? 'Yes' : 'No')),
			);
			foreach ($filterRows as $filterIndex => $filterRow) {
				$filterRowNumber = 3 + (int) floor($filterIndex / 2);
				$filterColumn = $filterIndex % 2 === 0 ? 'A' : 'D';
				$valueColumn = $filterIndex % 2 === 0 ? 'B' : 'E';
				$this->setText($sheet, $filterColumn.$filterRowNumber, $this->outputlangs->transnoentities($filterRow[0]));
				$this->setText($sheet, $valueColumn.$filterRowNumber, $filterRow[1]);
			}
			$row = 6;
		}
		$this->setText($sheet, 'A'.$row, $this->outputlangs->transnoentities('Date'));
		$this->setText($sheet, 'B'.$row, dol_print_date(dol_now(), 'dayhour', false, $this->outputlangs));

		$kpiRow = 7;
		$kpis = array(
			array('BudgetReportMarket', (float) $this->data['totalorders']),
			array('BudgetReportInvoiced', (float) $this->data['totalcustomerinvoices']),
			array('BudgetReportBudget', (float) $this->data['budget']),
			array('BudgetReportSpent', (float) $this->data['totalspent']),
			array('BudgetReportLeftToSpend', (float) $this->data['balance']),
			array('BudgetReportTimeSpentHours', (float) $this->data['totalTimeHours']),
		);
		$column = 1;
		foreach ($kpis as $kpi) {
			$coordinate = Coordinate::stringFromColumnIndex($column).$kpiRow;
			$this->setText($sheet, $coordinate, $this->outputlangs->transnoentities($kpi[0]));
			$sheet->getStyle($coordinate)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
			$sheet->getStyle($coordinate)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F81BD');
			$valueCoordinate = Coordinate::stringFromColumnIndex($column).($kpiRow + 1);
			$sheet->setCellValue($valueCoordinate, $kpi[1]);
			$sheet->getStyle($valueCoordinate)->getNumberFormat()->setFormatCode($kpi[0] === 'BudgetReportTimeSpentHours' ? '#,##0.00' : $currencyFormat);
			$column++;
		}

		$row = $withCharts ? 46 : 11;
		if (!empty($this->data['budgetReportProjectId'])) {
			$this->writeProjectSummary($sheet, $row, $currencyFormat);
		} else {
			$this->writeGlobalSummary($sheet, $row, $currencyFormat);
		}
		$sheet->freezePane('A7');
		$sheet->getColumnDimension('A')->setWidth(28);
		for ($column = 2; $column <= 8; $column++) {
			$sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(18);
		}
	}

	/** @param mixed $sheet @param int $row @param string $currencyFormat @return void */
	private function writeGlobalSummary($sheet, $row, $currencyFormat)
	{
		$headers = array('BudgetReportProject', 'BudgetReportMarket', 'BudgetReportBudget', 'BudgetReportSpent', 'BudgetReportGrossMargin', 'BudgetReportBalance');
		$this->writeHeaderRow($sheet, $row, $headers);
		$row++;
		foreach ($this->data['projects'] as $project) {
			$this->setText($sheet, 'A'.$row, $project['project_ref'].' - '.$project['title']);
			$values = array((float) $project['orders'], (float) $project['budget'], (float) $project['spent'], (float) $project['orders'] - (float) $project['spent'], (float) $project['budget'] - (float) $project['spent']);
			foreach ($values as $index => $value) {
				$cell = Coordinate::stringFromColumnIndex($index + 2).$row;
				$sheet->setCellValue($cell, $value);
				$sheet->getStyle($cell)->getNumberFormat()->setFormatCode($currencyFormat);
			}
			$row++;
		}
		$this->setText($sheet, 'A'.$row, $this->outputlangs->transnoentities('BudgetReportTotal'));
		$totals = array($this->data['totalorders'], $this->data['budget'], $this->data['totalspent'], $this->data['totalorders'] - $this->data['totalspent'], $this->data['balance']);
		foreach ($totals as $index => $value) {
			$cell = Coordinate::stringFromColumnIndex($index + 2).$row;
			$sheet->setCellValue($cell, (float) $value);
			$sheet->getStyle($cell)->getNumberFormat()->setFormatCode($currencyFormat);
		}
		$sheet->getStyle('A'.$row.':F'.$row)->getFont()->setBold(true);
	}

	/** @param mixed $sheet @param int $row @param string $currencyFormat @return void */
	private function writeProjectSummary($sheet, $row, $currencyFormat)
	{
		$headers = array('LMDB_CommercialCategoryExtrafield', 'BudgetReportOrderAmount', 'BudgetReportOrderBudget', 'BudgetReportSupplierExpenses', 'BudgetReportForecastGap');
		$this->writeHeaderRow($sheet, $row, $headers);
		$row++;
		$forecast = $this->data['budgetReportForecast'];
		foreach ($forecast['categories'] as $category) {
			$this->setText($sheet, 'A'.$row, $category['label']);
			$values = array($category['order_amount'], $category['order_budget'], $category['supplier_expenses'], $category['forecast_gap']);
			foreach ($values as $index => $value) {
				$cell = Coordinate::stringFromColumnIndex($index + 2).$row;
				$sheet->setCellValue($cell, (float) $value);
				$sheet->getStyle($cell)->getNumberFormat()->setFormatCode($currencyFormat);
			}
			$row++;
		}
	}

	/** @param mixed $sheet @return void */
	private function fillTimeSheet($sheet)
	{
		$sheet->setCellValue('A1', $this->outputlangs->transnoentities('BudgetReportTimeBreakdownByMonth'));
		$lastColumn = count($this->data['monthAxis']) + 2;
		$sheet->mergeCells('A1:'.Coordinate::stringFromColumnIndex($lastColumn).'1');
		$this->styleTitle($sheet, 'A1:'.Coordinate::stringFromColumnIndex($lastColumn).'1');
		$this->setText($sheet, 'A3', $this->data['timeBreakdown']['mode'] === 'project' ? $this->outputlangs->transnoentities('BudgetReportProject') : $this->outputlangs->transnoentities('Task'));
		$column = 2;
		foreach ($this->data['monthAxis'] as $monthData) {
			$this->setText($sheet, Coordinate::stringFromColumnIndex($column).'3', $monthData['label']);
			$column++;
		}
		$this->setText($sheet, Coordinate::stringFromColumnIndex($column).'3', $this->outputlangs->transnoentities('BudgetReportTotal'));
		$this->styleHeader($sheet, 'A3:'.Coordinate::stringFromColumnIndex($column).'3');

		$row = 4;
		foreach ($this->data['timeBreakdown']['rows'] as $timeRow) {
			$this->setText($sheet, 'A'.$row, $timeRow['ref'].' - '.$timeRow['label']);
			$column = 2;
			foreach ($this->data['monthAxis'] as $monthKey => $monthData) {
				$sheet->setCellValue(Coordinate::stringFromColumnIndex($column).$row, (float) $timeRow['months'][$monthKey]);
				$column++;
			}
			$sheet->setCellValue(Coordinate::stringFromColumnIndex($column).$row, (float) $timeRow['total_hours']);
			$sheet->getStyle('B'.$row.':'.Coordinate::stringFromColumnIndex($column).$row)->getNumberFormat()->setFormatCode('#,##0.00');
			$row++;
		}

		$this->setText($sheet, 'A'.$row, $this->outputlangs->transnoentities('BudgetReportTotal'));
		$column = 2;
		foreach ($this->data['monthAxis'] as $monthKey => $monthData) {
			$sheet->setCellValue(Coordinate::stringFromColumnIndex($column).$row, (float) $this->data['timeBreakdown']['column_totals'][$monthKey]);
			$column++;
		}
		$sheet->setCellValue(Coordinate::stringFromColumnIndex($column).$row, (float) $this->data['timeBreakdown']['total_hours']);
		$sheet->getStyle('A'.$row.':'.Coordinate::stringFromColumnIndex($column).$row)->getFont()->setBold(true);
		$sheet->getStyle('B4:'.Coordinate::stringFromColumnIndex($column).$row)->getNumberFormat()->setFormatCode('#,##0.00');
		$sheet->freezePane('B4');
		$sheet->getColumnDimension('A')->setWidth(42);
		for ($index = 2; $index <= $column; $index++) {
			$sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setWidth(14);
		}
	}

	/** @param mixed $sheet @return void */
	private function fillChartDataSheet($sheet)
	{
		$this->setText($sheet, 'A1', $this->outputlangs->transnoentities($this->data['budgetChartTitleKey']));
		$this->setText($sheet, 'A2', $this->outputlangs->transnoentities('Label'));
		$this->setText($sheet, 'B2', $this->outputlangs->transnoentities('BudgetReportBudget'));
		$row = 3;
		foreach ($this->data['labels'] as $index => $label) {
			$this->setText($sheet, 'A'.$row, $label);
			$sheet->setCellValue('B'.$row, (float) $this->data['budgets'][$index]);
			$row++;
		}
		$this->setText($sheet, 'D1', $this->outputlangs->transnoentities('BudgetReportBudgetVsSpent'));
		$this->setText($sheet, 'D2', $this->outputlangs->transnoentities('Label'));
		$this->setText($sheet, 'E2', $this->outputlangs->transnoentities('BudgetReportSpent'));
		$row = 3;
		foreach ($this->data['spentLabels'] as $index => $label) {
			$this->setText($sheet, 'D'.$row, $label);
			$sheet->setCellValue('E'.$row, (float) $this->data['spentValues'][$index]);
			$row++;
		}
		$this->setText($sheet, 'G1', $this->outputlangs->transnoentities('BudgetReportBudgetVsSpentByMonth'));
		$this->setText($sheet, 'G2', $this->outputlangs->transnoentities('Month'));
		$this->setText($sheet, 'H2', $this->outputlangs->transnoentities('BudgetReportBudget'));
		$this->setText($sheet, 'I2', $this->outputlangs->transnoentities('BudgetReportSpent'));
		$this->setText($sheet, 'J2', $this->outputlangs->transnoentities('BudgetReportTimeSpentByMonth'));
		$row = 3;
		foreach ($this->data['monthAxis'] as $monthData) {
			$this->setText($sheet, 'G'.$row, $monthData['label']);
			$sheet->setCellValue('H'.$row, (float) $monthData['budget']);
			$sheet->setCellValue('I'.$row, (float) $monthData['spent']);
			$sheet->setCellValue('J'.$row, (float) $monthData['time_spent']);
			$row++;
		}
		$this->styleHeader($sheet, 'A2:B2');
		$this->styleHeader($sheet, 'D2:E2');
		$this->styleHeader($sheet, 'G2:J2');
		foreach (array('A', 'D', 'G') as $column) {
			$sheet->getColumnDimension($column)->setWidth(32);
		}
	}

	/** @param mixed $reportSheet @param mixed $dataSheet @return void */
	private function addCharts($reportSheet, $dataSheet)
	{
		$sheetName = $dataSheet->getTitle();
		$budgetCount = count($this->data['labels']);
		$spentCount = count($this->data['spentLabels']);
		$monthCount = count($this->data['monthAxis']);
		if ($budgetCount > 0) {
			$reportSheet->addChart($this->createPieChart($sheetName, 'A', 'B', $budgetCount, $this->outputlangs->transnoentities($this->data['budgetChartTitleKey']), 'A10', 'F26'));
		}
		if ($spentCount > 0) {
			$reportSheet->addChart($this->createPieChart($sheetName, 'D', 'E', $spentCount, $this->outputlangs->transnoentities('BudgetReportBudgetVsSpent'), 'G10', 'L26'));
		}
		if ($monthCount > 0) {
			$budgetSeries = new DataSeries(
				DataSeries::TYPE_BARCHART,
				DataSeries::GROUPING_CLUSTERED,
				array(0),
				array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'".$sheetName."'!\$H\$2", null, 1)),
				array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'".$sheetName."'!\$G\$3:\$G\$".($monthCount + 2), null, $monthCount)),
				array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'".$sheetName."'!\$H\$3:\$H\$".($monthCount + 2), null, $monthCount))
			);
			$spentSeries = new DataSeries(
				DataSeries::TYPE_LINECHART,
				DataSeries::GROUPING_STANDARD,
				array(0),
				array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'".$sheetName."'!\$I\$2", null, 1)),
				array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'".$sheetName."'!\$G\$3:\$G\$".($monthCount + 2), null, $monthCount)),
				array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'".$sheetName."'!\$I\$3:\$I\$".($monthCount + 2), null, $monthCount))
			);
			$timeSeries = new DataSeries(
				DataSeries::TYPE_LINECHART,
				DataSeries::GROUPING_STANDARD,
				array(0),
				array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'".$sheetName."'!\$J\$2", null, 1)),
				array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'".$sheetName."'!\$G\$3:\$G\$".($monthCount + 2), null, $monthCount)),
				array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'".$sheetName."'!\$J\$3:\$J\$".($monthCount + 2), null, $monthCount))
			);
			$chart = new Chart('BudgetReportMonthlyChart', new Title($this->outputlangs->transnoentities('BudgetReportBudgetVsSpentByMonth')), new Legend(Legend::POSITION_TOP, null, false), new PlotArea(null, array($budgetSeries, $spentSeries, $timeSeries)));
			$chart->setTopLeftPosition('A28')->setBottomRightPosition('L44');
			$reportSheet->addChart($chart);
		}
	}

	/** @return Chart */
	private function createPieChart($sheetName, $labelColumn, $valueColumn, $count, $title, $topLeft, $bottomRight)
	{
		$series = new DataSeries(
			DataSeries::TYPE_PIECHART,
			DataSeries::GROUPING_STANDARD,
			array(0),
			array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'".$sheetName."'!\$".$valueColumn."\$2", null, 1)),
			array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'".$sheetName."'!\$".$labelColumn."\$3:\$".$labelColumn."\$".($count + 2), null, $count)),
			array(new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'".$sheetName."'!\$".$valueColumn."\$3:\$".$valueColumn."\$".($count + 2), null, $count))
		);
		$chart = new Chart('BudgetReport'.preg_replace('/[^A-Za-z0-9]/', '', $valueColumn.$title), new Title($title), new Legend(Legend::POSITION_RIGHT, null, false), new PlotArea(null, array($series)));
		$chart->setTopLeftPosition($topLeft)->setBottomRightPosition($bottomRight);

		return $chart;
	}

	/** @param mixed $sheet @param int $row @param array<int,string> $headers @return void */
	private function writeHeaderRow($sheet, $row, $headers)
	{
		foreach ($headers as $index => $translationKey) {
			$this->setText($sheet, Coordinate::stringFromColumnIndex($index + 1).$row, $this->outputlangs->transnoentities($translationKey));
		}
		$this->styleHeader($sheet, 'A'.$row.':'.Coordinate::stringFromColumnIndex(count($headers)).$row);
	}

	/** @param mixed $sheet @param string $range @return void */
	private function styleTitle($sheet, $range)
	{
		$style = $sheet->getStyle($range);
		$style->getFont()->setBold(true)->setSize(16)->getColor()->setRGB('FFFFFF');
		$style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E78');
		$style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
	}

	/** @param mixed $sheet @param string $range @return void */
	private function styleHeader($sheet, $range)
	{
		$style = $sheet->getStyle($range);
		$style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
		$style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F81BD');
		$style->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9E2F3');
		$style->getAlignment()->setWrapText(true);
	}

	/** @param mixed $sheet @param string $coordinate @param mixed $value @return void */
	private function setText($sheet, $coordinate, $value)
	{
		$text = (string) $value;
		if (preg_match('/^[=+\-@]/', $text)) {
			$text = "'".$text;
		}
		$sheet->setCellValueExplicit($coordinate, $text, DataType::TYPE_STRING);
	}

	/** @param string $title @return string */
	private function sheetTitle($title)
	{
		$title = preg_replace('~[\\\\/?*\[\]:]~', '-', (string) $title);

		return dol_substr($title, 0, 31);
	}
}
