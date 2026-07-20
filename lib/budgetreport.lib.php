<?php

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT.'/expensereport/class/expensereport.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

if (!function_exists('lmdbadvancedproject_round_amount')) {
	/**
	 * Round monetary amounts for display and chart data.
	 *
	 * @param  float|int $amount Amount to round
	 * @return float
	 */
	function lmdbadvancedproject_round_amount($amount)
	{
		return (float) price2num((float) $amount, 'MT');
	}
}

if (!function_exists('lmdbadvancedproject_format_price')) {
	/**
	 * Format amounts with Dolibarr's configured currency.
	 *
	 * @param  float|int $amount Amount to format
	 * @param Translate|null $outputlangs Output language
	 * @return string
	 */
	function lmdbadvancedproject_format_price($amount, $outputlangs = null)
	{
		global $conf, $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$currencyCode = empty($conf->currency) ? '' : $conf->currency;

		return price(price2num((float) $amount, 'MT'), 0, $outputlangs, 1, -1, -1, $currencyCode);
	}
}

if (!function_exists('lmdbadvancedproject_format_hours')) {
	/**
	 * Format a decimal duration using the current Dolibarr locale.
	 *
	 * @param float|int $hours Decimal hours
	 * @param bool      $withUnit Append the translated hour unit
	 * @param Translate|null $outputlangs Output language
	 * @return string
	 */
	function lmdbadvancedproject_format_hours($hours, $withUnit = false, $outputlangs = null)
	{
		global $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$value = price(price2num((float) $hours, 'MT'), 0, $outputlangs, 0, -1, -1);

		return $withUnit ? $outputlangs->trans('BudgetReportHoursValue', $value) : $value;
	}
}

if (!function_exists('lmdbadvancedproject_budget_report_document_title')) {
	/**
	 * Return the localized project budget report document title.
	 *
	 * @param string         $projectRef Project reference
	 * @param Translate|null $outputlangs Output language
	 * @return string
	 */
	function lmdbadvancedproject_budget_report_document_title($projectRef, $outputlangs = null)
	{
		global $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}

		return (string) $projectRef.' - '.$outputlangs->transnoentities('BudgetReportPdfDocumentName');
	}
}

if (!function_exists('lmdbadvancedproject_budget_report_filename')) {
	/**
	 * Return the localized project budget report filename.
	 *
	 * @param string         $projectRef Project reference
	 * @param Translate|null $outputlangs Output language
	 * @return string
	 */
	function lmdbadvancedproject_budget_report_filename($projectRef, $outputlangs = null)
	{
		return dol_sanitizeFileName(lmdbadvancedproject_budget_report_document_title($projectRef, $outputlangs), '_', 0).'.pdf';
	}
}

if (!function_exists('lmdbadvancedproject_get_month_label')) {
	/**
	 * Return a localized month label for a YYYY-MM key.
	 *
	 * @param string    $monthKey YYYY-MM
	 * @param Translate $outputlangs Output language
	 * @return string
	 */
	function lmdbadvancedproject_get_month_label($monthKey, $outputlangs = null)
	{
		global $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $monthKey, $matches)) {
			return (string) $monthKey;
		}

		$timestamp = dol_mktime(12, 0, 0, (int) $matches[2], 1, (int) $matches[1]);

		return dol_print_date($timestamp, '%b %Y', false, $outputlangs, true);
	}
}

if (!function_exists('lmdbadvancedproject_format_margin')) {
	/**
	 * Format gross margin with its percentage of orders amount.
	 *
	 * @param  float|int $amount Gross margin amount
	 * @param  float|int $orders Orders amount
	 * @return string
	 */
	function lmdbadvancedproject_format_margin($amount, $orders)
	{
		$formattedAmount = lmdbadvancedproject_format_price($amount);

		if ($orders > 0) {
			return $formattedAmount.' ('.round(($amount / $orders) * 100).'%)';
		}

		return $formattedAmount.' (-)';
	}
}

if (!function_exists('lmdbadvancedproject_format_percentage')) {
	/**
	 * Format a compact percentage from a partial amount and total amount.
	 *
	 * @param  float|int $amount Partial amount
	 * @param  float|int $total  Total amount
	 * @return string
	 */
	function lmdbadvancedproject_format_percentage($amount, $total)
	{
		if ($total <= 0) {
			return '0%';
		}

		return round(((float) $amount / (float) $total) * 100).'%';
	}
}

if (!function_exists('lmdbadvancedproject_format_spent_percentage')) {
	/**
	 * Format a compact percentage of the total spent amount.
	 *
	 * @param  float|int $amount     Partial spent amount
	 * @param  float|int $totalSpent Total spent amount
	 * @return string
	 */
	function lmdbadvancedproject_format_spent_percentage($amount, $totalSpent)
	{
		return lmdbadvancedproject_format_percentage($amount, $totalSpent);
	}
}

