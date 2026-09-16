<?php

/**
 * Central compatibility declaration for optional report features.
 */
class LmdbAdvancedProjectCompatibility
{
	/** Shared server-side predicate; permissions remain separate. */
	public static function isShipmentCostEnabled(): bool
	{
		return getDolGlobalInt('LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST') === 1
			&& self::shipmentCostAvailable();
	}

	/** Availability is checked independently of the administrator's switch. */
	public static function shipmentCostAvailable(): bool
	{
		global $db;
		if (!defined('DOL_VERSION') || version_compare(DOL_VERSION, '20.0.0', '<') || version_compare(PHP_VERSION, '8.0.0', '<')
			|| !isModEnabled('lmdbadvancedproject') || !isModEnabled('projet') || !isModEnabled('expedition')
			|| (!isModEnabled('product') && !isModEnabled('service'))) {
			return false;
		}
		static $ready = null;
		if ($ready === null) {
			$ready = true;
			foreach (array('lmdbap_shipment_cost', 'lmdbap_tariff_history') as $table) {
				$result = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.$table)."'");
				if (!$result || $db->num_rows($result) < 1) {
					$ready = false;
				}
				if ($result) {
					$db->free($result);
				}
			}
		}
		return $ready;
	}
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
			'shipment_cost' => array(
				'label' => 'BudgetCostFeature', 'description' => 'BudgetCostFeatureDescription',
				'min_dolibarr' => '20.0.0', 'min_php' => '8.0.0',
				'available' => self::shipmentCostAvailable(), 'reason' => 'BudgetCostUnavailable',
				'details' => array('SHIPPING_VALIDATE / SHIPMENT_UNVALIDATE (v20–v24)' => true,
					(version_compare(DOL_VERSION, '23.0.0', '>=') ? 'PRODUCT_BUYPRICE_* (v23+)' : 'SUPPLIER_PRODUCT_BUYPRICE_* (v20–v22)') => true, 'PMP' => isModEnabled('stock')),
			),
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
