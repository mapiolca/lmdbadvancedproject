<?php

/**
 * Central compatibility declaration for optional report features.
 */
class LmdbAdvancedProjectCompatibility
{
	public const MIN_DOLIBARR_VERSION = '20.0.0';
	public const MIN_PHP_VERSION = '8.0.0';
	/** @return array<string,string> Rule labels for the native settings selector */
	public static function projectAutocloseRules(): array
	{
		return array('disabled' => 'ProjectAutoCloseDisabled', 'invoices' => 'ProjectAutoCloseInvoices',
			'orders' => 'ProjectAutoCloseOrders', 'both' => 'ProjectAutoCloseBoth', 'either' => 'ProjectAutoCloseEither');
	}

	/** Native user selector accepts raw SQL in v20 and USF from v21. */
	public static function projectAutocloseUserFilter(): string
	{
		return version_compare(DOL_VERSION, '21.0.0', '>=')
			? '(u.statut:=:1) AND ((u.fk_soc:is:NULL) OR (u.fk_soc:=:0))'
			: 'AND u.statut = 1 AND (u.fk_soc IS NULL OR u.fk_soc = 0)';
	}

	/** Availability is independent of the switch and actor's functional permissions. */
	public static function projectAutocloseAvailable(?string $rule = null, ?int $days = null): bool
	{
		$rule = $rule ?? getDolGlobalString('LMDBADVANCEDPROJECT_AUTO_CLOSE_RULE', 'disabled');
		$days = $days ?? getDolGlobalInt('LMDBADVANCEDPROJECT_AUTO_CLOSE_DAYS', 30);
		return defined('DOL_VERSION') && version_compare(DOL_VERSION, self::MIN_DOLIBARR_VERSION, '>=')
			&& version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=')
			&& isModEnabled('lmdbadvancedproject') && isModEnabled('projet') && isModEnabled('cron') && isModEnabled('agenda')
			&& isset(self::projectAutocloseRules()[$rule])
			&& $days >= 1 && $days <= 36500
			&& ($rule === 'disabled' || $rule === 'orders' || isModEnabled('facture'))
			&& ($rule === 'disabled' || $rule === 'invoices' || isModEnabled('commande'));
	}
	/** Tables added in 1.4.1; old reporting remains available before reactivation. */
	public static function costValuationAvailable(): bool
	{
		global $db;
		if (!self::isShipmentCostEnabled()) { return false; }
		static $ready = null;
		if ($ready === null) {
			$ready = true;
			foreach (array('lmdbap_cost_instruction', 'lmdbap_cost_fallback') as $table) {
				$result = $db->query("SHOW TABLES LIKE '".$db->escape(MAIN_DB_PREFIX.$table)."'");
				if (!$result || $db->num_rows($result) < 1) { $ready = false; }
				if ($result) { $db->free($result); }
			}
		}
		return $ready;
	}