if (!function_exists('lmdbadvancedproject_trans_chart')) {
	/**
	 * Return a translated string suitable for JavaScript chart labels.
	 *
	 * @param  string $key Translation key
	 * @return string
	 */
	function lmdbadvancedproject_trans_chart($key)
	{
		global $langs;

		if (method_exists($langs, 'transnoentitiesnoconv')) {
			return $langs->transnoentitiesnoconv($key);
		}

		return html_entity_decode($langs->trans($key), ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('lmdbadvancedproject_chart_label')) {
	/**
	 * Return a string suitable for JavaScript chart labels.
	 *
	 * @param  string $value Label value
	 * @return string
	 */
	function lmdbadvancedproject_chart_label($value)
	{
		return html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('lmdbadvancedproject_format_date')) {
	/**
	 * Format a SQL date using Dolibarr user preferences.
	 *
	 * @param  string|int $date Date value
	 * @return string
	 */
	function lmdbadvancedproject_format_date($date)
	{
		global $db;

		if (empty($date)) {
			return '';
		}

		if (is_numeric($date)) {
			$timestamp = (int) $date;
		} elseif (method_exists($db, 'jdate')) {
			$timestamp = $db->jdate($date);
		} else {
			$timestamp = strtotime($date);
		}

		if (empty($timestamp)) {
			return lmdbadvancedproject_escape_html((string) $date);
		}

		if (function_exists('dol_print_date')) {
			return dol_print_date($timestamp, 'day');
		}

		return date('d/m/Y', $timestamp);
	}
}

if (!function_exists('lmdbadvancedproject_format_modal_date')) {
	/**
	 * Format a SQL date for compact modal columns.
	 *
	 * @param  string|int $date Date value
	 * @return string
	 */
	function lmdbadvancedproject_format_modal_date($date)
	{
		global $db;

		if (empty($date)) {
			return '';
		}

		if (is_numeric($date)) {
			$timestamp = (int) $date;
		} elseif (method_exists($db, 'jdate')) {
			$timestamp = $db->jdate($date);
		} else {
			$timestamp = strtotime($date);
		}

		if (empty($timestamp)) {
			return lmdbadvancedproject_escape_html((string) $date);
		}

		return date('d/m/Y', $timestamp);
	}
}

if (!function_exists('lmdbadvancedproject_format_multiline_text')) {
	/**
	 * Normalize line breaks and escape a free text value for table output.
	 *
	 * @param  string $value Text value
	 * @return string
	 */
	function lmdbadvancedproject_format_multiline_text($value)
	{
		$text = (string) $value;
		$text = str_replace(array('\\r\\n', '\\n', '\\r'), "\n", $text);
		$text = str_replace(array("\r\n", "\r"), "\n", $text);

		$lines = array();
		foreach (explode("\n", $text) as $line) {
			$line = trim(preg_replace('/[ \t]+/', ' ', $line));
			if ($line !== '') {
				$lines[] = $line;
			}
		}

		return nl2br(lmdbadvancedproject_escape_html(implode("\n", $lines)), false);
	}
}

if (!function_exists('lmdbadvancedproject_normalize_compact_text')) {
	/**
	 * Normalize free text for compact single-line table output.
	 *
	 * @param  string $value Text value
	 * @return string
	 */
	function lmdbadvancedproject_normalize_compact_text($value)
	{
		$text = (string) $value;
		$text = str_replace(array('\\r\\n', '\\n', '\\r'), "\n", $text);
		$text = str_replace(array("\r\n", "\r"), "\n", $text);

		$lines = array();
		foreach (explode("\n", $text) as $line) {
			$line = trim(preg_replace('/[ \t]+/', ' ', $line));
			if ($line !== '') {
				$lines[] = $line;
			}
		}

		return implode(' ', $lines);
	}
}

if (!function_exists('lmdbadvancedproject_truncated_text_parts')) {
	/**
	 * Return escaped full and truncated text parts for compact output.
	 *
	 * @param  string $value Text value
	 * @param  int    $limit Maximum displayed length
	 * @return array<string,string>
	 */
	function lmdbadvancedproject_truncated_text_parts($value, $limit)
	{
		$fullText = lmdbadvancedproject_normalize_compact_text($value);
		$limit = (int) $limit;
		if ($limit <= 0) {
			$limit = 1;
		}

		if (function_exists('dol_trunc')) {
			$shortText = dol_trunc($fullText, $limit);
		} elseif (strlen($fullText) > $limit) {
			$shortText = substr($fullText, 0, max(0, $limit - 3)).'...';
		} else {
			$shortText = $fullText;
		}

		return array(
			'full' => lmdbadvancedproject_escape_html($fullText),
			'short' => lmdbadvancedproject_escape_html($shortText),
		);
	}
}

if (!function_exists('lmdbadvancedproject_is_multicompany_enabled')) {
	/**
	 * Check if Dolibarr Multicompany module is enabled.
	 *
	 * @return bool
	 */
	function lmdbadvancedproject_is_multicompany_enabled()
	{
		global $conf;

		if (function_exists('isModEnabled')) {
			return isModEnabled('multicompany');
		}

		return !empty($conf->multicompany->enabled);
	}
}

if (!function_exists('lmdbadvancedproject_get_entity_filter')) {
	/**
	 * Return entity filter values for a Dolibarr element.
	 *
	 * @param  string $element Dolibarr element name
	 * @param  int    $shared  Use shared/available entities
	 * @return string
	 */
	function lmdbadvancedproject_get_entity_filter($element, $shared)
	{
		global $conf;

		if (function_exists('getEntity')) {
			return getEntity($element, $shared);
		}

		return (string) ((int) $conf->entity);
	}
}

if (!function_exists('lmdbadvancedproject_table_exists')) {
	/**
	 * Check if a database table exists.
	 *
	 * @param  string $tableName Full table name
	 * @return bool
	 */
	function lmdbadvancedproject_table_exists($tableName)
	{
		global $db;

		$sql = "SHOW TABLES LIKE '".$db->escape($tableName)."'";
		$resql = $db->query($sql);

		return ($resql && $db->num_rows($resql) > 0);
	}
}

if (!function_exists('lmdbadvancedproject_column_exists')) {
	/**
	 * Check if a database column exists.
	 *
	 * @param  string $tableName  Full table name
	 * @param  string $columnName Column name
	 * @return bool
	 */
	function lmdbadvancedproject_column_exists($tableName, $columnName)
	{
		global $db;

		$sql = "SHOW COLUMNS FROM ".$tableName." LIKE '".$db->escape($columnName)."'";
		$resql = $db->query($sql);

		return ($resql && $db->num_rows($resql) > 0);
	}
}

if (!function_exists('lmdbadvancedproject_supplier_invoice_split_report_enabled')) {
	/**
	 * Check if supplier invoice split rows must be used by reports.
	 *
	 * @return bool
	 */
	function lmdbadvancedproject_supplier_invoice_split_report_enabled()
	{
		global $conf;

		return !empty($conf->global->LMDBADVANCEDPROJECT_ENABLE_SUPPLIER_INVOICE_SPLIT)
			&& lmdbadvancedproject_table_exists(MAIN_DB_PREFIX.'lmdbadvancedproject_supplier_invoice_parts');
	}
}

if (!function_exists('lmdbadvancedproject_customer_invoice_split_report_enabled')) {
	/**
	 * Check if customer invoice split rows must be used by reports.
	 *
	 * @return bool
	 */
	function lmdbadvancedproject_customer_invoice_split_report_enabled()
	{
		global $conf;

		return !empty($conf->global->LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT)
			&& lmdbadvancedproject_table_exists(MAIN_DB_PREFIX.'lmdbadvancedproject_customer_invoice_parts');
	}
}

if (!function_exists('lmdbadvancedproject_escape_html')) {
	/**
	 * Escape a string for HTML output.
	 *
	 * @param  string $value Value to escape
	 * @return string
	 */
	function lmdbadvancedproject_escape_html($value)
	{
		if (function_exists('dol_escape_htmltag')) {
			return dol_escape_htmltag($value);
		}

		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('lmdbadvancedproject_normalize_report_date')) {
	/**
	 * Return a valid YYYY-MM-DD date string or an empty string.
	 *
	 * @param  string $date Date from request or configuration
	 * @return string
	 */
	function lmdbadvancedproject_normalize_report_date($date)
	{
		$date = trim((string) $date);
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return '';
		}

		$timestamp = strtotime($date);
		if ($timestamp === false || date('Y-m-d', $timestamp) !== $date) {
			return '';
		}

		return $date;
	}
}

if (!function_exists('lmdbadvancedproject_normalize_budget_report_filters')) {
	/**
	 * Normalize global budget report filters.
	 *
	 * @param  array<string,mixed> $filters Raw filters
	 * @return array<string,mixed>
	 */
	function lmdbadvancedproject_normalize_budget_report_filters($filters = array())
	{
		$normalized = array(
			'date_start' => '',
			'date_end' => '',
			'ignore_started_before' => '0',
			'ignore_ended_after' => '0',
			'exclude_content_outside_period' => '0',
			'project_status' => 'open',
			'project_ids' => array(),
		);

		if (is_array($filters)) {
			$normalized['date_start'] = lmdbadvancedproject_normalize_report_date(empty($filters['date_start']) ? '' : $filters['date_start']);
			$normalized['date_end'] = lmdbadvancedproject_normalize_report_date(empty($filters['date_end']) ? '' : $filters['date_end']);
			$normalized['ignore_started_before'] = empty($filters['ignore_started_before']) ? '0' : '1';
			$normalized['ignore_ended_after'] = empty($filters['ignore_ended_after']) ? '0' : '1';
			$normalized['exclude_content_outside_period'] = empty($filters['exclude_content_outside_period']) ? '0' : '1';

			$projectStatus = empty($filters['project_status']) ? 'open' : (string) $filters['project_status'];
			if (in_array($projectStatus, array('open', 'closed', 'both'), true)) {
				$normalized['project_status'] = $projectStatus;
			}

			$projectIds = isset($filters['project_ids']) ? $filters['project_ids'] : array();
			if (!is_array($projectIds)) {
				$projectIds = explode(',', (string) $projectIds);
			}
			$normalizedProjectIds = array();
			foreach ($projectIds as $projectId) {
				$projectId = (int) $projectId;
				if ($projectId > 0) {
					$normalizedProjectIds[$projectId] = $projectId;
				}
			}
			$normalized['project_ids'] = array_values($normalizedProjectIds);
		}

		return $normalized;
	}
}

if (!function_exists('lmdbadvancedproject_get_budget_report_authorized_project_ids')) {
	/**
	 * Return project ids available to the current user, or null when unrestricted by assignment.
	 *
	 * Entity and Multicompany filters are applied separately to each report query.
	 *
	 * @return list<int>|null
	 */
	function lmdbadvancedproject_get_budget_report_authorized_project_ids()
	{
		global $db, $user;

		static $loaded = false;
		static $authorizedProjectIds = null;
		if ($loaded) {
			return $authorizedProjectIds;
		}
		$loaded = true;

		if (!empty($user->admin) || (empty($user->socid) && $user->hasRight('projet', 'all', 'lire'))) {
			return null;
		}

		$projectStatic = new Project($db);
		$authorizedProjectList = $projectStatic->getProjectsAuthorizedForUser($user, 0, 1, empty($user->socid) ? 0 : (int) $user->socid);
		$authorizedProjectIds = array();
		$authorizedProjectCandidates = is_array($authorizedProjectList)
			? array_keys($authorizedProjectList)
			: explode(',', (string) $authorizedProjectList);
		foreach ($authorizedProjectCandidates as $projectId) {
			$projectId = (int) $projectId;
			if ($projectId > 0) {
				$authorizedProjectIds[] = $projectId;
			}
		}

		return $authorizedProjectIds;
	}
}

if (!function_exists('lmdbadvancedproject_get_budget_report_project_options')) {
	/**
	 * Return projects available in the global budget report filter.
	 *
	 * @return array<int,string>
	 */
	function lmdbadvancedproject_get_budget_report_project_options()
	{
		global $db;

		$entityShared = (lmdbadvancedproject_is_multicompany_enabled() && getDolGlobalInt('LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES')) ? 1 : 0;
		$projectEntities = lmdbadvancedproject_get_entity_filter('project', $entityShared);
		$orderEntities = lmdbadvancedproject_get_entity_filter('commande', 1);
		$authorizedProjectIds = lmdbadvancedproject_get_budget_report_authorized_project_ids();

		$sql = 'SELECT DISTINCT p.rowid, p.ref, p.title';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'projet p';
		$sql .= ' INNER JOIN '.MAIN_DB_PREFIX.'commande c ON c.fk_projet = p.rowid';
		$sql .= ' WHERE p.entity IN ('.$projectEntities.')';
		$sql .= ' AND c.entity IN ('.$orderEntities.') AND c.fk_statut > 0';
		if (is_array($authorizedProjectIds)) {
			$sql .= ' AND p.rowid IN ('.(empty($authorizedProjectIds) ? '0' : implode(',', array_map('intval', $authorizedProjectIds))).')';
		}
		$sql .= ' ORDER BY p.ref ASC';

		$options = array();
		$result = $db->query($sql);
		if (!$result) {
			dol_syslog(__FUNCTION__.': failed to load project filter options: '.$db->lasterror(), LOG_ERR);
			return $options;
		}
		while (is_object($projectRow = $db->fetch_object($result))) {
			$label = (string) $projectRow->ref;
			if (!empty($projectRow->title)) {
				$label .= ' - '.(string) $projectRow->title;
			}
			$options[(int) $projectRow->rowid] = $label;
		}
		$db->free($result);

		return $options;
	}
}

if (!function_exists('lmdbadvancedproject_get_customer_order_list_url')) {
	/**
	 * Build the native customer order list URL matching a project report total.
	 *
	 * @param  string              $projectRef Project reference
	 * @param  array<string,mixed> $filters    Normalized report filters
	 * @return string
	 */
	function lmdbadvancedproject_get_customer_order_list_url($projectRef, $filters)
	{
		$params = array(
			'leftmenu' => 'orders',
			'search_project_ref' => (string) $projectRef,
			'search_status' => -3,
		);

		$filters = lmdbadvancedproject_normalize_budget_report_filters($filters);
		if (lmdbadvancedproject_budget_report_content_period_is_active($filters)) {
			$dateParameters = array(
				'date_start' => 'search_dateorder_start_',
				'date_end' => 'search_dateorder_end_',
			);
			foreach ($dateParameters as $filterKey => $parameterPrefix) {
				if (!empty($filters[$filterKey]) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $filters[$filterKey], $matches)) {
					$params[$parameterPrefix.'year'] = (int) $matches[1];
					$params[$parameterPrefix.'month'] = (int) $matches[2];
					$params[$parameterPrefix.'day'] = (int) $matches[3];
				}
			}
		}

		return DOL_URL_ROOT.'/commande/list.php?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	}
}

if (!function_exists('lmdbadvancedproject_get_customer_order_summary_html')) {
	/**
	 * Render an order amount linked to the native order list with detail tooltip.
	 *
	 * @param  Form                $form         Dolibarr form helper
	 * @param  string              $projectRef   Project reference
	 * @param  float|int           $orders       Project orders amount
	 * @param  list<array{id:int,ref:string,date:string,amount:float}> $orderDetails Order details
	 * @param  array<string,mixed> $filters      Normalized report filters
	 * @return string
	 */
	function lmdbadvancedproject_get_customer_order_summary_html($form, $projectRef, $orders, $orderDetails, $filters)
	{
		global $db, $langs, $user;

		$formattedSummary = lmdbadvancedproject_format_price($orders);
		if (!$user->hasRight('commande', 'lire')) {
			return $formattedSummary;
		}

		$listUrl = lmdbadvancedproject_get_customer_order_list_url($projectRef, $filters);
		$summaryLink = '<a href="'.dol_escape_htmltag($listUrl).'">'.$formattedSummary.'</a>';
		if (empty($orderDetails)) {
			return $summaryLink;
		}

		$tooltip = '<table class="nobordernopadding">';
		$tooltip .= '<tr class="liste_titre"><th>'.$langs->trans('BudgetReportMarket').'</th><th>'.$langs->trans('Date').'</th><th class="right">'.$langs->trans('AmountHTShort').'</th></tr>';
		foreach ($orderDetails as $orderDetail) {
			$orderStatic = new Commande($db);
			$orderStatic->id = (int) $orderDetail['id'];
			$orderStatic->ref = (string) $orderDetail['ref'];

			$tooltip .= '<tr>';
			$tooltip .= '<td>'.$orderStatic->getNomUrl(1, '', 0, 0, 1).'</td>';
			$tooltip .= '<td class="nowrap">'.lmdbadvancedproject_format_date($orderDetail['date']).'</td>';
			$tooltip .= '<td class="right nowrap">'.lmdbadvancedproject_format_price($orderDetail['amount']).'</td>';
			$tooltip .= '</tr>';
		}
		$tooltip .= '</table>';

		return $form->textwithtooltip($summaryLink, $tooltip, 1, 0, '', '', 3, '', 1);
	}
}

if (!function_exists('lmdbadvancedproject_get_customer_invoice_list_url')) {
	/**
	 * Build the native customer invoice list URL matching a project report total.
	 *
	 * @param  string              $projectRef Project reference
	 * @param  array<string,mixed> $filters    Normalized report filters
	 * @return string
	 */
	function lmdbadvancedproject_get_customer_invoice_list_url($projectRef, $filters)
	{
		$params = array(
			'leftmenu' => 'customers_bills',
			'search_project_ref' => (string) $projectRef,
		);

		if (defined('DOL_VERSION') && version_compare(DOL_VERSION, '24.0.0-alpha', '>=')) {
			$params['search_status'] = array(1, 2);
		} else {
			$params['search_status'] = '1,2';
		}

		$filters = lmdbadvancedproject_normalize_budget_report_filters($filters);
		if (lmdbadvancedproject_budget_report_content_period_is_active($filters)) {
			$dateParameters = array(
				'date_start' => 'search_date_start',
				'date_end' => 'search_date_end',
			);
			foreach ($dateParameters as $filterKey => $parameterPrefix) {
				if (!empty($filters[$filterKey]) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $filters[$filterKey], $matches)) {
					$params[$parameterPrefix.'year'] = (int) $matches[1];
					$params[$parameterPrefix.'month'] = (int) $matches[2];
					$params[$parameterPrefix.'day'] = (int) $matches[3];
				}
			}
		}

		return DOL_URL_ROOT.'/compta/facture/list.php?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
	}
}

if (!function_exists('lmdbadvancedproject_get_customer_invoice_summary_html')) {
	/**
	 * Render an invoiced amount linked to the native invoice list with detail tooltip.
	 *
	 * @param  Form                $form       Dolibarr form helper
	 * @param  string              $projectRef Project reference
	 * @param  float|int           $invoiced   Project-attributed invoiced amount
	 * @param  float|int           $orders     Project orders amount
	 * @param  list<array{id:int,ref:string,type:int,date:string,amount:float}> $invoiceDetails Invoice contributions
	 * @param  array<string,mixed> $filters    Normalized report filters
	 * @return string
	 */
	function lmdbadvancedproject_get_customer_invoice_summary_html($form, $projectRef, $invoiced, $orders, $invoiceDetails, $filters)
	{
		global $db, $langs, $user;

		$formattedSummary = lmdbadvancedproject_format_price($invoiced).' ('.lmdbadvancedproject_format_percentage($invoiced, $orders).')';
		if (!$user->hasRight('facture', 'read')) {
			return $formattedSummary;
		}

		$listUrl = lmdbadvancedproject_get_customer_invoice_list_url($projectRef, $filters);
		$summaryLink = '<a href="'.dol_escape_htmltag($listUrl).'">'.$formattedSummary.'</a>';
		if (empty($invoiceDetails)) {
			return $summaryLink;
		}

		$tooltip = '<table class="nobordernopadding">';
		$tooltip .= '<tr class="liste_titre"><th>'.$langs->trans('BudgetReportInvoices').'</th><th>'.$langs->trans('Date').'</th><th class="right">'.$langs->trans('BudgetReportInvoiceAttributedAmount').'</th></tr>';
		foreach ($invoiceDetails as $invoiceDetail) {
			$invoiceStatic = new Facture($db);
			$invoiceStatic->id = (int) $invoiceDetail['id'];
			$invoiceStatic->ref = (string) $invoiceDetail['ref'];
			$invoiceStatic->type = (int) $invoiceDetail['type'];

			$tooltip .= '<tr>';
			$tooltip .= '<td>'.$invoiceStatic->getNomUrl(1, '', 0, 0, '', 1).'</td>';
			$tooltip .= '<td class="nowrap">'.lmdbadvancedproject_format_date($invoiceDetail['date']).'</td>';
			$tooltip .= '<td class="right nowrap">'.lmdbadvancedproject_format_price($invoiceDetail['amount']).'</td>';
			$tooltip .= '</tr>';
		}
		$tooltip .= '</table>';

		return $form->textwithtooltip($summaryLink, $tooltip, 1, 0, '', '', 3, '', 1);
	}
}

if (!function_exists('lmdbadvancedproject_get_budget_report_request_date')) {
	/**
	 * Read a native Dolibarr date selector while keeping legacy ISO query links compatible.
	 *
	 * @param  string $prefix Request parameter prefix
	 * @return string
	 */
	function lmdbadvancedproject_get_budget_report_request_date($prefix)
	{
		$timestamp = GETPOSTDATE($prefix);
		if ($timestamp > 0) {
			return dol_print_date($timestamp, '%Y-%m-%d', 'tzuserrel');
		}

		return (string) GETPOST($prefix, 'alpha');
	}
}

if (!function_exists('lmdbadvancedproject_build_project_status_sql_filter')) {
	/**
	 * Build the SQL filter for project status.
	 *
	 * @param  string $projectStatus open|closed|both
	 * @return string
	 */
	function lmdbadvancedproject_build_project_status_sql_filter($projectStatus)
	{
		if ($projectStatus === 'closed') {
			return ' AND p.fk_statut = 2';
		}
		if ($projectStatus === 'both') {
			return ' AND p.fk_statut IN (1,2)';
		}

		return ' AND p.fk_statut = 1';
	}
}

if (!function_exists('lmdbadvancedproject_build_project_date_sql_filter')) {
	/**
	 * Build the SQL overlap filter for project dates.
	 *
	 * @param  array<string,mixed> $filters Normalized filters
	 * @return string
	 */
	function lmdbadvancedproject_build_project_date_sql_filter($filters)
	{
		global $db;

		$dateStart = empty($filters['date_start']) ? '' : $filters['date_start'];
		$dateEnd = empty($filters['date_end']) ? '' : $filters['date_end'];
		$ignoreStartedBefore = !empty($filters['ignore_started_before']) && $filters['ignore_started_before'] === '1';
		$ignoreEndedAfter = !empty($filters['ignore_ended_after']) && $filters['ignore_ended_after'] === '1';
		if ($dateStart === '' && $dateEnd === '') {
			return '';
		}

		$conditions = array(
			"p.dateo IS NOT NULL",
			"p.dateo <> '0000-00-00'",
			"p.dateo <> '0000-00-00 00:00:00'",
		);

		if ($dateEnd !== '') {
			$conditions[] = "DATE(p.dateo) <= '".$db->escape($dateEnd)."'";
		}

		if ($dateStart !== '') {
			$conditions[] = "(p.datee IS NULL OR p.datee = '0000-00-00' OR p.datee = '0000-00-00 00:00:00' OR p.datee < p.dateo OR DATE(p.datee) >= '".$db->escape($dateStart)."')";
		}

		if ($ignoreStartedBefore && $dateStart !== '') {
			$conditions[] = "DATE(p.dateo) >= '".$db->escape($dateStart)."'";
		}

		if ($ignoreEndedAfter && $dateEnd !== '') {
			$conditions[] = "p.datee IS NOT NULL";
			$conditions[] = "p.datee <> '0000-00-00'";
			$conditions[] = "p.datee <> '0000-00-00 00:00:00'";
			$conditions[] = "DATE(p.datee) >= DATE(p.dateo)";
			$conditions[] = "DATE(p.datee) <= '".$db->escape($dateEnd)."'";
		}

		return ' AND '.implode(' AND ', $conditions);
	}
}

if (!function_exists('lmdbadvancedproject_budget_report_content_period_is_active')) {
	/**
	 * Check whether dated report content must be restricted to the observation period.
	 *
	 * @param  array<string,mixed> $filters Normalized filters
	 * @return bool
	 */
	function lmdbadvancedproject_budget_report_content_period_is_active($filters)
	{
		return !empty($filters['exclude_content_outside_period'])
			&& $filters['exclude_content_outside_period'] === '1'
			&& (!empty($filters['date_start']) || !empty($filters['date_end']));
	}
}

if (!function_exists('lmdbadvancedproject_build_content_date_sql_condition')) {
	/**
	 * Build an inclusive SQL condition for one dated report content source.
	 *
	 * The date expression is supplied only by module code and must never contain request data.
	 *
	 * @param  string               $dateExpression Trusted SQL date expression
	 * @param  array<string,mixed>  $filters        Normalized filters
	 * @return string
	 */
	function lmdbadvancedproject_build_content_date_sql_condition($dateExpression, $filters)
	{
		global $db;

		if (!lmdbadvancedproject_budget_report_content_period_is_active($filters)) {
			return '1 = 1';
		}

		$conditions = array('('.$dateExpression.') IS NOT NULL');
		if (!empty($filters['date_start'])) {
			$conditions[] = "DATE(".$dateExpression.") >= '".$db->escape($filters['date_start'])."'";
		}
		if (!empty($filters['date_end'])) {
			$conditions[] = "DATE(".$dateExpression.") <= '".$db->escape($filters['date_end'])."'";
		}

		return implode(' AND ', $conditions);
	}
}

if (!function_exists('lmdbadvancedproject_print_budget_report_filters')) {
	/**
	 * Print the global budget report filter form.
	 *
	 * @param  array<string,mixed> $filters Normalized filters
	 * @return void
	 */
	function lmdbadvancedproject_print_budget_report_filters($filters)
	{
		global $db, $langs;

		$filters = lmdbadvancedproject_normalize_budget_report_filters($filters);
		$form = new Form($db);
		$action = dol_buildpath('/lmdbadvancedproject/budgetreportindex.php', 1);
		$statusOptions = array(
			'open' => 'BudgetReportStatusOpen',
			'closed' => 'BudgetReportStatusClosed',
			'both' => 'BudgetReportStatusBoth',
		);
		$dateStartTooltip = $form->textwithtooltip('', $langs->trans('BudgetReportFilterDateStartHelp'), 2, 1, img_info(''), '', 3);
		$dateEndTooltip = $form->textwithtooltip('', $langs->trans('BudgetReportFilterDateEndHelp'), 2, 1, img_info(''), '', 3);
		$ignoreStartedTooltip = $form->textwithtooltip('', $langs->trans('BudgetReportIgnoreStartedBeforeHelp'), 2, 1, img_info(''), '', 3);
		$ignoreEndedTooltip = $form->textwithtooltip('', $langs->trans('BudgetReportIgnoreEndedAfterHelp'), 2, 1, img_info(''), '', 3);
		$contentPeriodTooltip = $form->textwithtooltip('', $langs->trans('BudgetReportExcludeContentOutsidePeriodHelp'), 2, 1, img_info(''), '', 3);
		$statusTooltip = $form->textwithtooltip('', $langs->trans('BudgetReportFilterStatusHelp'), 2, 1, img_info(''), '', 3);
		$projectsTooltip = $form->textwithtooltip('', $langs->trans('BudgetReportFilterProjectsHelp'), 2, 1, img_info(''), '', 3);
		$projectOptions = lmdbadvancedproject_get_budget_report_project_options();
		$selectedProjectIds = array_map('strval', $filters['project_ids']);

		print '<form method="GET" action="'.lmdbadvancedproject_escape_html($action).'" class="budgetreport-filters">';
		print '<div class="budgetreport-filter-title">'.$langs->trans('BudgetReportFilters').'</div>';
		print '<div class="budgetreport-filter-fields">';
		print '<div class="budgetreport-filter-period">';
		print '<div class="budgetreport-filter-period-title">'.$langs->trans('BudgetReportObservationPeriod').'</div>';
		print '<div class="budgetreport-filter-field budgetreport-filter-date-field">';
		print '<label><span>'.$langs->trans('BudgetReportFilterDateStart').' '.$dateStartTooltip.'</span>'.$form->selectDate($filters['date_start'] === '' ? -1 : $filters['date_start'], 'date_start', 0, 0, 1, '', 1, 0).'</label>';
		print '<label class="budgetreport-filter-binary"><span>'.$langs->trans('BudgetReportIgnoreStartedBefore').' '.$ignoreStartedTooltip.'</span>'.$form->selectyesno('ignore_started_before', $filters['ignore_started_before'], 1, false, 0, 1).'</label>';
		print '</div>';
		print '<div class="budgetreport-filter-field budgetreport-filter-date-field">';
		print '<label><span>'.$langs->trans('BudgetReportFilterDateEnd').' '.$dateEndTooltip.'</span>'.$form->selectDate($filters['date_end'] === '' ? -1 : $filters['date_end'], 'date_end', 0, 0, 1, '', 1, 0).'</label>';
		print '<label class="budgetreport-filter-binary"><span>'.$langs->trans('BudgetReportIgnoreEndedAfter').' '.$ignoreEndedTooltip.'</span>'.$form->selectyesno('ignore_ended_after', $filters['ignore_ended_after'], 1, false, 0, 1).'</label>';
		print '</div>';
		print '</div>';
		print '<label class="budgetreport-filter-field"><span>'.$langs->trans('BudgetReportExcludeContentOutsidePeriod').' '.$contentPeriodTooltip.'</span>'.$form->selectyesno('exclude_content_outside_period', $filters['exclude_content_outside_period'], 1, false, 0, 1).'</label>';
		print '<label class="budgetreport-filter-field"><span>'.$langs->trans('BudgetReportFilterStatus').' '.$statusTooltip.'</span><select class="flat" id="project_status" name="project_status">';
		foreach ($statusOptions as $value => $labelKey) {
			$selected = ($filters['project_status'] === $value) ? ' selected="selected"' : '';
			print '<option value="'.lmdbadvancedproject_escape_html($value).'"'.$selected.'>'.$langs->trans($labelKey).'</option>';
		}
		print '</select></label>';
		print ajax_combobox('project_status');
		print '<label class="budgetreport-filter-field"><span>'.$langs->trans('BudgetReportFilterProjects').' '.$projectsTooltip.'</span>';
		print $form->multiselectarray('project_ids', $projectOptions, $selectedProjectIds, 0, 0, 'minwidth300', 0, 320, '', '', $langs->trans('BudgetReportAllProjects'), 1);
		print '</label>';
		print '<div class="budgetreport-filter-actions">';
		print '<button type="submit" class="button">'.$langs->trans('BudgetReportApplyFilters').'</button>';
		print '<a class="button" href="'.lmdbadvancedproject_escape_html($action).'">'.$langs->trans('BudgetReportResetFilters').'</a>';
		print '</div>';
		print '</div>';
		print '</form>';
	}
}

if (!function_exists('lmdbadvancedproject_full_line_label')) {
	/**
	 * Return a normalized full label for a document line.
	 *
	 * @param  string $label       Line label
	 * @param  string $description Line description
	 * @return string
	 */
	function lmdbadvancedproject_full_line_label($label, $description)
	{
		$text = trim((string) $label);
		if ($text === '') {
			$text = trim(strip_tags((string) $description));
		}
		if ($text === '') {
			return '-';
		}

		$text = str_replace(array('\\r\\n', '\\n', '\\r'), "\n", $text);
		$text = str_replace(array("\r\n", "\r"), "\n", $text);

		$lines = array();
		foreach (explode("\n", $text) as $line) {
			$line = trim(preg_replace('/[ \t]+/', ' ', $line));
			if ($line !== '') {
				$lines[] = $line;
			}
		}

		$label = implode(' ', $lines);
		return $label === '' ? '-' : $label;
	}
}

if (!function_exists('lmdbadvancedproject_short_line_label')) {
	/**
	 * Return a compact label for a document line.
	 *
	 * @param  string $label       Line label
	 * @param  string $description Line description
	 * @return string
	 */
	function lmdbadvancedproject_short_line_label($label, $description)
	{
		$text = lmdbadvancedproject_full_line_label($label, $description);
		if (function_exists('dol_trunc')) {
			return dol_trunc($text, 50);
		}
		if (strlen($text) > 50) {
			return substr($text, 0, 47).'...';
		}

		return $text;
	}
}

if (!function_exists('lmdbadvancedproject_build_category_sql_parts')) {
	/**
	 * Build SQL fragments used to resolve commercial categories from product or line extrafields.
	 *
	 * @param  string $lineExtraTable Line extrafields table without prefix
	 * @param  string $lineAlias      SQL alias of the line table
	 * @return array<string,string>
	 */
	function lmdbadvancedproject_build_category_sql_parts($lineExtraTable, $lineAlias)
	{
		$parts = array(
			'select' => 'NULL AS product_category_key, NULL AS product_category_label, NULL AS line_category_key, NULL AS line_category_label',
			'join' => '',
		);

		if (!lmdbadvancedproject_table_exists(MAIN_DB_PREFIX.'c_commercial_category')) {
			return $parts;
		}

		$select = array(
			'NULL AS product_category_key',
			'NULL AS product_category_label',
			'NULL AS line_category_key',
			'NULL AS line_category_label',
		);
		$join = '';

		if (lmdbadvancedproject_table_exists(MAIN_DB_PREFIX.'product_extrafields') && lmdbadvancedproject_column_exists(MAIN_DB_PREFIX.'product_extrafields', 'lmdb_commercial_category')) {
			$join .= ' LEFT JOIN '.MAIN_DB_PREFIX.'product_extrafields pex ON pex.fk_object = '.$lineAlias.'.fk_product';
			$join .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_commercial_category pc ON (pc.rowid = pex.lmdb_commercial_category OR BINARY pc.code = BINARY pex.lmdb_commercial_category)';
			$select[0] = 'pc.rowid AS product_category_key';
			$select[1] = 'pc.label AS product_category_label';
		}

		if (lmdbadvancedproject_table_exists(MAIN_DB_PREFIX.$lineExtraTable) && lmdbadvancedproject_column_exists(MAIN_DB_PREFIX.$lineExtraTable, 'lmdb_commercial_category')) {
			$join .= ' LEFT JOIN '.MAIN_DB_PREFIX.$lineExtraTable.' lex ON lex.fk_object = '.$lineAlias.'.rowid';
			$join .= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_commercial_category lc ON (lc.rowid = lex.lmdb_commercial_category OR BINARY lc.code = BINARY lex.lmdb_commercial_category)';
			$select[2] = 'lc.rowid AS line_category_key';
			$select[3] = 'lc.label AS line_category_label';
		}

		$parts['select'] = implode(', ', $select);
		$parts['join'] = $join;

		return $parts;
	}
}

if (!function_exists('lmdbadvancedproject_get_linked_supplier_invoice_sql')) {
	/**
	 * Build SQL that returns invoiced supplier order amounts without double counting bidirectional links.
	 *
	 * @param  string $supplierInvoiceEntities Supplier invoice entity filter
	 * @return string
	 */
	function lmdbadvancedproject_get_linked_supplier_invoice_sql($supplierInvoiceEntities)
	{
		return "SELECT linked.order_id, SUM(linked.total_ht) AS invoiced_ht
			FROM (
				SELECT DISTINCT ee.fk_source AS order_id, ff.rowid AS invoice_id, ff.total_ht
				FROM ".MAIN_DB_PREFIX."element_element ee
				INNER JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = ee.fk_target
				WHERE ee.sourcetype IN ('order_supplier', 'supplier_order')
				AND ee.targettype IN ('invoice_supplier', 'supplier_invoice')
				AND ff.fk_statut IN (1,2)
				AND ff.entity IN (".$supplierInvoiceEntities.")
				UNION
				SELECT DISTINCT ee.fk_target AS order_id, ff.rowid AS invoice_id, ff.total_ht
				FROM ".MAIN_DB_PREFIX."element_element ee
				INNER JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = ee.fk_source
				WHERE ee.targettype IN ('order_supplier', 'supplier_order')
				AND ee.sourcetype IN ('invoice_supplier', 'supplier_invoice')
				AND ff.fk_statut IN (1,2)
				AND ff.entity IN (".$supplierInvoiceEntities.")
			) linked
			GROUP BY linked.order_id";
	}
}

if (!function_exists('lmdbadvancedproject_supplier_invoice_split_source_exclusion_sql')) {
	/**
	 * Exclude source supplier invoice lines that already have project split parts.
	 *
	 * @param  string $invoiceAlias            Supplier invoice table alias
	 * @param  string $lineAlias               Supplier invoice line table alias
	 * @param  string $supplierInvoiceEntities Supplier invoice entity filter
	 * @return string
	 */
	function lmdbadvancedproject_supplier_invoice_split_source_exclusion_sql($invoiceAlias, $lineAlias, $supplierInvoiceEntities)
	{
		if (!lmdbadvancedproject_supplier_invoice_split_report_enabled()) {
			return '';
		}

		return " AND NOT EXISTS (
			SELECT 1
			FROM ".MAIN_DB_PREFIX."lmdbadvancedproject_supplier_invoice_parts sipx
			WHERE sipx.fk_facture_fourn_det = ".$lineAlias.".rowid
			AND sipx.fk_facture_fourn = ".$invoiceAlias.".rowid
			AND sipx.entity = ".$invoiceAlias.".entity
			AND sipx.entity IN (".$supplierInvoiceEntities.")
		)";
	}
}

if (!function_exists('lmdbadvancedproject_supplier_order_split_source_exclusion_sql')) {
	/**
	 * Exclude supplier orders linked to supplier invoices that contain split parts.
	 *
	 * @param  string $orderAlias              Supplier order table alias
	 * @param  string $supplierInvoiceEntities Supplier invoice entity filter
	 * @return string
	 */
	function lmdbadvancedproject_supplier_order_split_source_exclusion_sql($orderAlias, $supplierInvoiceEntities)
	{
		if (!lmdbadvancedproject_supplier_invoice_split_report_enabled()) {
			return '';
		}

		$partTable = MAIN_DB_PREFIX.'lmdbadvancedproject_supplier_invoice_parts';
		$elementTable = MAIN_DB_PREFIX.'element_element';
		$invoiceTable = MAIN_DB_PREFIX.'facture_fourn';

		return " AND NOT EXISTS (
			SELECT 1
			FROM ".$elementTable." ees
			INNER JOIN ".$invoiceTable." ffs ON ffs.rowid = ees.fk_target
			INNER JOIN ".$partTable." sipx ON sipx.fk_facture_fourn = ffs.rowid
			WHERE ees.fk_source = ".$orderAlias.".rowid
			AND ees.sourcetype IN ('order_supplier', 'supplier_order')
			AND ees.targettype IN ('invoice_supplier', 'supplier_invoice')
			AND ffs.fk_statut IN (1,2)
			AND ffs.entity IN (".$supplierInvoiceEntities.")
			AND sipx.entity = ffs.entity
			AND sipx.entity IN (".$supplierInvoiceEntities.")
		) AND NOT EXISTS (
			SELECT 1
			FROM ".$elementTable." eet
			INNER JOIN ".$invoiceTable." fft ON fft.rowid = eet.fk_source
			INNER JOIN ".$partTable." sipy ON sipy.fk_facture_fourn = fft.rowid
			WHERE eet.fk_target = ".$orderAlias.".rowid
			AND eet.targettype IN ('order_supplier', 'supplier_order')
			AND eet.sourcetype IN ('invoice_supplier', 'supplier_invoice')
			AND fft.fk_statut IN (1,2)
			AND fft.entity IN (".$supplierInvoiceEntities.")
			AND sipy.entity = fft.entity
			AND sipy.entity IN (".$supplierInvoiceEntities.")
		)";
	}
}

