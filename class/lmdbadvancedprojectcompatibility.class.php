<?php

/**
 * Central compatibility declaration for optional report features.
 */
class LmdbAdvancedProjectCompatibility
{
	/**
	 * Return compatibility information for the current environment.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function getFeatures()
	{
		$minimumDolibarrAvailable = defined('DOL_VERSION') && version_compare(DOL_VERSION, '20.0.0', '>=');
		$minimumPhpAvailable = version_compare(PHP_VERSION, '8.0.0', '>=');
		$spreadsheetAvailable = is_readable(DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php');

		return array(
			'spreadsheet_export' => array(
				'label' => 'BudgetReportSpreadsheetExportFeature',
				'description' => 'BudgetReportSpreadsheetExportFeatureDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $minimumDolibarrAvailable && $minimumPhpAvailable && $spreadsheetAvailable && class_exists('ZipArchive'),
				'reason' => !$minimumDolibarrAvailable ? 'BudgetReportRequiresDolibarr20' : (!$minimumPhpAvailable ? 'BudgetReportRequiresPhp80' : (!$spreadsheetAvailable ? 'BudgetReportExportLibraryMissing' : (!class_exists('ZipArchive') ? 'BudgetReportExportZipMissing' : ''))),
				'details' => array(
					'PhpSpreadsheet' => $spreadsheetAvailable,
					'ZipArchive' => class_exists('ZipArchive'),
				),
			),
			'project_pdf' => array(
				'label' => 'BudgetReportProjectPdfFeature',
				'description' => 'BudgetReportProjectPdfFeatureDescription',
				'min_dolibarr' => '20.0.0',
				'min_php' => '8.0.0',
				'available' => $minimumDolibarrAvailable && $minimumPhpAvailable && is_readable(DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php'),
				'reason' => !$minimumDolibarrAvailable ? 'BudgetReportRequiresDolibarr20' : (!$minimumPhpAvailable ? 'BudgetReportRequiresPhp80' : (!is_readable(DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php') ? 'BudgetReportPdfLibraryMissing' : '')),
				'details' => array(),
			),
		);
	}
}