	/** Optional provider contracts are checked without calling any mutating method. */
	public static function costProviderAvailable(string $module): bool
	{
		if (!defined('DOL_VERSION') || version_compare(DOL_VERSION, self::MIN_DOLIBARR_VERSION, '<') || version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<') || !isModEnabled($module)) { return false; }
		if ($module === 'dynamicsprices') {
			if (!getDolGlobalInt('DYNAMICPRICES_COST_ENABLE', 1)) { return false; }
			if (!class_exists('DynamicPricesCostService')) { dol_include_once('/dynamicsprices/class/dynamicpricescostservice.class.php'); }
			$class = 'DynamicPricesCostService';
			$methods = array('getDynamicCostRecord' => 2);
		} elseif ($module === 'pricelist') {
			if (!class_exists('PriceList')) { dol_include_once('/pricelist/class/pricelist.class.php'); }
			$class = 'PriceList';
			$methods = array('get_price' => 4, 'getEffectiveCostPriceForRow' => 1, 'getHistory' => 0);
		} else {
			return false;
		}
		if (!class_exists($class)) { return false; }
		$contract = new ReflectionClass($class);
		foreach ($methods as $name => $arguments) {
			if (!$contract->hasMethod($name)) { return false; }
			$method = $contract->getMethod($name);
			if (!$method->isPublic() || $method->getNumberOfParameters() < $arguments || $method->getNumberOfRequiredParameters() > $arguments) { return false; }
		}
		return true;
	}
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
		if (!defined('DOL_VERSION') || version_compare(DOL_VERSION, self::MIN_DOLIBARR_VERSION, '<') || version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')
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
		$minimumDolibarrAvailable = defined('DOL_VERSION') && version_compare(DOL_VERSION, self::MIN_DOLIBARR_VERSION, '>=');
		$minimumPhpAvailable = version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>=');
		$spreadsheetAvailable = is_readable(DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/autoloader.php');

		$features = array(
			'project_autoclose' => array(
				'label' => 'ProjectAutoClose', 'description' => 'ProjectAutoCloseDescription',
				'available' => self::projectAutocloseAvailable(), 'reason' => 'ProjectAutoCloseUnavailable',
				'details' => array('BudgetCompatibilityModuleProject' => isModEnabled('projet'), 'Cron' => isModEnabled('cron'), 'Agenda' => isModEnabled('agenda')),
				'core_contract' => 'ProjectAutoCloseCore',
				'compatibility_check' => 'LmdbAdvancedProjectCompatibility::projectAutocloseAvailable()',
			),
			'cost_valuation' => array(
				'label' => 'BudgetCostValuation', 'description' => 'BudgetCostValuationDescription',
				'available' => self::costValuationAvailable(), 'reason' => 'BudgetCostValuationUnavailable',
				'details' => array('BudgetCostFeature' => self::isShipmentCostEnabled()),
				'core_contract' => 'BudgetCompatibilityValuationCore',
				'compatibility_check' => 'LmdbAdvancedProjectCompatibility::costValuationAvailable()',
			),
			'shipment_cost' => array(
				'label' => 'BudgetCostFeature', 'description' => 'BudgetCostFeatureDescription',
				'available' => self::shipmentCostAvailable(), 'reason' => 'BudgetCostUnavailable',
				'details' => array('BudgetCompatibilityModuleProject' => isModEnabled('projet'), 'BudgetCompatibilityModuleShipment' => isModEnabled('expedition'),
					'BudgetCompatibilityModuleProducts' => isModEnabled('product') || isModEnabled('service'), 'BudgetCostPmp' => isModEnabled('stock')),
				'core_contract' => 'BudgetCompatibilityShipmentCore',
				'compatibility_check' => 'LmdbAdvancedProjectCompatibility::shipmentCostAvailable()',
			),
			'dynamicprices_cost' => array(
				'label' => 'BudgetCostSource_dynamicprices', 'description' => 'BudgetCompatibilityDynamicDescription',
				'available' => self::costProviderAvailable('dynamicsprices'), 'reason' => 'BudgetCompatibilityDynamicUnavailable',
				'details' => array('DynamicPrices' => isModEnabled('dynamicsprices'), 'BudgetCompatibilityCostEnabled' => getDolGlobalInt('DYNAMICPRICES_COST_ENABLE', 1) === 1),
				'core_contract' => 'BudgetCompatibilityOptionalCore',
				'compatibility_check' => "LmdbAdvancedProjectCompatibility::costProviderAvailable('dynamicsprices')",
			),
			'pricelist_cost' => array(
				'label' => 'BudgetCostSource_pricelist', 'description' => 'BudgetCompatibilityPriceListDescription',
				'available' => self::costProviderAvailable('pricelist'), 'reason' => 'BudgetCompatibilityPriceListUnavailable',
				'details' => array('PriceList' => isModEnabled('pricelist')),
				'core_contract' => 'BudgetCompatibilityOptionalCore',
				'compatibility_check' => "LmdbAdvancedProjectCompatibility::costProviderAvailable('pricelist')",
			),
			'spreadsheet_export' => array(
				'label' => 'BudgetReportSpreadsheetExportFeature',
				'description' => 'BudgetReportSpreadsheetExportFeatureDescription',
				'core_contract' => 'BudgetCompatibilitySpreadsheetCore',
				'compatibility_check' => 'Dolibarr >= 20.0.0 && PHP >= 8.0.0 && PhpSpreadsheet && ZipArchive',
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
				'core_contract' => 'BudgetCompatibilityPdfCore',
				'compatibility_check' => 'Dolibarr >= 20.0.0 && PHP >= 8.0.0 && pdf.lib.php',
				'available' => $minimumDolibarrAvailable && $minimumPhpAvailable && is_readable(DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php'),
				'reason' => !$minimumDolibarrAvailable ? 'BudgetReportRequiresDolibarr20' : (!$minimumPhpAvailable ? 'BudgetReportRequiresPhp80' : (!is_readable(DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php') ? 'BudgetReportPdfLibraryMissing' : '')),
				'details' => array(),
			),
		);
		foreach ($features as &$feature) {
			$feature['min_dolibarr'] = self::MIN_DOLIBARR_VERSION;
			$feature['min_php'] = self::MIN_PHP_VERSION;
			$feature['core_available_from'] = self::MIN_DOLIBARR_VERSION;
			$feature['module_available_from'] = self::MIN_DOLIBARR_VERSION;
			if ($feature['available']) { $feature['reason'] = ''; }
			if (!$minimumDolibarrAvailable) { $feature['reason'] = 'BudgetReportRequiresDolibarr20'; }
			elseif (!$minimumPhpAvailable) { $feature['reason'] = 'BudgetReportRequiresPhp80'; }
		}
		unset($feature);
		return $features;
	}
}