if (!function_exists('lmdbadvancedproject_supplier_order_remaining_line_expression')) {
	/**
	 * Return the SQL expression used for remaining supplier order line amounts.
	 *
	 * @return string
	 */
	function lmdbadvancedproject_supplier_order_remaining_line_expression()
	{
		return "CASE
			WHEN COALESCE(cf.total_ht, 0) > 0 THEN COALESCE(cfd.total_ht, 0) * GREATEST(COALESCE(cf.total_ht, 0) - COALESCE(inv.invoiced_ht, 0), 0) / COALESCE(cf.total_ht, 0)
			ELSE 0
		END";
	}
}

if (!function_exists('lmdbadvancedproject_init_forecast')) {
	/**
	 * Initialize forecast structure.
	 *
	 * @return array<string,mixed>
	 */
	function lmdbadvancedproject_init_forecast()
	{
		return array(
			'categories' => array(),
			'totals' => array(
				'order_amount' => 0,
				'order_budget' => 0,
				'supplier_expenses' => 0,
				'forecast_gap' => 0,
			),
			'time' => array(
				'hours' => 0,
				'cost' => 0,
				'contributors' => 0,
				'lines' => array(),
			),
			'expenses' => array(
				'total' => 0,
				'lines' => array(),
			),
		);
	}
}

if (!function_exists('lmdbadvancedproject_get_forecast_category')) {
	/**
	 * Resolve category key and label from a SQL row.
	 *
	 * @param  stdClass $obj SQL row
	 * @return array<string,string>
	 */
	function lmdbadvancedproject_get_forecast_category($obj)
	{
		global $langs;

		$productKey = empty($obj->product_category_key) ? '' : (string) $obj->product_category_key;
		$productLabel = empty($obj->product_category_label) ? '' : (string) $obj->product_category_label;
		$lineKey = empty($obj->line_category_key) ? '' : (string) $obj->line_category_key;
		$lineLabel = empty($obj->line_category_label) ? '' : (string) $obj->line_category_label;
		$fkProduct = empty($obj->fk_product) ? 0 : (int) $obj->fk_product;

		if ($fkProduct > 0 && $productKey !== '') {
			return array('key' => 'cat_'.$productKey, 'label' => $productLabel, 'translation_key' => '');
		}
		if ($fkProduct <= 0 && $lineKey !== '') {
			return array('key' => 'cat_'.$lineKey, 'label' => $lineLabel, 'translation_key' => '');
		}
		if ($productKey !== '') {
			return array('key' => 'cat_'.$productKey, 'label' => $productLabel, 'translation_key' => '');
		}
		if ($lineKey !== '') {
			return array('key' => 'cat_'.$lineKey, 'label' => $lineLabel, 'translation_key' => '');
		}

		return array('key' => 'uncategorized', 'label' => $langs->trans('BudgetReportUncategorized'), 'translation_key' => 'BudgetReportUncategorized');
	}
}

if (!function_exists('lmdbadvancedproject_get_forecast_category_label')) {
	/**
	 * Resolve a forecast category label in the requested output language.
	 *
	 * @param string              $categoryKey Category identifier
	 * @param array<string,mixed> $category Category data
	 * @param Translate|null      $outputlangs Output language
	 * @return string
	 */
	function lmdbadvancedproject_get_forecast_category_label($categoryKey, $category, $outputlangs = null)
	{
		global $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$translationKey = empty($category['translation_key']) ? '' : (string) $category['translation_key'];
		if ($translationKey === '' && (string) $categoryKey === 'uncategorized') {
			$translationKey = 'BudgetReportUncategorized';
		}
		if ($translationKey !== '') {
			return $outputlangs->transnoentities($translationKey);
		}

		return empty($category['label']) ? '' : (string) $category['label'];
	}
}

if (!function_exists('lmdbadvancedproject_add_forecast_line')) {
	/**
	 * Add a line amount to a forecast category.
	 *
	 * @param array<string,mixed> $forecast Forecast data
	 * @param stdClass           $obj      SQL row
	 * @param string             $type     customer_order|supplier_invoice|supplier_order|supplier_order_ordered|supplier_order_delivered
	 * @return void
	 */
	function lmdbadvancedproject_add_forecast_line(&$forecast, $obj, $type)
	{
		$category = lmdbadvancedproject_get_forecast_category($obj);
		$key = $category['key'];

		if (!isset($forecast['categories'][$key])) {
			$forecast['categories'][$key] = array(
				'label' => $category['label'],
				'translation_key' => $category['translation_key'],
				'order_amount' => 0,
				'order_budget' => 0,
				'supplier_expenses' => 0,
				'order_lines' => array(),
				'supplier_lines' => array(),
			);
		}

		$amount = empty($obj->amount_ht) ? 0 : (float) $obj->amount_ht;
		$budget = empty($obj->budget_ht) ? 0 : (float) $obj->budget_ht;
		$fullLabel = lmdbadvancedproject_full_line_label(empty($obj->line_label) ? '' : $obj->line_label, empty($obj->line_description) ? '' : $obj->line_description);
		$line = array(
			'type' => $type,
			'document_id' => empty($obj->document_id) ? 0 : (int) $obj->document_id,
			'ref' => empty($obj->document_ref) ? '' : (string) $obj->document_ref,
			'date' => empty($obj->document_date) ? '' : (string) $obj->document_date,
			'label' => lmdbadvancedproject_short_line_label(empty($obj->line_label) ? '' : $obj->line_label, empty($obj->line_description) ? '' : $obj->line_description),
			'label_full' => $fullLabel,
			'document_status' => isset($obj->document_status) ? (int) $obj->document_status : null,
			'document_paid' => isset($obj->document_paid) ? (int) $obj->document_paid : 0,
			'document_billed' => isset($obj->document_billed) ? (int) $obj->document_billed : 0,
			'qty' => empty($obj->qty) ? 0 : (float) $obj->qty,
			'amount' => $amount,
			'budget' => $budget,
		);

		if ($type === 'customer_order') {
			$forecast['categories'][$key]['order_amount'] += $amount;
			$forecast['categories'][$key]['order_budget'] += $budget;
			$forecast['categories'][$key]['order_lines'][] = $line;
			$forecast['totals']['order_amount'] += $amount;
			$forecast['totals']['order_budget'] += $budget;
		} else {
			$forecast['categories'][$key]['supplier_expenses'] += $amount;
			$forecast['categories'][$key]['supplier_lines'][] = $line;
			$forecast['totals']['supplier_expenses'] += $amount;
		}
	}
}

if (!function_exists('lmdbadvancedproject_load_project_forecast')) {
	/**
	 * Load forecast data for a single project.
	 *
	 * @param  int    $projectId               Project id
	 * @param  string $projectEntities         Project entity filter
	 * @param  string $orderEntities           Customer order entity filter
	 * @param  string $supplierInvoiceEntities Supplier invoice entity filter
	 * @param  string $supplierOrderEntities   Supplier order entity filter
	 * @param  string $expenseReportEntities   Expense report entity filter
	 * @return array<string,mixed>
	 */
	function lmdbadvancedproject_load_project_forecast($projectId, $projectEntities, $orderEntities, $supplierInvoiceEntities, $supplierOrderEntities, $expenseReportEntities)
	{
		global $db;

		$forecast = lmdbadvancedproject_init_forecast();
		$projectId = (int) $projectId;

		$categorySql = lmdbadvancedproject_build_category_sql_parts('commandedet_extrafields', 'cd');
		$sql = "SELECT 'customer_order' AS source_type, c.rowid AS document_id, c.ref AS document_ref, c.date_commande AS document_date, c.fk_statut AS document_status, 0 AS document_paid, 0 AS document_billed, cd.fk_product, cd.label AS line_label, cd.description AS line_description, cd.qty, cd.total_ht AS amount_ht, (COALESCE(cd.buy_price_ht, 0) * COALESCE(cd.qty, 0)) AS budget_ht, ".$categorySql['select']."
			FROM ".MAIN_DB_PREFIX."commande c
			INNER JOIN ".MAIN_DB_PREFIX."commandedet cd ON cd.fk_commande = c.rowid
			".$categorySql['join']."
			WHERE c.fk_projet = ".$projectId." AND c.fk_statut > 0 AND c.entity IN (".$orderEntities.") AND cd.product_type IN (0,1)";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				lmdbadvancedproject_add_forecast_line($forecast, $obj, 'customer_order');
			}
			$db->free($resql);
		}

		$supplierInvoiceSplitEnabled = lmdbadvancedproject_supplier_invoice_split_report_enabled();
		$supplierInvoiceSplitExclusion = lmdbadvancedproject_supplier_invoice_split_source_exclusion_sql('ff', 'ffd', $supplierInvoiceEntities);

		$categorySql = lmdbadvancedproject_build_category_sql_parts('facture_fourn_det_extrafields', 'ffd');
		$sql = "SELECT 'supplier_invoice' AS source_type, ff.rowid AS document_id, ff.ref AS document_ref, ff.datef AS document_date, ff.fk_statut AS document_status, ff.paye AS document_paid, 0 AS document_billed, ffd.fk_product, ffd.label AS line_label, ffd.description AS line_description, ffd.qty, ffd.total_ht AS amount_ht, 0 AS budget_ht, ".$categorySql['select']."
			FROM ".MAIN_DB_PREFIX."facture_fourn ff
			INNER JOIN ".MAIN_DB_PREFIX."facture_fourn_det ffd ON ffd.fk_facture_fourn = ff.rowid
			".$categorySql['join']."
			WHERE ff.fk_projet = ".$projectId." AND ff.fk_statut IN (1,2) AND ff.entity IN (".$supplierInvoiceEntities.") AND ffd.product_type IN (0,1)".$supplierInvoiceSplitExclusion;
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				lmdbadvancedproject_add_forecast_line($forecast, $obj, 'supplier_invoice');
			}
			$db->free($resql);
		}

		if ($supplierInvoiceSplitEnabled) {
			$categorySql = lmdbadvancedproject_build_category_sql_parts('facture_fourn_det_extrafields', 'ffd');
			$sql = "SELECT 'supplier_invoice' AS source_type, ff.rowid AS document_id, ff.ref AS document_ref, sip.date AS document_date, ff.fk_statut AS document_status, ff.paye AS document_paid, 0 AS document_billed, ffd.fk_product, ffd.label AS line_label, ffd.description AS line_description, sip.qty, sip.total_ht AS amount_ht, 0 AS budget_ht, ".$categorySql['select']."
				FROM ".MAIN_DB_PREFIX."lmdbadvancedproject_supplier_invoice_parts sip
				INNER JOIN ".MAIN_DB_PREFIX."facture_fourn_det ffd ON ffd.rowid = sip.fk_facture_fourn_det
				INNER JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = sip.fk_facture_fourn
				".$categorySql['join']."
				WHERE sip.fk_projet = ".$projectId."
				AND ff.fk_statut IN (1,2)
				AND ff.entity IN (".$supplierInvoiceEntities.")
				AND sip.entity IN (".$supplierInvoiceEntities.")
				AND ffd.product_type IN (0,1)";
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					lmdbadvancedproject_add_forecast_line($forecast, $obj, 'supplier_invoice');
				}
				$db->free($resql);
			}
		}

		$categorySql = lmdbadvancedproject_build_category_sql_parts('commande_fournisseurdet_extrafields', 'cfd');
		$linkedSupplierInvoiceSql = lmdbadvancedproject_get_linked_supplier_invoice_sql($supplierInvoiceEntities);
		$supplierOrderRemainingExpression = lmdbadvancedproject_supplier_order_remaining_line_expression();
		$supplierOrderSplitExclusion = lmdbadvancedproject_supplier_order_split_source_exclusion_sql('cf', $supplierInvoiceEntities);
		$sql = "SELECT CASE WHEN cf.fk_statut = 3 THEN 'supplier_order_ordered' ELSE 'supplier_order_delivered' END AS source_type, cf.rowid AS document_id, cf.ref AS document_ref, COALESCE(cf.date_commande, DATE(cf.date_creation)) AS document_date, cf.fk_statut AS document_status, 0 AS document_paid, COALESCE(cf.billed, 0) AS document_billed, cfd.fk_product, cfd.label AS line_label, cfd.description AS line_description, cfd.qty,
				".$supplierOrderRemainingExpression." AS amount_ht,
				0 AS budget_ht, ".$categorySql['select']."
			FROM ".MAIN_DB_PREFIX."commande_fournisseur cf
			INNER JOIN ".MAIN_DB_PREFIX."commande_fournisseurdet cfd ON cfd.fk_commande = cf.rowid
			LEFT JOIN (".$linkedSupplierInvoiceSql.") inv ON inv.order_id = cf.rowid
			".$categorySql['join']."
			WHERE cf.fk_projet = ".$projectId."
			AND cf.fk_statut IN (3,4,5)
			AND COALESCE(cf.billed, 0) = 0
			AND cf.entity IN (".$supplierOrderEntities.")
			AND cfd.product_type IN (0,1)
			".$supplierOrderSplitExclusion."
			HAVING amount_ht > 0";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$sourceType = empty($obj->source_type) ? 'supplier_order' : (string) $obj->source_type;
				lmdbadvancedproject_add_forecast_line($forecast, $obj, $sourceType);
			}
			$db->free($resql);
		}

		$sql = "SELECT pt.rowid AS task_id, pt.ref AS task_ref, pt.label AS task_label, ptt.fk_user AS contributor_user_id,
				SUM(ptt.element_duration) / 3600.0 AS total_hours,
				SUM((ptt.element_duration / 3600.0) * CASE
				WHEN ptt.thm IS NOT NULL AND ptt.thm > 0 THEN ptt.thm
				WHEN u.thm IS NOT NULL AND u.thm > 0 THEN u.thm
				ELSE 0
			END) AS total_cost
			FROM ".MAIN_DB_PREFIX."element_time ptt
			INNER JOIN ".MAIN_DB_PREFIX."projet_task pt ON ptt.fk_element = pt.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = ptt.fk_user
			WHERE ptt.elementtype = 'task' AND ptt.element_duration > 0 AND pt.fk_projet = ".$projectId." AND pt.entity IN (".$projectEntities.")
			GROUP BY pt.rowid, pt.ref, pt.label, ptt.fk_user
			ORDER BY pt.ref ASC, pt.label ASC, ptt.fk_user ASC";
		$resql = $db->query($sql);
		if ($resql) {
			$timeLineIndexes = array();
			$projectContributors = array();
			while ($obj = $db->fetch_object($resql)) {
				$taskId = empty($obj->task_id) ? 0 : (int) $obj->task_id;
				$hours = empty($obj->total_hours) ? 0 : (float) $obj->total_hours;
				$cost = empty($obj->total_cost) ? 0 : (float) $obj->total_cost;
				$contributorUserId = empty($obj->contributor_user_id) ? 0 : (int) $obj->contributor_user_id;
				$forecast['time']['hours'] += $hours;
				$forecast['time']['cost'] += $cost;
				if (!isset($timeLineIndexes[$taskId])) {
					$timeLineIndexes[$taskId] = count($forecast['time']['lines']);
					$forecast['time']['lines'][] = array(
						'task_id' => $taskId,
						'task_ref' => empty($obj->task_ref) ? '' : (string) $obj->task_ref,
						'task_label' => empty($obj->task_label) ? '' : (string) $obj->task_label,
						'contributors' => 0,
						'contributor_ids' => array(),
						'hours' => 0.0,
						'cost' => 0.0,
					);
				}
				$lineIndex = $timeLineIndexes[$taskId];
				$forecast['time']['lines'][$lineIndex]['hours'] += $hours;
				$forecast['time']['lines'][$lineIndex]['cost'] += $cost;
				if ($contributorUserId > 0) {
					$forecast['time']['lines'][$lineIndex]['contributor_ids'][$contributorUserId] = true;
					$projectContributors[$contributorUserId] = true;
				}
			}
			$db->free($resql);
			foreach ($forecast['time']['lines'] as $lineIndex => $timeLine) {
				$forecast['time']['lines'][$lineIndex]['contributors'] = count($timeLine['contributor_ids']);
				unset($forecast['time']['lines'][$lineIndex]['contributor_ids']);
			}
			$forecast['time']['contributors'] = count($projectContributors);
		}

		$sql = "SELECT ex.rowid AS expense_id, ex.ref, ex.fk_user_author AS user_id, eu.firstname AS user_firstname, eu.lastname AS user_lastname, eu.login AS user_login, ed.date, ed.comments, ed.total_ht
			FROM ".MAIN_DB_PREFIX."expensereport_det ed
			LEFT JOIN ".MAIN_DB_PREFIX."expensereport ex ON ed.fk_expensereport = ex.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."user eu ON eu.rowid = ex.fk_user_author
			WHERE ed.fk_projet = ".$projectId." AND ex.fk_user_approve > 0 AND ex.entity IN (".$expenseReportEntities.")
			ORDER BY ed.date ASC, ex.ref ASC";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$amount = empty($obj->total_ht) ? 0 : (float) $obj->total_ht;
				$userName = dolGetFirstLastname(empty($obj->user_firstname) ? '' : $obj->user_firstname, empty($obj->user_lastname) ? '' : $obj->user_lastname);
				if ($userName === '') {
					$userName = empty($obj->user_login) ? '' : (string) $obj->user_login;
				}
				$forecast['expenses']['total'] += $amount;
				$forecast['expenses']['lines'][] = array(
					'id' => empty($obj->expense_id) ? 0 : (int) $obj->expense_id,
					'ref' => empty($obj->ref) ? '' : (string) $obj->ref,
					'user_id' => empty($obj->user_id) ? 0 : (int) $obj->user_id,
					'user_name' => $userName,
					'user_firstname' => empty($obj->user_firstname) ? '' : (string) $obj->user_firstname,
					'user_lastname' => empty($obj->user_lastname) ? '' : (string) $obj->user_lastname,
					'user_login' => empty($obj->user_login) ? '' : (string) $obj->user_login,
					'date' => empty($obj->date) ? '' : (string) $obj->date,
					'comment' => empty($obj->comments) ? '' : (string) $obj->comments,
					'amount' => $amount,
				);
			}
			$db->free($resql);
		}

		foreach ($forecast['categories'] as $key => $category) {
			$forecast['categories'][$key]['forecast_gap'] = $category['order_budget'] - $category['supplier_expenses'];
		}
		$forecast['totals']['forecast_gap'] = $forecast['totals']['order_budget'] - $forecast['totals']['supplier_expenses'];

		return $forecast;
	}
}

if (!function_exists('lmdbadvancedproject_include_dolibarr_class')) {
	/**
	 * Include a Dolibarr class file when it is not already loaded.
	 *
	 * @param  string $relativePath Path from Dolibarr document root
	 * @return void
	 */
	function lmdbadvancedproject_include_dolibarr_class($relativePath)
	{
		if (function_exists('dol_include_once')) {
			dol_include_once($relativePath);
			return;
		}

		if (defined('DOL_DOCUMENT_ROOT') && file_exists(DOL_DOCUMENT_ROOT.$relativePath)) {
			include_once DOL_DOCUMENT_ROOT.$relativePath;
		}
	}
}

if (!function_exists('lmdbadvancedproject_get_forecast_document_nom_url')) {
	/**
	 * Return a Dolibarr document link for a forecast detail line.
	 *
	 * @param  array<string,mixed> $line Forecast detail line
	 * @return string
	 */
	function lmdbadvancedproject_get_forecast_document_nom_url($line)
	{
		global $db;

		$documentId = empty($line['document_id']) ? 0 : (int) $line['document_id'];
		$ref = empty($line['ref']) ? '' : (string) $line['ref'];
		$type = empty($line['type']) ? '' : (string) $line['type'];
		if ($documentId <= 0 || $ref === '') {
			return lmdbadvancedproject_escape_html($ref);
		}

		$className = '';
		$relativePath = '';
		if ($type === 'customer_order') {
			$className = 'Commande';
			$relativePath = '/commande/class/commande.class.php';
		} elseif ($type === 'supplier_invoice') {
			$className = 'FactureFournisseur';
			$relativePath = '/fourn/class/fournisseur.facture.class.php';
		} elseif (in_array($type, array('supplier_order', 'supplier_order_ordered', 'supplier_order_delivered'), true)) {
			$className = 'CommandeFournisseur';
			$relativePath = '/fourn/class/fournisseur.commande.class.php';
		}

		if ($className === '') {
			return lmdbadvancedproject_escape_html($ref);
		}

		if (!class_exists($className)) {
			lmdbadvancedproject_include_dolibarr_class($relativePath);
		}
		if (!class_exists($className)) {
			return lmdbadvancedproject_escape_html($ref);
		}

		$document = new $className($db);
		$document->id = $documentId;
		$document->rowid = $documentId;
		$document->ref = $ref;
		if (method_exists($document, 'getNomUrl')) {
			return $document->getNomUrl(1);
		}

		return lmdbadvancedproject_escape_html($ref);
	}
}

if (!function_exists('lmdbadvancedproject_get_forecast_document_status_badge')) {
	/**
	 * Return a Dolibarr status badge for a supplier forecast detail line.
	 *
	 * @param  array<string,mixed> $line Forecast detail line
	 * @return string
	 */
	function lmdbadvancedproject_get_forecast_document_status_badge($line)
	{
		global $db, $langs;

		$documentId = empty($line['document_id']) ? 0 : (int) $line['document_id'];
		$ref = empty($line['ref']) ? '' : (string) $line['ref'];
		$type = empty($line['type']) ? '' : (string) $line['type'];
		$status = array_key_exists('document_status', $line) && $line['document_status'] !== null ? (int) $line['document_status'] : 0;
		$paid = empty($line['document_paid']) ? 0 : (int) $line['document_paid'];
		$billed = empty($line['document_billed']) ? 0 : (int) $line['document_billed'];

		$className = '';
		$relativePath = '';
		if ($type === 'supplier_invoice') {
			$className = 'FactureFournisseur';
			$relativePath = '/fourn/class/fournisseur.facture.class.php';
		} elseif (in_array($type, array('supplier_order', 'supplier_order_ordered', 'supplier_order_delivered'), true)) {
			$className = 'CommandeFournisseur';
			$relativePath = '/fourn/class/fournisseur.commande.class.php';
		}

		if ($className !== '') {
			if (!class_exists($className)) {
				lmdbadvancedproject_include_dolibarr_class($relativePath);
			}
			if (class_exists($className)) {
				$document = new $className($db);
				$document->id = $documentId;
				$document->rowid = $documentId;
				$document->ref = $ref;
				$document->status = $status;
				$document->statut = $status;
				$document->fk_statut = $status;
				$document->paye = $paid;
				$document->paid = $paid;
				$document->billed = $billed;
				if (method_exists($document, 'getLibStatut')) {
					return $document->getLibStatut(5);
				}
			}
		}

		if ($type === 'supplier_invoice') {
			return lmdbadvancedproject_escape_html($langs->trans($paid ? 'BudgetReportSupplierInvoicePaid' : 'BudgetReportSupplierInvoiceUnpaid'));
		}

		if ($status === 4) {
			return lmdbadvancedproject_escape_html($langs->trans('BudgetReportSupplierStatusPartiallyDelivered'));
		}
		if ($status === 5) {
			return lmdbadvancedproject_escape_html($langs->trans('BudgetReportSupplierStatusDelivered'));
		}

		return lmdbadvancedproject_escape_html($langs->trans('BudgetReportSupplierStatusOrdered'));
	}
}

if (!function_exists('lmdbadvancedproject_print_forecast_lines')) {
	/**
	 * Print forecast detail lines.
	 *
	 * @param array<int,array<string,mixed>> $lines      Lines to print
	 * @param bool                          $showBudget Show budget column
	 * @param string                        $titleKey   Translation key for details title
	 * @param string                        $modalId    Modal HTML id
	 * @return void
	 */
	function lmdbadvancedproject_print_forecast_lines($lines, $showBudget, $titleKey, $modalId)
	{
		global $langs;

		if (empty($lines)) {
			print '<button type="button" class="button budgetreport-modal-open" disabled="disabled" title="'.lmdbadvancedproject_escape_html($langs->trans('BudgetReportNoDetail')).'">'.$langs->trans($titleKey).' (0)</button>';
			return;
		}

		$safeModalId = lmdbadvancedproject_escape_html($modalId);
		$title = $langs->trans($titleKey);
		$showSupplierStatus = !$showBudget;

		print '<button type="button" class="button budgetreport-modal-open" data-budgetreport-modal-target="'.$safeModalId.'">'.$title.' ('.count($lines).')</button>';
		print '<div class="budgetreport-modal" id="'.$safeModalId.'" aria-hidden="true">';
		print '<div class="budgetreport-modal-backdrop" data-budgetreport-modal-close="1"></div>';
		print '<div class="budgetreport-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="'.$safeModalId.'-title">';
		print '<div class="budgetreport-modal-header">';
		print '<div class="budgetreport-modal-title" id="'.$safeModalId.'-title">'.$title.'</div>';
		print '<button type="button" class="ui-button ui-corner-all ui-widget ui-button-icon-only ui-dialog-titlebar-close" title="'.lmdbadvancedproject_escape_html($langs->trans('Close')).'" data-budgetreport-modal-close="1" aria-label="'.lmdbadvancedproject_escape_html($langs->trans('Close')).'"><span class="ui-button-icon ui-icon ui-icon-closethick"></span><span class="ui-button-icon-space"> </span></button>';
		print '</div>';
		print '<div class="budgetreport-modal-body">';
		print '<table class="budgetreport-forecast-subtable">';
		print '<tr><th class="budgetreport-forecast-ref-col">'.$langs->trans('Ref').'</th>';
		if ($showSupplierStatus) {
			print '<th class="budgetreport-forecast-status-col">'.$langs->trans('Status').'</th>';
		}
		print '<th class="budgetreport-forecast-date-col">'.$langs->trans('Date').'</th><th class="budgetreport-forecast-label-col">'.$langs->trans('Label').'</th><th class="budgetreport-forecast-qty-col">'.$langs->trans('Qty').'</th><th class="budgetreport-forecast-amount-col">'.$langs->trans('AmountHTShort').'</th>';
		if ($showBudget) {
			print '<th class="budgetreport-forecast-budget-col">'.$langs->trans('BudgetReportOrderBudget').'</th>';
		}
		print '</tr>';
		$totalAmount = 0;
		$totalBudget = 0;
		foreach ($lines as $line) {
			$totalAmount += empty($line['amount']) ? 0 : (float) $line['amount'];
			$totalBudget += empty($line['budget']) ? 0 : (float) $line['budget'];
			$lineLabel = empty($line['label']) ? '' : (string) $line['label'];
			$lineLabelFull = empty($line['label_full']) ? $lineLabel : (string) $line['label_full'];
			print '<tr>';
			print '<td class="budgetreport-forecast-ref-col">'.lmdbadvancedproject_get_forecast_document_nom_url($line).'</td>';
			if ($showSupplierStatus) {
				print '<td class="budgetreport-forecast-status-col">'.lmdbadvancedproject_get_forecast_document_status_badge($line).'</td>';
			}
			print '<td class="budgetreport-forecast-date-col">'.lmdbadvancedproject_format_modal_date($line['date']).'</td>';
			print '<td class="budgetreport-forecast-label-col"><span class="budgetreport-forecast-label-truncate" title="'.lmdbadvancedproject_escape_html($lineLabelFull).'">'.lmdbadvancedproject_escape_html($lineLabel).'</span></td>';
			print '<td class="budgetreport-forecast-qty-col" align="right">'.price($line['qty']).'</td>';
			print '<td class="budgetreport-forecast-amount-col" align="right">'.lmdbadvancedproject_format_price($line['amount']).'</td>';
			if ($showBudget) {
				print '<td class="budgetreport-forecast-budget-col" align="right">'.lmdbadvancedproject_format_price($line['budget']).'</td>';
			}
			print '</tr>';
		}
		print '<tr class="budgetreport-forecast-total-row">';
		print '<td colspan="'.($showSupplierStatus ? '4' : '3').'">'.$langs->trans('BudgetReportTotal').'</td>';
		print '<td class="budgetreport-forecast-qty-col" align="right"></td>';
		print '<td class="budgetreport-forecast-amount-col" align="right">'.lmdbadvancedproject_format_price($totalAmount).'</td>';
		if ($showBudget) {
			print '<td class="budgetreport-forecast-budget-col" align="right">'.lmdbadvancedproject_format_price($totalBudget).'</td>';
		}
		print '</tr>';
		print '</table>';
		print '</div>';
		print '</div>';
		print '</div>';
	}
}

if (!function_exists('lmdbadvancedproject_print_budgetreport_modal_script')) {
	/**
	 * Print modal behavior for forecast detail dialogs.
	 *
	 * @return void
	 */
	function lmdbadvancedproject_print_budgetreport_modal_script()
	{
		?>
		<script>
		(function() {
			function openModal(modal) {
				if (!modal) return;
				modal.classList.add('budgetreport-modal-is-open');
				modal.setAttribute('aria-hidden', 'false');
			}
			function closeModal(modal) {
				if (!modal) return;
				modal.classList.remove('budgetreport-modal-is-open');
				modal.setAttribute('aria-hidden', 'true');
			}
			document.addEventListener('click', function(event) {
				var target = event.target;
				if (!target || !target.closest) return;
				var opener = target.closest('[data-budgetreport-modal-target]');
				if (opener) {
					event.preventDefault();
					openModal(document.getElementById(opener.getAttribute('data-budgetreport-modal-target')));
					return;
				}
				if (target.closest('[data-budgetreport-modal-close]')) {
					event.preventDefault();
					closeModal(target.closest('.budgetreport-modal'));
				}
			});
			document.addEventListener('keydown', function(event) {
				if (event.key !== 'Escape') return;
				document.querySelectorAll('.budgetreport-modal-is-open').forEach(function(modal) {
					closeModal(modal);
				});
			});
		})();
		</script>
		<?php
	}
}

if (!function_exists('lmdbadvancedproject_get_task_nom_url')) {
	/**
	 * Return a Dolibarr task link for a time detail line.
	 *
	 * @param  array<string,mixed> $line Time detail line
	 * @return string
	 */
	function lmdbadvancedproject_get_task_nom_url($line)
	{
		global $db;

		$taskId = empty($line['task_id']) ? 0 : (int) $line['task_id'];
		if ($taskId <= 0 || !class_exists('Task')) {
			return lmdbadvancedproject_escape_html(empty($line['task_ref']) ? '' : $line['task_ref']);
		}

		$taskstatic = new Task($db);
		$taskstatic->id = $taskId;
		$taskstatic->ref = empty($line['task_ref']) ? (string) $taskId : (string) $line['task_ref'];
		$taskstatic->label = empty($line['task_label']) ? '' : (string) $line['task_label'];

		return $taskstatic->getNomUrl(1);
	}
}

if (!function_exists('lmdbadvancedproject_get_expense_report_nom_url')) {
	/**
	 * Return a Dolibarr expense report link for an expense detail line.
	 *
	 * @param  array<string,mixed> $line Expense detail line
	 * @return string
	 */
	function lmdbadvancedproject_get_expense_report_nom_url($line)
	{
		global $db;

		$expenseId = empty($line['id']) ? 0 : (int) $line['id'];
		if ($expenseId <= 0 || !class_exists('ExpenseReport')) {
			return lmdbadvancedproject_escape_html(empty($line['ref']) ? '' : $line['ref']);
		}

		$expensestatic = new ExpenseReport($db);
		$expensestatic->id = $expenseId;
		$expensestatic->ref = empty($line['ref']) ? (string) $expenseId : (string) $line['ref'];

		return $expensestatic->getNomUrl(1);
	}
}

if (!function_exists('lmdbadvancedproject_get_user_nom_url')) {
	/**
	 * Return the native Dolibarr rendering for a linked user.
	 *
	 * @param array<string,mixed> $line Expense detail line
	 * @return string
	 */
	function lmdbadvancedproject_get_user_nom_url($line)
	{
		global $db;

		$userId = empty($line['user_id']) ? 0 : (int) $line['user_id'];
		if ($userId <= 0) {
			return '';
		}
		if (empty($line['user_name']) && empty($line['user_login'])) {
			return '<span class="opacitymedium">#'.$userId.'</span>';
		}
		$userstatic = new User($db);
		$userstatic->id = $userId;
		$userstatic->firstname = empty($line['user_firstname']) ? '' : (string) $line['user_firstname'];
		$userstatic->lastname = empty($line['user_lastname']) ? '' : (string) $line['user_lastname'];
		$userstatic->login = empty($line['user_login']) ? '' : (string) $line['user_login'];

		return $userstatic->getNomUrl(-1);
	}
}

if (!function_exists('lmdbadvancedproject_print_project_forecast')) {
	/**
	 * Print project forecast table.
	 *
	 * @param array<string,mixed> $forecast      Forecast data
	 * @param array<string,mixed> $timeBreakdown Monthly time breakdown data
	 * @param array<string,mixed> $monthAxis     Shared monthly axis
	 * @return void
	 */
	function lmdbadvancedproject_print_project_forecast($forecast, $timeBreakdown, $monthAxis)
	{
		global $langs;

		print '<div class="budgetreport-table-scroll">';
		print '<table class="budgettbl budgetreport-forecast-table">';
		print '<tr>';
		print '<th>'.$langs->trans('LMDB_CommercialCategoryExtrafield').'</th>';
		print '<th>'.$langs->trans('BudgetReportOrderAmount').'</th>';
		print '<th>'.$langs->trans('BudgetReportOrderBudget').'</th>';
		print '<th>'.$langs->trans('BudgetReportSupplierExpenses').'</th>';
		print '<th>'.$langs->trans('BudgetReportForecastGap').'</th>';
		print '<th>'.$langs->trans('BudgetReportDetails').'</th>';
		print '</tr>';

		foreach ($forecast['categories'] as $categoryKey => $category) {
			$gapColor = $category['forecast_gap'] >= 0 ? 'green' : 'red';
			$modalBase = 'budgetreport-forecast-'.preg_replace('/[^a-zA-Z0-9_-]/', '-', (string) $categoryKey);
			print '<tr>';
			print '<td>'.lmdbadvancedproject_escape_html(lmdbadvancedproject_get_forecast_category_label($categoryKey, $category, $langs)).'</td>';
			print '<td align="right">'.lmdbadvancedproject_format_price($category['order_amount']).'</td>';
			print '<td align="right">'.lmdbadvancedproject_format_price($category['order_budget']).'</td>';
			print '<td align="right">'.lmdbadvancedproject_format_price($category['supplier_expenses']).'</td>';
			print '<td align="right" style="color:'.$gapColor.'">'.lmdbadvancedproject_format_price($category['forecast_gap']).'</td>';
			print '<td>';
			lmdbadvancedproject_print_forecast_lines($category['order_lines'], true, 'BudgetReportOrderDetails', $modalBase.'-orders');
			lmdbadvancedproject_print_forecast_lines($category['supplier_lines'], false, 'BudgetReportSupplierDetails', $modalBase.'-suppliers');
			print '</td>';
			print '</tr>';
		}

		$totalGapColor = $forecast['totals']['forecast_gap'] >= 0 ? 'green' : 'red';
		print '<tr>';
		print '<td><b>'.$langs->trans('BudgetReportTotal').'</b></td>';
		print '<td align="right"><b>'.lmdbadvancedproject_format_price($forecast['totals']['order_amount']).'</b></td>';
		print '<td align="right"><b>'.lmdbadvancedproject_format_price($forecast['totals']['order_budget']).'</b></td>';
		print '<td align="right"><b>'.lmdbadvancedproject_format_price($forecast['totals']['supplier_expenses']).'</b></td>';
		print '<td align="right" style="color:'.$totalGapColor.'"><b>'.lmdbadvancedproject_format_price($forecast['totals']['forecast_gap']).'</b></td>';
		print '<td></td>';
		print '</tr>';
		print '</table>';
		print '</div>';

		lmdbadvancedproject_print_time_breakdown($timeBreakdown, $monthAxis);

		print '<div class="budgetreport-forecast-extra">';
		print '<div class="budgettitle budgetreport-forecast-subtitle">'.$langs->trans('BudgetReportTimeSpentTotal').'</div>';
		print '<div class="budgetreport-table-scroll">';
		print '<table class="budgettbl budgetreport-extra-subtable">';
		print '<tr><th class="budgetreport-extra-compact-col">'.$langs->trans('Task').'</th><th class="budgetreport-extra-task-label-col">'.$langs->trans('Label').'</th><th class="budgetreport-extra-compact-col">'.$langs->trans('BudgetReportContributorCount').'</th><th class="budgetreport-extra-compact-col">'.$langs->trans('BudgetReportTimeSpentHours').'</th><th class="budgetreport-extra-compact-col">'.$langs->trans('BudgetReportSpent').'</th></tr>';
		foreach ($forecast['time']['lines'] as $line) {
			$taskLabelParts = lmdbadvancedproject_truncated_text_parts(empty($line['task_label']) ? '' : $line['task_label'], 50);
			print '<tr>';
			print '<td class="budgetreport-extra-compact-col">'.lmdbadvancedproject_get_task_nom_url($line).'</td>';
			print '<td class="budgetreport-extra-task-label-col"><span class="budgetreport-extra-task-label-truncate" title="'.$taskLabelParts['full'].'">'.$taskLabelParts['short'].'</span></td>';
			print '<td class="budgetreport-extra-compact-col" align="right">'.((int) $line['contributors']).'</td>';
			print '<td class="budgetreport-extra-compact-col" align="right">'.lmdbadvancedproject_format_hours($line['hours']).'</td>';
			print '<td class="budgetreport-extra-compact-col" align="right">'.lmdbadvancedproject_format_price($line['cost']).'</td>';
			print '</tr>';
		}
		print '<tr><td colspan="2"><b>'.$langs->trans('BudgetReportTotal').'</b></td><td class="budgetreport-extra-compact-col" align="right"><b>'.((int) $forecast['time']['contributors']).'</b></td><td class="budgetreport-extra-compact-col" align="right"><b>'.lmdbadvancedproject_format_hours($forecast['time']['hours']).'</b></td><td class="budgetreport-extra-compact-col" align="right"><b>'.lmdbadvancedproject_format_price($forecast['time']['cost']).'</b></td></tr>';
		print '</table>';
		print '</div>';

		print '<div class="budgettitle budgetreport-forecast-subtitle">'.$langs->trans('BudgetReportExpenseReportDetails').'</div>';
		print '<div class="budgetreport-table-scroll">';
		print '<table class="budgettbl budgetreport-extra-subtable">';
		print '<tr><th class="budgetreport-extra-compact-col">'.$langs->trans('Date').'</th><th class="budgetreport-extra-compact-col">'.$langs->trans('Ref').'</th><th class="budgetreport-extra-compact-col">'.$langs->trans('User').'</th><th class="budgetreport-extra-expense-comment-col">'.$langs->trans('BudgetReportExpenseComment').'</th><th class="budgetreport-extra-compact-col">'.$langs->trans('AmountHTShort').'</th></tr>';
		foreach ($forecast['expenses']['lines'] as $line) {
			$expenseCommentParts = lmdbadvancedproject_truncated_text_parts(empty($line['comment']) ? '' : $line['comment'], 75);
			print '<tr>';
			print '<td class="budgetreport-extra-compact-col">'.lmdbadvancedproject_format_date($line['date']).'</td>';
			print '<td class="budgetreport-extra-compact-col">'.lmdbadvancedproject_get_expense_report_nom_url($line).'</td>';
			print '<td class="budgetreport-extra-compact-col">'.lmdbadvancedproject_get_user_nom_url($line).'</td>';
			print '<td class="budgetreport-extra-expense-comment-col"><span class="budgetreport-extra-expense-comment-truncate" title="'.$expenseCommentParts['full'].'">'.$expenseCommentParts['short'].'</span></td>';
			print '<td class="budgetreport-extra-compact-col" align="right">'.lmdbadvancedproject_format_price($line['amount']).'</td>';
			print '</tr>';
		}
		print '<tr><td colspan="4"><b>'.$langs->trans('BudgetReportTotal').'</b></td><td class="budgetreport-extra-compact-col" align="right"><b>'.lmdbadvancedproject_format_price($forecast['expenses']['total']).'</b></td></tr>';
		print '</table>';
		print '</div>';
		print '</div>';
		lmdbadvancedproject_print_budgetreport_modal_script();
	}
}

if (!function_exists('lmdbadvancedproject_load_budget_report_data')) {
	/**
	 * Load all data needed by the global and project budget reports.
	 *
	 * @param  int                 $budgetReportProjectId Project id for project tab, 0 for global report
	 * @param  array<string,mixed> $filters               Global report filters
	 * @return array<string,mixed>
	 */
	function lmdbadvancedproject_load_budget_report_data($budgetReportProjectId = 0, $filters = array())
	{
		global $db, $conf;

		$projects = array();
		$mobudget = array();
		$mospent = array();
		$motimehours = array();
		$cleanmos = array();

		$totaltime = 0;
		$totalvendinv = 0;
		$totalsupplierordersorderedremaining = 0;
		$totalsupplierordersdeliveredremaining = 0;
		$totalsupplierordersremaining = 0;
		$totalexpenses = 0;
		$totalorders = 0;
		$totalcustomerinvoices = 0;
		$budget = 0;

		$budgetReportProjectId = empty($budgetReportProjectId) ? 0 : (int) $budgetReportProjectId;
		$filters = lmdbadvancedproject_normalize_budget_report_filters($filters);
		$projectSqlFilter = $budgetReportProjectId > 0 ? " AND p.rowid = ".$budgetReportProjectId : "";
		$projectStatusSqlFilter = ($budgetReportProjectId > 0) ? '' : lmdbadvancedproject_build_project_status_sql_filter($filters['project_status']);
		$projectDateSqlFilter = ($budgetReportProjectId > 0) ? '' : lmdbadvancedproject_build_project_date_sql_filter($filters);
		$orderProjectSqlFilter = $budgetReportProjectId > 0 ? " AND c.fk_projet = ".$budgetReportProjectId : "";
		$vendorInvoiceProjectSqlFilter = $budgetReportProjectId > 0 ? " AND ff.fk_projet = ".$budgetReportProjectId : "";
		$supplierOrderProjectSqlFilter = $budgetReportProjectId > 0 ? " AND cf.fk_projet = ".$budgetReportProjectId : "";
		$expenseProjectSqlFilter = $budgetReportProjectId > 0 ? " AND ed.fk_projet = ".$budgetReportProjectId : "";
		if ($budgetReportProjectId <= 0) {
			$restrictedProjectIds = null;
			$authorizedProjectIds = lmdbadvancedproject_get_budget_report_authorized_project_ids();
			if (is_array($authorizedProjectIds)) {
				$restrictedProjectIds = array_values(array_unique(array_map('intval', $authorizedProjectIds)));
			}
			if (!empty($filters['project_ids'])) {
				$requestedProjectIds = array_values(array_unique(array_map('intval', $filters['project_ids'])));
				$restrictedProjectIds = is_array($restrictedProjectIds)
					? array_values(array_intersect($restrictedProjectIds, $requestedProjectIds))
					: $requestedProjectIds;
			}
			if (is_array($restrictedProjectIds)) {
				$restrictedProjectsSql = empty($restrictedProjectIds) ? '0' : implode(',', $restrictedProjectIds);
				$projectSqlFilter .= ' AND p.rowid IN ('.$restrictedProjectsSql.')';
				$orderProjectSqlFilter .= ' AND c.fk_projet IN ('.$restrictedProjectsSql.')';
				$vendorInvoiceProjectSqlFilter .= ' AND ff.fk_projet IN ('.$restrictedProjectsSql.')';
				$supplierOrderProjectSqlFilter .= ' AND cf.fk_projet IN ('.$restrictedProjectsSql.')';
				$expenseProjectSqlFilter .= ' AND ed.fk_projet IN ('.$restrictedProjectsSql.')';
			}
		}
		$contentPeriodIsActive = $budgetReportProjectId <= 0 && lmdbadvancedproject_budget_report_content_period_is_active($filters);
		$contentFilters = $filters;
		if ($budgetReportProjectId > 0) {
			$contentFilters['exclude_content_outside_period'] = '0';
		}
		$orderDateCondition = lmdbadvancedproject_build_content_date_sql_condition('c.date_commande', $contentFilters);
		$customerInvoiceDateCondition = lmdbadvancedproject_build_content_date_sql_condition('f.datef', $contentFilters);
		$timeDateCondition = lmdbadvancedproject_build_content_date_sql_condition('ptt.element_date', $contentFilters);
		$supplierInvoiceDateCondition = lmdbadvancedproject_build_content_date_sql_condition('ff.datef', $contentFilters);
		$supplierOrderDateCondition = lmdbadvancedproject_build_content_date_sql_condition('COALESCE(cf.date_commande, DATE(cf.date_creation))', $contentFilters);
		$expenseDateCondition = lmdbadvancedproject_build_content_date_sql_condition('ed.date', $contentFilters);
		$contentStartMonth = $contentPeriodIsActive && !empty($filters['date_start']) ? substr($filters['date_start'], 0, 7) : '';
		$contentEndMonth = $contentPeriodIsActive && !empty($filters['date_end']) ? substr($filters['date_end'], 0, 7) : '';

		$entityShared = (lmdbadvancedproject_is_multicompany_enabled() && getDolGlobalInt('LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES')) ? 1 : 0;
		$projectDisplayEntityShared = $budgetReportProjectId > 0 ? 1 : $entityShared;
		$projectEntities = lmdbadvancedproject_get_entity_filter('project', $projectDisplayEntityShared);
		$projectDataEntities = lmdbadvancedproject_get_entity_filter('project', 1);
		$orderEntities = lmdbadvancedproject_get_entity_filter('commande', 1);
		$customerInvoiceEntities = lmdbadvancedproject_get_entity_filter('facture', 1);
		$supplierInvoiceEntities = lmdbadvancedproject_get_entity_filter('supplier_invoice', 1);
		$supplierOrderEntities = lmdbadvancedproject_get_entity_filter('supplier_order', 1);
		$expenseReportEntities = lmdbadvancedproject_get_entity_filter('expensereport', 1);

		$budgetReportMulticompanyInfoKey = 'BudgetReportMulticompanyInactiveInfo';
		if (lmdbadvancedproject_is_multicompany_enabled()) {
			$budgetReportMulticompanyInfoKey = $entityShared ? 'BudgetReportMulticompanyAllEntitiesInfo' : 'BudgetReportMulticompanyCurrentEntityInfo';
		}

		$sql = "SELECT p.*, cmd.total_orders, COALESCE(cmdbudget.total_budget, 0) AS total_budget FROM ".MAIN_DB_PREFIX."projet p
			INNER JOIN (
				SELECT c.fk_projet, SUM(CASE WHEN ".$orderDateCondition." THEN COALESCE(c.total_ht, 0) ELSE 0 END) as total_orders
				FROM ".MAIN_DB_PREFIX."commande c
				WHERE c.fk_projet > 0 AND c.fk_statut > 0 AND c.entity IN (".$orderEntities.")".$orderProjectSqlFilter."
				GROUP BY c.fk_projet
			) cmd ON cmd.fk_projet = p.rowid
			LEFT JOIN (
				SELECT c.fk_projet, SUM(CASE WHEN ".$orderDateCondition." THEN COALESCE(cd.buy_price_ht, 0) * COALESCE(cd.qty, 0) ELSE 0 END) as total_budget
				FROM ".MAIN_DB_PREFIX."commande c
				INNER JOIN ".MAIN_DB_PREFIX."commandedet cd ON cd.fk_commande = c.rowid
				WHERE c.fk_projet > 0 AND c.fk_statut > 0 AND c.entity IN (".$orderEntities.")".$orderProjectSqlFilter."
				GROUP BY c.fk_projet
			) cmdbudget ON cmdbudget.fk_projet = p.rowid
			WHERE p.entity IN (".$projectEntities.")".$projectSqlFilter.$projectStatusSqlFilter.$projectDateSqlFilter."
			ORDER BY cmd.total_orders DESC";

		$result = $db->query($sql);
		$nbtotalofrecords = $result ? $db->num_rows($result) : 0;

		$i=0;
		while ($i<$nbtotalofrecords) {
			$obj = $db->fetch_object($result);
			$projectOrders = (float) $obj->total_orders;
			$projectBudget = (float) $obj->total_budget;

			$projects[$obj->rowid] = array(
				"ref" => $obj->rowid,
				"project_ref" => $obj->ref,
				"title" => $obj->title,
				"public" => (int) $obj->public,
				"budget" => $projectBudget,
				"orders" => $projectOrders,
				"order_details" => array(),
				"invoiced" => 0,
				"invoice_details" => array(),
				"spent" => 0,
			);
			$budget += $projectBudget;
			$totalorders += $projectOrders;

			if (empty($obj->dateo)) {
				// Projects without a start date stay in totals but cannot be plotted by month.
			} elseif (empty($obj->datee) || $obj->datee<$obj->dateo) {
				$yrmo = date('Y-m', strtotime($obj->dateo));
				if ($contentStartMonth !== '' && $yrmo < $contentStartMonth) {
					$yrmo = $contentStartMonth;
				}
				if ($contentEndMonth === '' || $yrmo <= $contentEndMonth) {
					$cleanmos[$yrmo] = $yrmo;
					if (!isset($mobudget[$yrmo])) {
						$mobudget[$yrmo] = 0;
					}
					$mobudget[$yrmo] += $projectBudget;
				}
			} else if ($projectBudget>0) {
				$j = 0;
				$molist = array();
				$yrmo = date('Y-m', strtotime($obj->dateo));
				$yrme = date('Y-m', strtotime($obj->datee));
				if ($contentStartMonth !== '' && $yrmo < $contentStartMonth) {
					$yrmo = $contentStartMonth;
				}
				if ($contentEndMonth !== '' && $yrme > $contentEndMonth) {
					$yrme = $contentEndMonth;
				}

				while ($yrmo<=$yrme && $j<37) {
					$molist[$j] = $yrmo;
					$cleanmos[$yrmo] = $yrmo;
					$j++;
					$yrmo = date('Y-m', strtotime($yrmo.'-01 +1 month'));
				}

				if ($j > 0) {
					$permonth = $projectBudget/$j;
					foreach ($molist as $mos) {
						if (!isset($mobudget[$mos])) {
							$mobudget[$mos] = 0;
						}
						$mobudget[$mos] += $permonth;
					}
				}
			}

			$i++;
		}
		if ($result) {
			$db->free($result);
		}

		/** @var array<int,list<array{id:int,ref:string,date:string,amount:float}>> $customerOrderDetails */
		$customerOrderDetails = array();
		$selectedReportProjectIds = array_map('intval', array_keys($projects));
		if (!empty($selectedReportProjectIds)) {
			$selectedReportProjectsSql = implode(',', $selectedReportProjectIds);
			$sqlCustomerOrderDetails = "SELECT c.fk_projet, c.rowid AS order_id, c.ref AS order_ref, c.date_commande AS order_date, COALESCE(c.total_ht, 0) AS total_order
				FROM ".MAIN_DB_PREFIX."commande c
				WHERE c.fk_projet IN (".$selectedReportProjectsSql.") AND c.fk_statut > 0 AND c.entity IN (".$orderEntities.")
				AND ".$orderDateCondition."
				ORDER BY c.date_commande DESC, c.ref DESC";
			$resultCustomerOrderDetails = $db->query($sqlCustomerOrderDetails);
			if (!$resultCustomerOrderDetails) {
				dol_syslog(__FUNCTION__.': failed to load customer order details: '.$db->lasterror(), LOG_ERR);
			} else {
				while (is_object($orderRow = $db->fetch_object($resultCustomerOrderDetails))) {
					$projectId = (int) $orderRow->fk_projet;
					if (!isset($customerOrderDetails[$projectId])) {
						$customerOrderDetails[$projectId] = array();
					}
					$customerOrderDetails[$projectId][] = array(
						'id' => (int) $orderRow->order_id,
						'ref' => (string) $orderRow->order_ref,
						'date' => (string) $orderRow->order_date,
						'amount' => (float) $orderRow->total_order,
					);
				}
				$db->free($resultCustomerOrderDetails);
			}
		}
		foreach (array_keys($projects) as $pid) {
			if (!empty($customerOrderDetails[$pid])) {
				$projects[$pid]['order_details'] = $customerOrderDetails[$pid];
			}
		}

		/** @var array<int,list<array{id:int,ref:string,type:int,date:string,amount:float}>> $customerInvoiceDetails */
		$customerInvoiceDetails = array();
		if (!empty($selectedReportProjectIds)) {
			$selectedCustomerInvoiceProjectsSql = implode(',', $selectedReportProjectIds);
			$customerInvoiceSplitEnabled = lmdbadvancedproject_customer_invoice_split_report_enabled();
			if ($customerInvoiceSplitEnabled) {
				$directInvoiceContributionsSql = "SELECT f.fk_projet, f.rowid AS invoice_id, f.ref AS invoice_ref, f.type AS invoice_type, f.datef AS invoice_date, SUM(COALESCE(fd.total_ht, 0)) AS total_invoice
					FROM ".MAIN_DB_PREFIX."facture f
					INNER JOIN ".MAIN_DB_PREFIX."facturedet fd ON fd.fk_facture = f.rowid
					WHERE f.fk_projet IN (".$selectedCustomerInvoiceProjectsSql.") AND f.fk_statut IN (1,2) AND f.entity IN (".$customerInvoiceEntities.")
					AND ".$customerInvoiceDateCondition."
					AND NOT EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."lmdbadvancedproject_customer_invoice_parts cipx WHERE cipx.fk_facture_det = fd.rowid)
					GROUP BY f.fk_projet, f.rowid, f.ref, f.type, f.datef";
				$splitInvoiceContributionsSql = "SELECT cip.fk_projet, f.rowid AS invoice_id, f.ref AS invoice_ref, f.type AS invoice_type, f.datef AS invoice_date, SUM(COALESCE(cip.total_ht, 0)) AS total_invoice
					FROM ".MAIN_DB_PREFIX."lmdbadvancedproject_customer_invoice_parts cip
					INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = cip.fk_facture
					WHERE cip.fk_projet IN (".$selectedCustomerInvoiceProjectsSql.") AND f.fk_statut IN (1,2) AND f.entity IN (".$customerInvoiceEntities.") AND cip.entity IN (".$customerInvoiceEntities.")
					AND ".$customerInvoiceDateCondition."
					GROUP BY cip.fk_projet, f.rowid, f.ref, f.type, f.datef";
				$sqlCustomerInvoiceDetails = "SELECT invoice_contribution.fk_projet, invoice_contribution.invoice_id, invoice_contribution.invoice_ref, invoice_contribution.invoice_type, invoice_contribution.invoice_date, SUM(invoice_contribution.total_invoice) AS total_invoice
					FROM (".$directInvoiceContributionsSql." UNION ALL ".$splitInvoiceContributionsSql.") invoice_contribution
					GROUP BY invoice_contribution.fk_projet, invoice_contribution.invoice_id, invoice_contribution.invoice_ref, invoice_contribution.invoice_type, invoice_contribution.invoice_date
					ORDER BY invoice_contribution.invoice_date DESC, invoice_contribution.invoice_ref DESC";
			} else {
				$sqlCustomerInvoiceDetails = "SELECT f.fk_projet, f.rowid AS invoice_id, f.ref AS invoice_ref, f.type AS invoice_type, f.datef AS invoice_date, COALESCE(f.total_ht, 0) AS total_invoice
					FROM ".MAIN_DB_PREFIX."facture f
					WHERE f.fk_projet IN (".$selectedCustomerInvoiceProjectsSql.") AND f.fk_statut IN (1,2) AND f.entity IN (".$customerInvoiceEntities.")
					AND ".$customerInvoiceDateCondition."
					ORDER BY f.datef DESC, f.ref DESC";
			}

			$resultCustomerInvoiceDetails = $db->query($sqlCustomerInvoiceDetails);
			if (!$resultCustomerInvoiceDetails) {
				dol_syslog(__FUNCTION__.': failed to load customer invoice details: '.$db->lasterror(), LOG_ERR);
			} else {
				while (is_object($invoiceRow = $db->fetch_object($resultCustomerInvoiceDetails))) {
					$projectId = (int) $invoiceRow->fk_projet;
					if (!isset($customerInvoiceDetails[$projectId])) {
						$customerInvoiceDetails[$projectId] = array();
					}
					$customerInvoiceDetails[$projectId][] = array(
						'id' => (int) $invoiceRow->invoice_id,
						'ref' => (string) $invoiceRow->invoice_ref,
						'type' => (int) $invoiceRow->invoice_type,
						'date' => (string) $invoiceRow->invoice_date,
						'amount' => (float) $invoiceRow->total_invoice,
					);
				}
				$db->free($resultCustomerInvoiceDetails);
			}
		}

		foreach ($projects as $pid => $data) {
			if (empty($customerInvoiceDetails[$pid])) {
				continue;
			}
			$projects[$pid]['invoice_details'] = $customerInvoiceDetails[$pid];
			foreach ($customerInvoiceDetails[$pid] as $invoiceDetail) {
				$projects[$pid]['invoiced'] += (float) $invoiceDetail['amount'];
			}
			$totalcustomerinvoices += (float) $projects[$pid]['invoiced'];
		}

		$timespent = array();
		$totalTimeHours = 0.0;
		$timeBreakdown = array(
			'mode' => $budgetReportProjectId > 0 ? 'task' : 'project',
			'rows' => array(),
			'column_totals' => array(),
			'total_hours' => 0.0,
		);
		$selectedProjectIds = array_map('intval', array_keys($projects));

		if ($budgetReportProjectId > 0 && !empty($selectedProjectIds)) {
			$sqlTasks = "SELECT pt.rowid, pt.fk_projet, pt.ref, pt.label
				FROM ".MAIN_DB_PREFIX."projet_task pt
				WHERE pt.fk_projet = ".$budgetReportProjectId."
				AND pt.entity IN (".$projectDataEntities.")
				ORDER BY pt.ref ASC, pt.label ASC";
			$resultTasks = $db->query($sqlTasks);
			if ($resultTasks) {
				while (is_object($taskObject = $db->fetch_object($resultTasks))) {
					$taskId = (int) $taskObject->rowid;
					$timeBreakdown['rows'][$taskId] = array(
						'id' => $taskId,
						'project_id' => (int) $taskObject->fk_projet,
						'ref' => (string) $taskObject->ref,
						'label' => (string) $taskObject->label,
						'months' => array(),
						'total_hours' => 0.0,
					);
				}
				$db->free($resultTasks);
			}
		} else {
			foreach ($projects as $projectId => $projectData) {
				$timeBreakdown['rows'][(int) $projectId] = array(
					'id' => (int) $projectId,
					'project_id' => (int) $projectId,
					'ref' => (string) $projectData['project_ref'],
					'label' => (string) $projectData['title'],
					'public' => (int) $projectData['public'],
					'months' => array(),
					'total_hours' => 0.0,
				);
			}
		}

		if (!empty($selectedProjectIds)) {
			$groupSelect = $budgetReportProjectId > 0
				? "pt.rowid AS breakdown_id, pt.ref AS breakdown_ref, pt.label AS breakdown_label,"
				: "pt.fk_projet AS breakdown_id, '' AS breakdown_ref, '' AS breakdown_label,";
			$groupBy = $budgetReportProjectId > 0
				? 'pt.rowid, pt.ref, pt.label, month_key'
				: 'pt.fk_projet, month_key';
			$sql0 = "SELECT pt.fk_projet, ".$groupSelect." DATE_FORMAT(ptt.element_date, '%Y-%m') AS month_key,
				SUM(ptt.element_duration) / 3600.0 AS total_hours,
				SUM((ptt.element_duration / 3600.0) * CASE
					WHEN ptt.thm IS NOT NULL AND ptt.thm > 0 THEN ptt.thm
					WHEN u.thm IS NOT NULL AND u.thm > 0 THEN u.thm
					ELSE 0
				END) AS totalspent
				FROM ".MAIN_DB_PREFIX."element_time ptt
				INNER JOIN ".MAIN_DB_PREFIX."projet_task pt ON ptt.fk_element = pt.rowid
				LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = ptt.fk_user
				WHERE ptt.elementtype = 'task'
				AND ptt.element_duration > 0
				AND ptt.element_date IS NOT NULL
				AND ".$timeDateCondition."
				AND pt.entity IN (".$projectDataEntities.")
				AND pt.fk_projet IN (".implode(',', $selectedProjectIds).")
				GROUP BY ".$groupBy."
				ORDER BY month_key ASC";
			$result0 = $db->query($sql0);
			if ($result0) {
				while (is_object($obj = $db->fetch_object($result0))) {
					$projectId = (int) $obj->fk_projet;
					$rowId = (int) $obj->breakdown_id;
					$monthKey = (string) $obj->month_key;
					$hours = empty($obj->total_hours) ? 0.0 : (float) $obj->total_hours;
					$cost = empty($obj->totalspent) ? 0.0 : (float) $obj->totalspent;

					if (!isset($timespent[$projectId][$monthKey])) {
						$timespent[$projectId][$monthKey] = 0.0;
					}
					$timespent[$projectId][$monthKey] += $cost;
					if (!isset($motimehours[$monthKey])) {
						$motimehours[$monthKey] = 0.0;
					}
					$motimehours[$monthKey] += $hours;
					$cleanmos[$monthKey] = $monthKey;

					if (!isset($timeBreakdown['rows'][$rowId])) {
						$timeBreakdown['rows'][$rowId] = array(
							'id' => $rowId,
							'project_id' => $projectId,
							'ref' => (string) $obj->breakdown_ref,
							'label' => (string) $obj->breakdown_label,
							'months' => array(),
							'total_hours' => 0.0,
						);
					}
					$timeBreakdown['rows'][$rowId]['months'][$monthKey] = $hours;
					$timeBreakdown['rows'][$rowId]['total_hours'] += $hours;
					$totalTimeHours += $hours;
				}
				$db->free($result0);
			}
		}

		foreach ($projects as $pid=>$data) {
			if (isset($timespent[$pid])) {
				foreach ($timespent[$pid] as $dt=>$val) {
					$projects[$pid]["spent"] += (float) $val;
					$totaltime += (float) $val;

					$yrmo = date('Y-m', strtotime($dt));
					$cleanmos[$yrmo] = $yrmo;
					if (!isset($mospent[$yrmo])) {
						$mospent[$yrmo] = 0;
					}
					$mospent[$yrmo] += (float) $val;
				}
			}
		}

		$vendorinvs = array();
		$supplierInvoiceSplitEnabled = lmdbadvancedproject_supplier_invoice_split_report_enabled();
		$supplierInvoiceSplitExclusion = lmdbadvancedproject_supplier_invoice_split_source_exclusion_sql('ff', 'ffd', $supplierInvoiceEntities);
		$sql1 = "SELECT ff.datef, ff.fk_projet, SUM(ffd.total_ht) as total_inv FROM ".MAIN_DB_PREFIX."facture_fourn ff
			INNER JOIN ".MAIN_DB_PREFIX."facture_fourn_det ffd ON ffd.fk_facture_fourn = ff.rowid
			WHERE ff.fk_projet > 0 AND ff.fk_statut IN (1,2) AND ff.entity IN (".$supplierInvoiceEntities.")".$vendorInvoiceProjectSqlFilter.$supplierInvoiceSplitExclusion."
			AND ".$supplierInvoiceDateCondition."
			GROUP BY ff.fk_projet, ff.datef";
		$result1 = $db->query($sql1);
		$nbtotal1 = $result1 ? $db->num_rows($result1) : 0;
		$i=0;
		while ($i<$nbtotal1) {
			$obj = $db->fetch_object($result1);
			if (!isset($vendorinvs[$obj->fk_projet][$obj->datef])) {
				$vendorinvs[$obj->fk_projet][$obj->datef] = 0;
			}
			$vendorinvs[$obj->fk_projet][$obj->datef] += (float) $obj->total_inv;
			$i++;
		}
		if ($result1) {
			$db->free($result1);
		}

		if ($supplierInvoiceSplitEnabled) {
			$supplierInvoicePartProjectSqlFilter = $budgetReportProjectId > 0 ? " AND sip.fk_projet = ".$budgetReportProjectId : "";
			$sqlSupplierInvoiceParts = "SELECT ff.datef, sip.fk_projet, SUM(sip.total_ht) AS total_inv
				FROM ".MAIN_DB_PREFIX."lmdbadvancedproject_supplier_invoice_parts sip
				INNER JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = sip.fk_facture_fourn
				WHERE sip.fk_projet > 0 AND ff.fk_statut IN (1,2) AND ff.entity IN (".$supplierInvoiceEntities.") AND sip.entity IN (".$supplierInvoiceEntities.")".$supplierInvoicePartProjectSqlFilter."
				AND ".$supplierInvoiceDateCondition."
				GROUP BY sip.fk_projet, ff.datef";
			$resultSupplierInvoiceParts = $db->query($sqlSupplierInvoiceParts);
			$nbtotalSupplierInvoiceParts = $resultSupplierInvoiceParts ? $db->num_rows($resultSupplierInvoiceParts) : 0;
			$i=0;
			while ($i<$nbtotalSupplierInvoiceParts) {
				$obj = $db->fetch_object($resultSupplierInvoiceParts);
				if (!isset($vendorinvs[$obj->fk_projet][$obj->datef])) {
					$vendorinvs[$obj->fk_projet][$obj->datef] = 0;
				}
				$vendorinvs[$obj->fk_projet][$obj->datef] += (float) $obj->total_inv;
				$i++;
			}
			if ($resultSupplierInvoiceParts) {
				$db->free($resultSupplierInvoiceParts);
			}
		}

		foreach ($projects as $pid=>$data) {
			if (isset($vendorinvs[$pid])) {
				foreach ($vendorinvs[$pid] as $dt=>$val) {
					$projects[$pid]["spent"] += (float) $val;
					$totalvendinv += (float) $val;

					$yrmo = date('Y-m', strtotime($dt));
					$cleanmos[$yrmo] = $yrmo;
					if (!isset($mospent[$yrmo])) {
						$mospent[$yrmo] = 0;
					}
					$mospent[$yrmo] += (float) $val;
				}
			}
		}

		$supplierorders = array();
		$linkedSupplierInvoiceSql = lmdbadvancedproject_get_linked_supplier_invoice_sql($supplierInvoiceEntities);
		$supplierOrderRemainingExpression = lmdbadvancedproject_supplier_order_remaining_line_expression();
		$supplierOrderSplitExclusion = lmdbadvancedproject_supplier_order_split_source_exclusion_sql('cf', $supplierInvoiceEntities);
		$sql3 = "SELECT cf.fk_projet, COALESCE(cf.date_commande, DATE(cf.date_creation)) AS order_date,
				CASE WHEN cf.fk_statut = 3 THEN 'ordered' ELSE 'delivered' END AS supplier_order_bucket,
				SUM(".$supplierOrderRemainingExpression.") as total_order_remaining
			FROM ".MAIN_DB_PREFIX."commande_fournisseur cf
			INNER JOIN ".MAIN_DB_PREFIX."commande_fournisseurdet cfd ON cfd.fk_commande = cf.rowid
			LEFT JOIN (".$linkedSupplierInvoiceSql.") inv ON inv.order_id = cf.rowid
			WHERE cf.fk_projet > 0
			AND cf.fk_statut IN (3,4,5)
			AND COALESCE(cf.billed, 0) = 0
			AND cf.entity IN (".$supplierOrderEntities.")".$supplierOrderProjectSqlFilter.$supplierOrderSplitExclusion."
			AND ".$supplierOrderDateCondition."
			GROUP BY cf.fk_projet, order_date, supplier_order_bucket";
		$result3 = $db->query($sql3);
		$nbtotal3 = $result3 ? $db->num_rows($result3) : 0;
		$i=0;
		while ($i<$nbtotal3) {
			$obj = $db->fetch_object($result3);
			if ($obj->total_order_remaining > 0) {
				$bucket = ($obj->supplier_order_bucket === 'ordered') ? 'ordered' : 'delivered';
				if (!isset($supplierorders[$obj->fk_projet][$obj->order_date][$bucket])) {
					$supplierorders[$obj->fk_projet][$obj->order_date][$bucket] = 0;
				}
				$supplierorders[$obj->fk_projet][$obj->order_date][$bucket] += (float) $obj->total_order_remaining;
			}
			$i++;
		}
		if ($result3) {
			$db->free($result3);
		}

		foreach ($projects as $pid=>$data) {
			if (isset($supplierorders[$pid])) {
				foreach ($supplierorders[$pid] as $dt=>$bucketAmounts) {
					foreach ($bucketAmounts as $bucket=>$val) {
						$projects[$pid]["spent"] += (float) $val;
						$totalsupplierordersremaining += (float) $val;
						if ($bucket === 'ordered') {
							$totalsupplierordersorderedremaining += (float) $val;
						} else {
							$totalsupplierordersdeliveredremaining += (float) $val;
						}

						$yrmo = date('Y-m', strtotime($dt));
						$cleanmos[$yrmo] = $yrmo;
						if (!isset($mospent[$yrmo])) {
							$mospent[$yrmo] = 0;
						}
						$mospent[$yrmo] += (float) $val;
					}
				}
			}
		}

		$expenses = array();
		$sql2 = "SELECT ed.date, ed.fk_projet, SUM(ed.total_ht) as total_exp FROM ".MAIN_DB_PREFIX."expensereport_det ed
			LEFT JOIN ".MAIN_DB_PREFIX."expensereport ex ON ed.fk_expensereport = ex.rowid
			WHERE ed.fk_projet > 0 AND ex.fk_user_approve>0 AND ex.entity IN (".$expenseReportEntities.")".$expenseProjectSqlFilter."
			AND ".$expenseDateCondition."
			GROUP BY ed.fk_projet, ed.date ";
		$result2 = $db->query($sql2);
		$nbtotal2 = $result2 ? $db->num_rows($result2) : 0;
		$i=0;
		while ($i<$nbtotal2) {
			$obj = $db->fetch_object($result2);
			if (!isset($expenses[$obj->fk_projet][$obj->date])) {
				$expenses[$obj->fk_projet][$obj->date] = 0;
			}
			$expenses[$obj->fk_projet][$obj->date] += (float) $obj->total_exp;
			$i++;
		}
		if ($result2) {
			$db->free($result2);
		}

		foreach ($projects as $pid=>$data) {
			if (isset($expenses[$pid])) {
				foreach ($expenses[$pid] as $dt=>$val) {
					$projects[$pid]["spent"] += (float) $val;
					$totalexpenses += (float) $val;

					$yrmo = date('Y-m', strtotime($dt));
					$cleanmos[$yrmo] = $yrmo;
					if (!isset($mospent[$yrmo])) {
						$mospent[$yrmo] = 0;
					}
					$mospent[$yrmo] += (float) $val;
				}
			}
		}

		ksort($cleanmos);
		$monthAxis = array();
		$molabels = array();
		$mobudgets = array();
		$mospents = array();
		$motimehourvalues = array();
		$mobudgetFormattedValues = array();
		$mospentFormattedValues = array();
		$motimehourFormattedValues = array();
		foreach ($cleanmos as $monthKey) {
			$monthLabel = lmdbadvancedproject_get_month_label($monthKey);
			$monthBudget = empty($mobudget[$monthKey]) ? 0.0 : (float) $mobudget[$monthKey];
			$monthSpent = empty($mospent[$monthKey]) ? 0.0 : (float) $mospent[$monthKey];
			$monthTimeHours = empty($motimehours[$monthKey]) ? 0.0 : (float) $motimehours[$monthKey];
			$monthAxis[$monthKey] = array(
				'key' => $monthKey,
				'label' => $monthLabel,
				'budget' => $monthBudget,
				'spent' => $monthSpent,
				'time_hours' => $monthTimeHours,
			);
			$molabels[] = $monthLabel;
			$mobudgets[] = lmdbadvancedproject_round_amount($monthBudget);
			$mospents[] = lmdbadvancedproject_round_amount($monthSpent);
			$motimehourvalues[] = $monthTimeHours;
			$mobudgetFormattedValues[] = lmdbadvancedproject_format_price($monthBudget);
			$mospentFormattedValues[] = lmdbadvancedproject_format_price($monthSpent);
			$motimehourFormattedValues[] = lmdbadvancedproject_format_hours($monthTimeHours, true);
			$timeBreakdown['column_totals'][$monthKey] = 0.0;
		}
		$motimehourAxisMaximum = empty($motimehourvalues) ? 1.0 : max(1.0, ceil(max($motimehourvalues) * 1.1));
		foreach ($timeBreakdown['rows'] as $rowId => $row) {
			foreach ($monthAxis as $monthKey => $monthData) {
				$hours = isset($row['months'][$monthKey]) ? (float) $row['months'][$monthKey] : 0.0;
				$timeBreakdown['rows'][$rowId]['months'][$monthKey] = $hours;
				$timeBreakdown['column_totals'][$monthKey] += $hours;
			}
		}
		$timeBreakdown['total_hours'] = $totalTimeHours;

		$totalspent = $totaltime+$totalvendinv+$totalsupplierordersremaining+$totalexpenses;
		$balance = $budget-$totalspent;
		$blncolor = $balance < 0 ? "red" : "green";

		$labels = array();
		$budgets = array();
		$spents = array();
		$budgetFormattedValues = array();
		$spentFormattedValues = array();

		foreach ($projects as $data) {
			$labels[] = lmdbadvancedproject_chart_label($data["title"]);
			$budgets[] = lmdbadvancedproject_round_amount($data["budget"]);
			$spents[] = lmdbadvancedproject_round_amount($data["spent"]);
			$budgetFormattedValues[] = lmdbadvancedproject_format_price($data["budget"]);
			$spentFormattedValues[] = lmdbadvancedproject_format_price($data["spent"]);
		}

		$spentLabels = array(
			lmdbadvancedproject_trans_chart("BudgetReportTimeSpentOnTasks"),
			lmdbadvancedproject_trans_chart("BudgetReportSupplierOrdersOrderedNotInvoiced"),
			lmdbadvancedproject_trans_chart("BudgetReportSupplierOrdersDeliveredNotInvoiced"),
			lmdbadvancedproject_trans_chart("BudgetReportVendorInvoices"),
			lmdbadvancedproject_trans_chart("BudgetReportStaffExpenses"),
		);
		$spentValues = array(
			lmdbadvancedproject_round_amount($totaltime),
			lmdbadvancedproject_round_amount($totalsupplierordersorderedremaining),
			lmdbadvancedproject_round_amount($totalsupplierordersdeliveredremaining),
			lmdbadvancedproject_round_amount($totalvendinv),
			lmdbadvancedproject_round_amount($totalexpenses),
		);

		if ($balance > 0) {
			$spentLabels[] = lmdbadvancedproject_trans_chart("BudgetReportBalance");
			$spentValues[] = lmdbadvancedproject_round_amount($balance);
		}

		$spentPieFormattedValues = array();
		foreach ($spentValues as $spentValue) {
			$spentPieFormattedValues[] = lmdbadvancedproject_format_price($spentValue);
		}

		$budgetReportForecast = array();
		if ($budgetReportProjectId > 0 && !empty($projects)) {
			$budgetReportForecast = lmdbadvancedproject_load_project_forecast($budgetReportProjectId, $projectDataEntities, $orderEntities, $supplierInvoiceEntities, $supplierOrderEntities, $expenseReportEntities);
		}

		$budgetChartTitleKey = "BudgetReportBudgetByProjects";
		if ($budgetReportProjectId > 0) {
			$budgetChartTitleKey = "BudgetReportBudgetByCategory";
			$labels = array();
			$budgets = array();
			$budgetFormattedValues = array();
			if (!empty($budgetReportForecast['categories'])) {
				foreach ($budgetReportForecast['categories'] as $categoryKey => $category) {
					$categoryBudget = empty($category['order_budget']) ? 0 : (float) $category['order_budget'];
					$labels[] = lmdbadvancedproject_chart_label(lmdbadvancedproject_get_forecast_category_label($categoryKey, $category, $langs));
					$budgets[] = lmdbadvancedproject_round_amount($categoryBudget);
					$budgetFormattedValues[] = lmdbadvancedproject_format_price($categoryBudget);
				}
			}
		}

		return array(
			'budgetReportProjectId' => $budgetReportProjectId,
			'filters' => $filters,
			'budgetReportMulticompanyInfoKey' => $budgetReportMulticompanyInfoKey,
			'projects' => $projects,
			'mobudget' => $mobudget,
			'mospent' => $mospent,
			'motimehours' => $motimehours,
			'cleanmos' => $cleanmos,
			'monthAxis' => $monthAxis,
			'molabels' => $molabels,
			'mobudgets' => $mobudgets,
			'mospents' => $mospents,
			'motimehourvalues' => $motimehourvalues,
			'motimehourAxisMaximum' => $motimehourAxisMaximum,
			'mobudgetFormattedValues' => $mobudgetFormattedValues,
			'mospentFormattedValues' => $mospentFormattedValues,
			'motimehourFormattedValues' => $motimehourFormattedValues,
			'timeBreakdown' => $timeBreakdown,
			'totalTimeHours' => $totalTimeHours,
			'totaltime' => $totaltime,
			'totalvendinv' => $totalvendinv,
			'totalsupplierordersorderedremaining' => $totalsupplierordersorderedremaining,
			'totalsupplierordersdeliveredremaining' => $totalsupplierordersdeliveredremaining,
			'totalsupplierordersremaining' => $totalsupplierordersremaining,
			'totalexpenses' => $totalexpenses,
			'totalorders' => $totalorders,
			'totalcustomerinvoices' => $totalcustomerinvoices,
			'budget' => $budget,
			'totalspent' => $totalspent,
			'balance' => $balance,
			'blncolor' => $blncolor,
			'labels' => $labels,
			'budgets' => $budgets,
			'spents' => $spents,
			'budgetFormattedValues' => $budgetFormattedValues,
			'spentFormattedValues' => $spentFormattedValues,
			'spentLabels' => $spentLabels,
			'spentValues' => $spentValues,
			'spentPieFormattedValues' => $spentPieFormattedValues,
			'budgetReportForecast' => $budgetReportForecast,
			'budgetChartTitleKey' => $budgetChartTitleKey,
		);
	}
}

if (!function_exists('lmdbadvancedproject_print_time_breakdown')) {
	/**
	 * Print the monthly time breakdown matrix.
	 *
	 * @param array<string,mixed> $timeBreakdown Time breakdown data
	 * @param array<string,mixed> $monthAxis Month axis
	 * @return void
	 */
	function lmdbadvancedproject_print_time_breakdown($timeBreakdown, $monthAxis)
	{
		global $db, $langs;

		$isProjectMode = isset($timeBreakdown['mode']) && $timeBreakdown['mode'] === 'project';
		$firstColumnLabel = $isProjectMode ? $langs->trans('BudgetReportProject') : $langs->trans('Task');

		print '<div class="budgetreport-time-section">';
		print '<div class="budgettitle">'.$langs->trans('BudgetReportTimeBreakdownByMonth').'</div>';
		print '<div class="budgetreport-time-scroll">';
		print '<table class="budgettbl budgetreport-time-table">';
		print '<thead><tr><th class="budgetreport-time-label">'.$firstColumnLabel.'</th>';
		foreach ($monthAxis as $monthData) {
			print '<th class="right nowrap">'.lmdbadvancedproject_escape_html($monthData['label']).'</th>';
		}
		print '<th class="right nowrap">'.$langs->trans('BudgetReportTotal').'</th></tr></thead>';
		print '<tbody>';

		if (empty($timeBreakdown['rows'])) {
			print '<tr class="oddeven"><td colspan="'.(count($monthAxis) + 2).'" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
		} else {
			foreach ($timeBreakdown['rows'] as $row) {
				print '<tr>';
				print '<td class="budgetreport-time-label">';
				if ($isProjectMode) {
					$projectstatic = new Project($db);
					$projectstatic->id = (int) $row['project_id'];
					$projectstatic->ref = (string) $row['ref'];
					$projectstatic->title = (string) $row['label'];
					$projectstatic->public = empty($row['public']) ? 0 : (int) $row['public'];
					print $projectstatic->getNomUrl(1, '/lmdbadvancedproject/tabs/project_budgetreport.php');
				} else {
					print lmdbadvancedproject_get_task_nom_url(array(
						'task_id' => (int) $row['id'],
						'task_ref' => (string) $row['ref'],
						'task_label' => (string) $row['label'],
					));
				}
				if (!empty($row['label'])) {
					print '<span class="opacitymedium budgetreport-time-row-label">'.lmdbadvancedproject_escape_html($row['label']).'</span>';
				}
				print '</td>';
				foreach ($monthAxis as $monthKey => $monthData) {
					$hours = isset($row['months'][$monthKey]) ? (float) $row['months'][$monthKey] : 0.0;
					print '<td class="right nowrap">'.lmdbadvancedproject_format_hours($hours).'</td>';
				}
				print '<td class="right nowrap"><b>'.lmdbadvancedproject_format_hours($row['total_hours']).'</b></td>';
				print '</tr>';
			}
		}

		print '<tr class="liste_total"><td><b>'.$langs->trans('BudgetReportTotal').'</b></td>';
		foreach ($monthAxis as $monthKey => $monthData) {
			$hours = isset($timeBreakdown['column_totals'][$monthKey]) ? (float) $timeBreakdown['column_totals'][$monthKey] : 0.0;
			print '<td class="right nowrap"><b>'.lmdbadvancedproject_format_hours($hours).'</b></td>';
		}
		print '<td class="right nowrap"><b>'.lmdbadvancedproject_format_hours($timeBreakdown['total_hours']).'</b></td></tr>';
		print '</tbody></table></div></div>';
	}
}

if (!function_exists('lmdbadvancedproject_render_budget_report')) {
	/**
	 * Render the budget report body.
	 *
	 * @param  int                 $budgetReportProjectId  Project id for project tab, 0 for global report
	 * @param  array<string,mixed> $filters                Global report filters
	 * @param  bool                $permissionToGeneratePdf Whether PDF generation is allowed
	 * @return void
	 */
	function lmdbadvancedproject_render_budget_report($budgetReportProjectId = 0, $filters = array(), $permissionToGeneratePdf = false)
	{
		global $db, $conf, $langs, $user;

		if (!$user->hasRight('projet', 'lire') || !$user->hasRight('lmdbadvancedproject', 'budgetreport', 'read')) {
			accessforbidden();
		}

		$budgetReportProjectId = (int) $budgetReportProjectId;

		$budgetReportData = lmdbadvancedproject_load_budget_report_data($budgetReportProjectId, $filters);
		extract($budgetReportData, EXTR_OVERWRITE);
		$monthlyChartMinWidth = max(720, (count($monthAxis) * 90) + 180);
		$exportParameters = $budgetReportProjectId > 0 ? array('project_id' => $budgetReportProjectId) : $filters;
		$exportBaseUrl = dol_buildpath('/lmdbadvancedproject/budgetreportexport.php', 1).'?'.http_build_query($exportParameters);
		$pdfFormTarget = getDolGlobalInt('MAIN_DISABLE_FORCE_SAVEAS') ? ' target="_blank"' : '';
		$formBudgetReport = new Form($db);
		$monthlyChartTooltip = $formBudgetReport->textwithtooltip('', $langs->trans('BudgetReportMonthlyTimeChartHelp'), 2, 1, img_help(1, ''), '', 3);

?>

<div class="info"><?php echo $langs->trans($budgetReportMulticompanyInfoKey); ?></div>

<?php if ($budgetReportProjectId > 0 && empty($projects)) { ?>
<div class="warning"><?php echo $langs->trans("BudgetReportProjectNoData"); ?></div>
<?php return; } ?>

<div class="tabsAction budgetreport-export-actions">
	<a class="butAction" href="<?php echo lmdbadvancedproject_escape_html($exportBaseUrl.'&format=xlsx'); ?>"><?php echo $langs->trans('BudgetReportExportXlsx'); ?></a>
	<a class="butAction" href="<?php echo lmdbadvancedproject_escape_html($exportBaseUrl.'&format=ods'); ?>"><?php echo $langs->trans('BudgetReportExportOds'); ?></a>
<?php if ($budgetReportProjectId > 0 && $permissionToGeneratePdf) { ?>
	<form method="POST" action="<?php echo lmdbadvancedproject_escape_html(dol_buildpath('/lmdbadvancedproject/tabs/project_budgetreport.php', 1).'?id='.(int) $budgetReportProjectId); ?>" id="budgetreport-pdf-form-<?php echo (int) $budgetReportProjectId; ?>"<?php echo $pdfFormTarget; ?> hidden>
		<input type="hidden" name="token" value="<?php echo newToken(); ?>">
		<input type="hidden" name="action" value="generate_budgetreport">
	</form>
	<div class="inline-block divButAction">
		<a class="butAction" href="#" role="button" onclick="document.getElementById('budgetreport-pdf-form-<?php echo (int) $budgetReportProjectId; ?>').submit(); return false;"><?php echo $langs->trans('BudgetReportGeneratePdf'); ?></a>
	</div>
<?php } ?>
</div>

<div class="budgetreport-summary-fullwidth">
<table class="noborder centpercent dashboard_budget" role="presentation">
	<tr>
		<td colspan="3" class="center valignmiddle budgetreport-summary-cell">
			<div class="opacitymedium budgetreport-summary-label"><?php echo $langs->trans("BudgetReportMarket"); ?></div>
			<div class="nowraponall budgetreport-summary-amount">
				<?php echo lmdbadvancedproject_format_price($totalorders); ?>
			</div>
		</td>
		<td colspan="3" class="center valignmiddle budgetreport-summary-cell">
			<div class="opacitymedium budgetreport-summary-label"><?php echo $langs->trans("BudgetReportInvoiced"); ?></div>
			<div class="nowraponall budgetreport-summary-amount">
				<?php echo lmdbadvancedproject_format_price($totalcustomerinvoices).' ('.lmdbadvancedproject_format_percentage($totalcustomerinvoices, $totalorders).')'; ?>
			</div>
		</td>
		<td colspan="3" class="center valignmiddle budgetreport-summary-cell">
			<div class="opacitymedium budgetreport-summary-label"><?php echo $langs->trans("BudgetReportBudget"); ?></div>
			<div class="nowraponall budgetreport-summary-amount">
				<?php echo lmdbadvancedproject_format_price($budget); ?>
			</div>
		</td>
		<td colspan="3" class="center valignmiddle budgetreport-summary-cell">
			<div class="opacitymedium budgetreport-summary-label"><?php echo $langs->trans("BudgetReportSpent"); ?></div>
			<div class="nowraponall budgetreport-summary-amount">
				<?php echo lmdbadvancedproject_format_price($totalspent); ?>
			</div>
			<div class="budgetreport-summary-breakdown">
				<div><span><?php echo $langs->trans("BudgetReportTimeSpentTotal"); ?></span><strong><?php echo lmdbadvancedproject_format_price($totaltime).' ('.lmdbadvancedproject_format_spent_percentage($totaltime, $totalspent).') &middot; '.lmdbadvancedproject_format_hours($totalTimeHours, true); ?></strong></div>
				<div><span><?php echo $langs->trans("BudgetReportSupplierOrdersOrdered"); ?></span><strong><?php echo lmdbadvancedproject_format_price($totalsupplierordersorderedremaining).' ('.lmdbadvancedproject_format_spent_percentage($totalsupplierordersorderedremaining, $totalspent).')'; ?></strong></div>
				<div><span><?php echo $langs->trans("BudgetReportSupplierOrdersDelivered"); ?></span><strong><?php echo lmdbadvancedproject_format_price($totalsupplierordersdeliveredremaining).' ('.lmdbadvancedproject_format_spent_percentage($totalsupplierordersdeliveredremaining, $totalspent).')'; ?></strong></div>
				<div><span><?php echo $langs->trans("BudgetReportVendorInvoices"); ?></span><strong><?php echo lmdbadvancedproject_format_price($totalvendinv).' ('.lmdbadvancedproject_format_spent_percentage($totalvendinv, $totalspent).')'; ?></strong></div>
				<div><span><?php echo $langs->trans("BudgetReportStaffExpenses"); ?></span><strong><?php echo lmdbadvancedproject_format_price($totalexpenses).' ('.lmdbadvancedproject_format_spent_percentage($totalexpenses, $totalspent).')'; ?></strong></div>
			</div>
		</td>
		<td colspan="3" class="center valignmiddle budgetreport-summary-cell">
			<div class="opacitymedium budgetreport-summary-label"><?php echo $langs->trans("BudgetReportLeftToSpend"); ?></div>
			<div class="nowraponall budgetreport-summary-amount" style='color:<?php echo $blncolor; ?>'>
				<?php echo lmdbadvancedproject_format_price($balance); ?>
			</div>
		</td>
	</tr>
</table>
</div>

<div class="budgetreport-report">

<div class="budgetreport-charts-row">
<div class="budgetreport-chart-panel">
	<div class="budgettitle"><?php echo $langs->trans($budgetChartTitleKey); ?></div>
	<div class="budgetreport-chart-scroll">
	<div class="budgetchart budgetreport-chart-content">
	<canvas id="canvas_idgraphstatus"></canvas>
	</div>
	</div>


	<script id="idgraphstatus">

	window.chartColors = {
		green: 'rgb(105, 191, 100)',
		red: 'rgb(221, 51, 51)',
		blue: 'rgb(41, 128, 230)',
		orange: 'rgb(255, 159, 64)',
		yellow: 'rgb(255, 205, 86)',
		greeny: 'rgb(75, 192, 192)',
		pink: 'rgb(255, 99, 132)',
		cyan: 'rgb(54, 203, 235)',
		purple: 'rgb(162, 74, 236)',
		purple2: 'rgb(153, 102, 255)',
		grey: 'rgb(201, 203, 207)',
		white: 'rgb(250, 245, 245)'
	};

	var budgetReportCurrencyCode = <?php echo json_encode(empty($conf->currency) ? '' : $conf->currency); ?>;
	var budgetReportMaxTotalDecimals = <?php echo max(0, getDolGlobalInt('MAIN_MAX_DECIMALS_TOT', 2)); ?>;
	var budgetReportMinTotalDecimals = Math.min(2, budgetReportMaxTotalDecimals);
	function budgetReportFormatChartValue(value) {
		if (budgetReportCurrencyCode && typeof Intl !== 'undefined') {
			try {
				return new Intl.NumberFormat(undefined, {
					style: 'currency',
					currency: budgetReportCurrencyCode,
					minimumFractionDigits: budgetReportMinTotalDecimals,
					maximumFractionDigits: budgetReportMaxTotalDecimals
				}).format(value);
			} catch (e) {}
		}

		value = Number(value);
		if (isNaN(value)) {
			value = 0;
		}
		value = value.toFixed(budgetReportMaxTotalDecimals).split('.');
		if (value.length > 1) {
			while (value[1].length > budgetReportMinTotalDecimals && value[1].slice(-1) === '0') {
				value[1] = value[1].slice(0, -1);
			}
			if (value[1] === '') {
				value.pop();
			}
		}
		value[0] = value[0].split(/(?=(?:...)*$)/).join(',');
		return value.join('.');
	}

	var budgetFormattedValues = <?php echo json_encode(array_values($budgetFormattedValues)); ?>;
	var budget_config = {
			type: 'pie',
			data: {
				datasets: [{
					label: <?php echo json_encode(lmdbadvancedproject_trans_chart($budgetChartTitleKey)); ?>,
					data: <?php echo json_encode(array_values($budgets)); ?>,
					backgroundColor: [window.chartColors.green,
										window.chartColors.red,
										window.chartColors.purple,
										window.chartColors.orange,
										window.chartColors.cyan,
										window.chartColors.pink,
										window.chartColors.blue,
										window.chartColors.yellow]
					}],
				labels: <?php echo json_encode(array_values($labels)); ?>
				},

			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: {
					position: 'right',
				},
				title: {
					display: false,
					text: <?php echo json_encode(lmdbadvancedproject_trans_chart($budgetChartTitleKey)); ?>
				},
				animation: {
					animateScale: true,
					animateRotate: true
				},

				tooltips: {
				  callbacks: {
						label: function(tooltipItem, data) {
							var label = data.labels[tooltipItem.index];
							return label+': '+budgetFormattedValues[tooltipItem.index];
						}
				  }
				} //end tooltips

			}
		};

	var ctx = document.getElementById("canvas_idgraphstatus").getContext("2d");
	var chart = new Chart(ctx, budget_config);
	</script>
</div>

<div class="budgetreport-chart-panel">
	<div class="budgettitle"><?php echo $langs->trans("BudgetReportBudgetVsSpent"); ?></div>
	<div class="budgetreport-chart-scroll">
	<div class="budgetchart budgetreport-chart-content">
	<canvas id="canvas_idgraphspent"></canvas>
	</div>
	</div>

	<script id="idgraphspent">
	var spentFormattedValues = <?php echo json_encode(array_values($spentPieFormattedValues)); ?>;
	var spent_config = {
			type: 'pie',
			data: {
				datasets: [{
					label: <?php echo json_encode(lmdbadvancedproject_trans_chart("BudgetReportBudgetVsSpent")); ?>,
					data: <?php echo json_encode(array_values($spentValues)); ?>,
					backgroundColor: [window.chartColors.cyan,
										window.chartColors.orange,
										window.chartColors.greeny,
										window.chartColors.pink,
										window.chartColors.yellow,
										window.chartColors.white,]
					}],
				labels: <?php echo json_encode(array_values($spentLabels)); ?>
				},

			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: {
					position: 'right',
				},
				title: {
					display: false,
					text: <?php echo json_encode(lmdbadvancedproject_trans_chart("BudgetReportBudgetVsSpent")); ?>
				},
				animation: {
					animateScale: true,
					animateRotate: true
				},

				tooltips: {
				  callbacks: {
						label: function(tooltipItem, data) {
							var label = data.labels[tooltipItem.index];
							return label+': '+spentFormattedValues[tooltipItem.index];
						}
				  }
				} //end tooltips

			}
		};

	var ctx = document.getElementById("canvas_idgraphspent").getContext("2d");
	var chart = new Chart(ctx, spent_config);
	</script>
</div>
</div>




<div class="budgetreport-month-section">
	<div class="budgettitle"><?php echo $langs->trans("BudgetReportBudgetVsSpentByMonth").' '.$monthlyChartTooltip; ?></div>
	<div class="budgetbarchart">
	<canvas id="canvas_idgraphmonth"></canvas>
	</div>
	<div id="budgetreport-month-scrollbar" class="budgetreport-month-scrollbar" tabindex="0" aria-label="<?php echo lmdbadvancedproject_escape_html($langs->trans('BudgetReportBudgetVsSpentByMonth')); ?>" hidden>
		<div id="budgetreport-month-scrollbar-track" class="budgetreport-month-scrollbar-track" style="width: <?php echo (int) $monthlyChartMinWidth; ?>px;"></div>
	</div>

	<script id="idgraphmonth">
	var color = Chart.helpers.color;
	var monthAllFormattedValues = [
		<?php echo json_encode(array_values($mobudgetFormattedValues)); ?>,
		<?php echo json_encode(array_values($mospentFormattedValues)); ?>,
		<?php echo json_encode(array_values($motimehourFormattedValues)); ?>
	];
	var monthFormattedValues = monthAllFormattedValues.map(function(values) { return values.slice(); });
	var monthAllLabels = <?php echo json_encode(array_values($molabels)); ?>;
	var monthAllDatasetValues = [
		<?php echo json_encode(array_values($mobudgets)); ?>,
		<?php echo json_encode(array_values($mospents)); ?>,
		<?php echo json_encode(array_values($motimehourvalues)); ?>
	];
	var monthChartTitle = <?php echo json_encode(lmdbadvancedproject_trans_chart("BudgetReportBudgetVsSpentByMonth")); ?>;
	var monthHoursAxisTitle = <?php echo json_encode(lmdbadvancedproject_trans_chart("BudgetReportTimeSpentHours")); ?>;
	var monthHoursAxisMaximum = <?php echo json_encode($motimehourAxisMaximum); ?>;
	var month_config = {
			type: 'bar',
			data: {
				datasets: [{
					label: <?php echo json_encode(lmdbadvancedproject_trans_chart("BudgetReportBudget")); ?>,
					data: monthAllDatasetValues[0].slice(),
					backgroundColor: color(window.chartColors.blue).alpha(0.4).rgbString(),
					borderColor: window.chartColors.blue,
					borderWidth: 1,
					yAxisID: 'y-axis-amount',
					},
					{
					label: <?php echo json_encode(lmdbadvancedproject_trans_chart("BudgetReportSpent")); ?>,
					type: 'line',
					data: monthAllDatasetValues[1].slice(),
					backgroundColor: color(window.chartColors.red).alpha(0).rgbString(),
					borderColor: window.chartColors.red,
					borderWidth: 1,
					yAxisID: 'y-axis-amount',
					},
					{
					label: <?php echo json_encode(lmdbadvancedproject_trans_chart("BudgetReportTimeSpentByMonth")); ?>,
					type: 'line',
					data: monthAllDatasetValues[2].slice(),
					backgroundColor: color(window.chartColors.greeny).alpha(0).rgbString(),
					borderColor: window.chartColors.greeny,
					borderDash: [6, 4],
					borderWidth: 2,
					pointBackgroundColor: window.chartColors.greeny,
					yAxisID: 'y-axis-hours',
					}
					],
				labels: monthAllLabels.slice(),
				},

			options: {
				responsive: true,
				maintainAspectRatio: false,
				animation: {
					animateScale: true,
					animateRotate: true
				}
			}
		};

	var usesModernChartApi = typeof Chart.registry !== 'undefined';
	if (usesModernChartApi) {
		month_config.options.plugins = {
			legend: {
				position: 'top'
			},
			title: {
				display: false,
				text: monthChartTitle
			},
			tooltip: {
				mode: 'index',
				callbacks: {
					label: function(context) {
						var label = context.dataset.label || '';
						return label+': '+monthFormattedValues[context.datasetIndex][context.dataIndex];
					}
				}
			}
		};
		month_config.options.scales = {
			'y-axis-amount': {
				type: 'linear',
				position: 'left',
				beginAtZero: true,
				ticks: {
					callback: function(value) {
						return budgetReportFormatChartValue(value);
					}
				},
				title: {
					display: budgetReportCurrencyCode !== '',
					text: budgetReportCurrencyCode
				}
			},
			'y-axis-hours': {
				type: 'linear',
				position: 'right',
				beginAtZero: true,
				max: monthHoursAxisMaximum,
				suggestedMax: monthHoursAxisMaximum,
				grid: {
					drawOnChartArea: false
				},
				title: {
					display: true,
					text: monthHoursAxisTitle
				}
			},
			x: {
				type: 'category'
			}
		};
	} else {
		month_config.options.legend = {
			position: 'top'
		};
		month_config.options.title = {
			display: false,
			text: monthChartTitle
		};
		month_config.options.tooltips = {
			mode: 'label',
			callbacks: {
				label: function(tooltipItem, data) {
					var label = data.datasets[tooltipItem.datasetIndex].label || '';
					return label+': '+monthFormattedValues[tooltipItem.datasetIndex][tooltipItem.index];
				}
			}
		};
		month_config.options.scales = {
			yAxes: [{
				id: 'y-axis-amount',
				position: 'left',
				ticks: {
					beginAtZero: true,
					userCallback: function(value) {
						return budgetReportFormatChartValue(value);
					}
				},
				scaleLabel: {
					display: budgetReportCurrencyCode !== '',
					labelString: budgetReportCurrencyCode
				}
			}, {
				id: 'y-axis-hours',
				position: 'right',
				gridLines: {
					drawOnChartArea: false
				},
				ticks: {
					beginAtZero: true,
					max: monthHoursAxisMaximum,
					suggestedMax: monthHoursAxisMaximum
				},
				scaleLabel: {
					display: true,
					labelString: monthHoursAxisTitle
				}
			}],
			xAxes: [{
				ticks: {}
			}]
		};
	}

	var monthChartContainer = document.getElementById("canvas_idgraphmonth").parentNode;
	var monthScrollbar = document.getElementById("budgetreport-month-scrollbar");
	var monthScrollbarTrack = document.getElementById("budgetreport-month-scrollbar-track");
	var ctx = document.getElementById("canvas_idgraphmonth").getContext("2d");
	var monthChart = new Chart(ctx, month_config);
	var monthWindowStart = -1;
	var monthWindowSize = -1;
	var monthScrollFrame = null;

	function budgetReportUpdateMonthWindow(forceUpdate) {
		var availableWidth = Math.max(320, monthChartContainer.clientWidth || 720);
		var visibleMonths = Math.max(1, Math.floor((availableWidth - 170) / 90));
		visibleMonths = Math.min(monthAllLabels.length, visibleMonths);
		var firstMonth = 0;
		var needsScrollbar = monthAllLabels.length > visibleMonths;

		if (needsScrollbar) {
			monthScrollbar.hidden = false;
			monthScrollbarTrack.style.width = Math.max(availableWidth + 1, (monthAllLabels.length * 90) + 180)+'px';
			var maximumScroll = Math.max(1, monthScrollbar.scrollWidth - monthScrollbar.clientWidth);
			var maximumStart = monthAllLabels.length - visibleMonths;
			firstMonth = Math.min(maximumStart, Math.round((monthScrollbar.scrollLeft / maximumScroll) * maximumStart));
		} else {
			monthScrollbar.hidden = true;
			monthScrollbar.scrollLeft = 0;
			visibleMonths = monthAllLabels.length;
		}

		if (!forceUpdate && firstMonth === monthWindowStart && visibleMonths === monthWindowSize) {
			return;
		}
		monthWindowStart = firstMonth;
		monthWindowSize = visibleMonths;
		monthChart.data.labels = monthAllLabels.slice(firstMonth, firstMonth + visibleMonths);
		monthFormattedValues = monthAllFormattedValues.map(function(values) {
			return values.slice(firstMonth, firstMonth + visibleMonths);
		});
		monthChart.data.datasets.forEach(function(dataset, datasetIndex) {
			dataset.data = monthAllDatasetValues[datasetIndex].slice(firstMonth, firstMonth + visibleMonths);
		});
		if (usesModernChartApi) {
			monthChart.update('none');
		} else {
			monthChart.update(0);
		}
	}

	function budgetReportScheduleMonthWindowUpdate(forceUpdate) {
		if (monthScrollFrame !== null) {
			window.cancelAnimationFrame(monthScrollFrame);
		}
		monthScrollFrame = window.requestAnimationFrame(function() {
			monthScrollFrame = null;
			budgetReportUpdateMonthWindow(forceUpdate);
		});
	}

	monthScrollbar.addEventListener('scroll', function() {
		budgetReportScheduleMonthWindowUpdate(false);
	});
	window.addEventListener('resize', function() {
		budgetReportScheduleMonthWindowUpdate(true);
	});
	budgetReportScheduleMonthWindowUpdate(true);
	</script>
</div>

<?php if ($budgetReportProjectId <= 0) { ?>
	<?php lmdbadvancedproject_print_time_breakdown($timeBreakdown, $monthAxis); ?>
<?php } ?>

<div class="budgetreport-table-section">
<?php if ($budgetReportProjectId > 0) { ?>
	<div class="budgettitle"><?php echo $langs->trans("BudgetReportCategorySummary"); ?></div>
	<?php lmdbadvancedproject_print_project_forecast($budgetReportForecast, $timeBreakdown, $monthAxis); ?>
<?php } else { ?>
	<div class="budgettitle"><?php echo $langs->trans("BudgetReportBudgetVsSpentByProject"); ?></div>
	<div class="budgetreport-table-scroll">
	<table class="budgettbl">
		<tr>
			<th><?php echo $langs->trans("BudgetReportProject"); ?></th>
			<th><?php echo $langs->trans("BudgetReportMarket"); ?></th>
			<th><?php echo $langs->trans("BudgetReportInvoices"); ?></th>
			<th><?php echo $langs->trans("BudgetReportBudget"); ?></th>
			<th><?php echo $langs->trans("BudgetReportSpent"); ?></th>
			<th><?php echo $langs->trans("BudgetReportGrossMargin"); ?></th>
			<th><?php echo $langs->trans("BudgetReportBalance"); ?></th>
		</tr>

		<?php
		foreach ($projects as $pid=>$data) {
		$fbal = $data['budget']-$data['spent'];
		$fcolor = "green";
		if ($fbal<0) $fcolor="red";
		$fgrossmargin = $data['orders']-$data['spent'];
		$fgrosscolor = "green";
		if ($fgrossmargin<0) $fgrosscolor="red";
		$projectstatic = new Project($db);
		$projectstatic->id = $pid;
		$projectstatic->ref = $data['project_ref'];
		$projectstatic->title = $data['title'];
		$projectstatic->public = $data['public'];
		?>

		<tr>
			<td><?php echo $projectstatic->getNomUrl(1, '/lmdbadvancedproject/tabs/project_budgetreport.php', 1); ?></td>
			<td align="right"><?php echo lmdbadvancedproject_get_customer_order_summary_html($formBudgetReport, $data['project_ref'], $data['orders'], $data['order_details'], $filters); ?></td>
			<td align="right"><?php echo lmdbadvancedproject_get_customer_invoice_summary_html($formBudgetReport, $data['project_ref'], $data['invoiced'], $data['orders'], $data['invoice_details'], $filters); ?></td>
			<td align="right"><?php echo lmdbadvancedproject_format_price($data['budget']); ?></td>
			<td align="right"><?php echo lmdbadvancedproject_format_price($data['spent']); ?></td>
			<td align="right" style='color:<?php echo $fgrosscolor; ?>'><?php echo lmdbadvancedproject_format_margin($fgrossmargin, $data['orders']); ?></td>
			<td align="right" style='color:<?php echo $fcolor; ?>'><?php echo lmdbadvancedproject_format_price($fbal); ?></td>
		</tr>

		<?php } ?>

		<?php
		$totalgrossmargin = $totalorders-$totalspent;
		$totalgrosscolor = "green";
		if ($totalgrossmargin<0) $totalgrosscolor="red";
		?>
		<tr>
			<td><b><?php echo $langs->trans("BudgetReportTotal"); ?></b></td>
			<td align="right"><b><?php echo lmdbadvancedproject_format_price($totalorders); ?></b></td>
			<td align="right"><b><?php echo lmdbadvancedproject_format_price($totalcustomerinvoices).' ('.lmdbadvancedproject_format_percentage($totalcustomerinvoices, $totalorders).')'; ?></b></td>
			<td align="right"><b><?php echo lmdbadvancedproject_format_price($budget); ?></b></td>
			<td align="right"><b><?php echo lmdbadvancedproject_format_price($totalspent); ?></b></td>
			<td align="right" style='color:<?php echo $totalgrosscolor; ?>'><b><?php echo lmdbadvancedproject_format_margin($totalgrossmargin, $totalorders); ?></b></td>
			<td align="right" style='color:<?php echo $blncolor; ?>'><b><?php echo lmdbadvancedproject_format_price($balance); ?></b></td>
		</tr>
	</table>
	</div>
<?php } ?>
</div>

</div>
<?php
	}
}

if (!function_exists('lmdbadvancedproject_render_global_budget_report')) {
	/**
	 * Render the global budget report.
	 *
	 * @param  array<string,mixed> $filters Global report filters
	 * @return void
	 */
	function lmdbadvancedproject_render_global_budget_report($filters = array())
	{
		lmdbadvancedproject_render_budget_report(0, $filters);
	}
}

if (!function_exists('lmdbadvancedproject_render_project_budget_report')) {
	/**
	 * Render the budget report for a single project.
	 *
	 * @param  int  $projectId               Project id
	 * @param  bool $permissionToGeneratePdf Whether PDF generation is allowed
	 * @return void
	 */
	function lmdbadvancedproject_render_project_budget_report($projectId, $permissionToGeneratePdf = false)
	{
		lmdbadvancedproject_render_budget_report((int) $projectId, array(), $permissionToGeneratePdf);
	}
}
