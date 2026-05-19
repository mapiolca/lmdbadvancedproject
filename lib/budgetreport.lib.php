<?php

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

if (!function_exists('lmdbadvancedproject_round_amount')) {
	/**
	 * Round monetary amounts for display and chart data.
	 *
	 * @param  float|int $amount Amount to round
	 * @return float
	 */
	function lmdbadvancedproject_round_amount($amount)
	{
		return round((float) $amount, 2);
	}
}

if (!function_exists('lmdbadvancedproject_format_price')) {
	/**
	 * Format amounts with Dolibarr's configured currency.
	 *
	 * @param  float|int $amount Amount to format
	 * @return string
	 */
	function lmdbadvancedproject_format_price($amount)
	{
		global $conf, $langs;

		$currencyCode = empty($conf->currency) ? '' : $conf->currency;

		return price(lmdbadvancedproject_round_amount($amount), 0, $langs, 1, 2, 2, $currencyCode);
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
		$text = trim((string) $label);
		if ($text === '') {
			$text = trim(strip_tags((string) $description));
		}
		if ($text === '') {
			return '-';
		}
		if (function_exists('dol_trunc')) {
			return dol_trunc($text, 90);
		}
		if (strlen($text) > 90) {
			return substr($text, 0, 87).'...';
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
			return array('key' => 'cat_'.$productKey, 'label' => $productLabel);
		}
		if ($fkProduct <= 0 && $lineKey !== '') {
			return array('key' => 'cat_'.$lineKey, 'label' => $lineLabel);
		}
		if ($productKey !== '') {
			return array('key' => 'cat_'.$productKey, 'label' => $productLabel);
		}
		if ($lineKey !== '') {
			return array('key' => 'cat_'.$lineKey, 'label' => $lineLabel);
		}

		return array('key' => 'uncategorized', 'label' => $langs->trans('BudgetReportUncategorized'));
	}
}

if (!function_exists('lmdbadvancedproject_add_forecast_line')) {
	/**
	 * Add a line amount to a forecast category.
	 *
	 * @param array<string,mixed> $forecast Forecast data
	 * @param stdClass           $obj      SQL row
	 * @param string             $type     customer_order|supplier_invoice|supplier_order
	 * @return void
	 */
	function lmdbadvancedproject_add_forecast_line(&$forecast, $obj, $type)
	{
		$category = lmdbadvancedproject_get_forecast_category($obj);
		$key = $category['key'];

		if (!isset($forecast['categories'][$key])) {
			$forecast['categories'][$key] = array(
				'label' => $category['label'],
				'order_amount' => 0,
				'order_budget' => 0,
				'supplier_expenses' => 0,
				'order_lines' => array(),
				'supplier_lines' => array(),
			);
		}

		$amount = empty($obj->amount_ht) ? 0 : (float) $obj->amount_ht;
		$budget = empty($obj->budget_ht) ? 0 : (float) $obj->budget_ht;
		$line = array(
			'type' => $type,
			'ref' => empty($obj->document_ref) ? '' : (string) $obj->document_ref,
			'date' => empty($obj->document_date) ? '' : (string) $obj->document_date,
			'label' => lmdbadvancedproject_short_line_label(empty($obj->line_label) ? '' : $obj->line_label, empty($obj->line_description) ? '' : $obj->line_description),
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
		$sql = "SELECT 'customer_order' AS source_type, c.ref AS document_ref, c.date_commande AS document_date, cd.fk_product, cd.label AS line_label, cd.description AS line_description, cd.qty, cd.total_ht AS amount_ht, (COALESCE(cd.buy_price_ht, 0) * COALESCE(cd.qty, 0)) AS budget_ht, ".$categorySql['select']."
			FROM ".MAIN_DB_PREFIX."commande c
			INNER JOIN ".MAIN_DB_PREFIX."commandedet cd ON cd.fk_commande = c.rowid
			".$categorySql['join']."
			WHERE c.fk_projet = ".$projectId." AND c.fk_statut > 0 AND c.entity IN (".$orderEntities.")";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				lmdbadvancedproject_add_forecast_line($forecast, $obj, 'customer_order');
			}
			$db->free($resql);
		}

		$categorySql = lmdbadvancedproject_build_category_sql_parts('facture_fourn_det_extrafields', 'ffd');
		$sql = "SELECT 'supplier_invoice' AS source_type, ff.ref AS document_ref, ff.datef AS document_date, ffd.fk_product, ffd.label AS line_label, ffd.description AS line_description, ffd.qty, ffd.total_ht AS amount_ht, 0 AS budget_ht, ".$categorySql['select']."
			FROM ".MAIN_DB_PREFIX."facture_fourn ff
			INNER JOIN ".MAIN_DB_PREFIX."facture_fourn_det ffd ON ffd.fk_facture_fourn = ff.rowid
			".$categorySql['join']."
			WHERE ff.fk_projet = ".$projectId." AND ff.fk_statut IN (1,2) AND ff.entity IN (".$supplierInvoiceEntities.")";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				lmdbadvancedproject_add_forecast_line($forecast, $obj, 'supplier_invoice');
			}
			$db->free($resql);
		}

		$categorySql = lmdbadvancedproject_build_category_sql_parts('commande_fournisseurdet_extrafields', 'cfd');
		$linkedSupplierInvoiceSql = "SELECT linked.order_id, SUM(linked.total_ht) AS invoiced_ht
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
		$sql = "SELECT 'supplier_order' AS source_type, cf.ref AS document_ref, COALESCE(cf.date_commande, DATE(cf.date_creation)) AS document_date, cfd.fk_product, cfd.label AS line_label, cfd.description AS line_description, cfd.qty,
				CASE
					WHEN COALESCE(cf.total_ht, 0) > 0 THEN COALESCE(cfd.total_ht, 0) * GREATEST(COALESCE(cf.total_ht, 0) - COALESCE(inv.invoiced_ht, 0), 0) / COALESCE(cf.total_ht, 0)
					ELSE 0
				END AS amount_ht,
				0 AS budget_ht, ".$categorySql['select']."
			FROM ".MAIN_DB_PREFIX."commande_fournisseur cf
			INNER JOIN ".MAIN_DB_PREFIX."commande_fournisseurdet cfd ON cfd.fk_commande = cf.rowid
			LEFT JOIN (".$linkedSupplierInvoiceSql.") inv ON inv.order_id = cf.rowid
			".$categorySql['join']."
			WHERE cf.fk_projet = ".$projectId."
			AND cf.fk_statut IN (3,4,5)
			AND COALESCE(cf.billed, 0) = 0
			AND cf.entity IN (".$supplierOrderEntities.")
			HAVING amount_ht > 0";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				lmdbadvancedproject_add_forecast_line($forecast, $obj, 'supplier_order');
			}
			$db->free($resql);
		}

		$sql = "SELECT SUM(ptt.element_duration) / 3600.0 AS total_hours, SUM((ptt.element_duration / 3600.0) * CASE
				WHEN ptt.thm IS NOT NULL AND ptt.thm > 0 THEN ptt.thm
				WHEN u.thm IS NOT NULL AND u.thm > 0 THEN u.thm
				ELSE 0
			END) AS total_cost
			FROM ".MAIN_DB_PREFIX."element_time ptt
			INNER JOIN ".MAIN_DB_PREFIX."projet_task pt ON ptt.fk_element = pt.rowid
			LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = ptt.fk_user
			WHERE ptt.elementtype = 'task' AND ptt.element_duration > 0 AND pt.fk_projet = ".$projectId." AND pt.entity IN (".$projectEntities.")";
		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$forecast['time']['hours'] = empty($obj->total_hours) ? 0 : (float) $obj->total_hours;
				$forecast['time']['cost'] = empty($obj->total_cost) ? 0 : (float) $obj->total_cost;
			}
			$db->free($resql);
		}

		$sql = "SELECT ex.ref, ed.date, ed.comments, ed.total_ht
			FROM ".MAIN_DB_PREFIX."expensereport_det ed
			LEFT JOIN ".MAIN_DB_PREFIX."expensereport ex ON ed.fk_expensereport = ex.rowid
			WHERE ed.fk_projet = ".$projectId." AND ex.fk_user_approve > 0 AND ex.entity IN (".$expenseReportEntities.")
			ORDER BY ed.date ASC, ex.ref ASC";
		$resql = $db->query($sql);
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$amount = empty($obj->total_ht) ? 0 : (float) $obj->total_ht;
				$forecast['expenses']['total'] += $amount;
				$forecast['expenses']['lines'][] = array(
					'ref' => empty($obj->ref) ? '' : (string) $obj->ref,
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

		print '<button type="button" class="button budgetreport-modal-open" data-budgetreport-modal-target="'.$safeModalId.'">'.$title.' ('.count($lines).')</button>';
		print '<div class="budgetreport-modal" id="'.$safeModalId.'" aria-hidden="true">';
		print '<div class="budgetreport-modal-backdrop" data-budgetreport-modal-close="1"></div>';
		print '<div class="budgetreport-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="'.$safeModalId.'-title">';
		print '<div class="budgetreport-modal-header">';
		print '<div class="budgetreport-modal-title" id="'.$safeModalId.'-title">'.$title.'</div>';
		print '<button type="button" class="button budgetreport-modal-close" data-budgetreport-modal-close="1">'.$langs->trans('Close').'</button>';
		print '</div>';
		print '<div class="budgetreport-modal-body">';
		print '<table class="budgetreport-forecast-subtable">';
		print '<tr><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('Qty').'</th><th>'.$langs->trans('AmountHTShort').'</th>';
		if ($showBudget) {
			print '<th>'.$langs->trans('BudgetReportOrderBudget').'</th>';
		}
		print '</tr>';
		foreach ($lines as $line) {
			$typeLabel = '';
			if ($line['type'] === 'supplier_order') {
				$typeLabel = $langs->trans('BudgetReportSupplierOrderNotInvoiced').' - ';
			} elseif ($line['type'] === 'supplier_invoice') {
				$typeLabel = $langs->trans('BudgetReportSupplierInvoice').' - ';
			}
			print '<tr>';
			print '<td>'.$typeLabel.lmdbadvancedproject_escape_html($line['ref']).'</td>';
			print '<td>'.lmdbadvancedproject_escape_html($line['date']).'</td>';
			print '<td>'.lmdbadvancedproject_escape_html($line['label']).'</td>';
			print '<td align="right">'.price($line['qty']).'</td>';
			print '<td align="right">'.lmdbadvancedproject_format_price($line['amount']).'</td>';
			if ($showBudget) {
				print '<td align="right">'.lmdbadvancedproject_format_price($line['budget']).'</td>';
			}
			print '</tr>';
		}
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

if (!function_exists('lmdbadvancedproject_print_project_forecast')) {
	/**
	 * Print project forecast table.
	 *
	 * @param array<string,mixed> $forecast Forecast data
	 * @return void
	 */
	function lmdbadvancedproject_print_project_forecast($forecast)
	{
		global $langs;

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
			print '<td>'.lmdbadvancedproject_escape_html($category['label']).'</td>';
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

		print '<div class="budgetreport-forecast-extra">';
		print '<div class="budgettitle budgetreport-forecast-subtitle">'.$langs->trans('BudgetReportTimeSpentTotal').'</div>';
		print '<table class="budgettbl">';
		print '<tr><th>'.$langs->trans('BudgetReportTimeSpentHours').'</th><th>'.$langs->trans('BudgetReportSpent').'</th></tr>';
		print '<tr><td align="right">'.price(lmdbadvancedproject_round_amount($forecast['time']['hours'])).'</td><td align="right">'.lmdbadvancedproject_format_price($forecast['time']['cost']).'</td></tr>';
		print '</table>';

		print '<div class="budgettitle budgetreport-forecast-subtitle">'.$langs->trans('BudgetReportExpenseReportDetails').'</div>';
		print '<table class="budgettbl">';
		print '<tr><th>'.$langs->trans('Date').'</th><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('BudgetReportExpenseComment').'</th><th>'.$langs->trans('AmountHTShort').'</th></tr>';
		foreach ($forecast['expenses']['lines'] as $line) {
			print '<tr>';
			print '<td>'.lmdbadvancedproject_escape_html($line['date']).'</td>';
			print '<td>'.lmdbadvancedproject_escape_html($line['ref']).'</td>';
			print '<td>'.lmdbadvancedproject_escape_html($line['comment']).'</td>';
			print '<td align="right">'.lmdbadvancedproject_format_price($line['amount']).'</td>';
			print '</tr>';
		}
		print '<tr><td colspan="3"><b>'.$langs->trans('BudgetReportTotal').'</b></td><td align="right"><b>'.lmdbadvancedproject_format_price($forecast['expenses']['total']).'</b></td></tr>';
		print '</table>';
		print '</div>';
		lmdbadvancedproject_print_budgetreport_modal_script();
	}
}

if (!function_exists('lmdbadvancedproject_render_budget_report')) {
	/**
	 * Render the budget report body.
	 *
	 * @param  int $budgetReportProjectId Project id for project tab, 0 for global report
	 * @return void
	 */
	function lmdbadvancedproject_render_budget_report($budgetReportProjectId = 0)
	{
		global $db, $conf, $langs, $user;

		if (!$user->rights->projet->lire || empty($user->rights->lmdbadvancedproject->budgetreport->read)) {
			accessforbidden();
		}

		$budgetReportProjectId = (int) $budgetReportProjectId;

$datenow = date('Y-m-d');
$projects = array();
$mobudget = array();
$mospent = array();
$cleanmos = array();

$totaltime = 0;
$totalvendinv = 0;
$totalsupplierordersremaining = 0;
$totalexpenses = 0;
$totalorders = 0;
$budget = 0;

$budgetReportProjectId = empty($budgetReportProjectId) ? 0 : (int) $budgetReportProjectId;
$projectSqlFilter = $budgetReportProjectId > 0 ? " AND p.rowid = ".$budgetReportProjectId : "";
$orderProjectSqlFilter = $budgetReportProjectId > 0 ? " AND c.fk_projet = ".$budgetReportProjectId : "";
$taskProjectSqlFilter = $budgetReportProjectId > 0 ? " AND pt.fk_projet = ".$budgetReportProjectId : "";
$vendorInvoiceProjectSqlFilter = $budgetReportProjectId > 0 ? " AND ff.fk_projet = ".$budgetReportProjectId : "";
$supplierOrderProjectSqlFilter = $budgetReportProjectId > 0 ? " AND cf.fk_projet = ".$budgetReportProjectId : "";
$expenseProjectSqlFilter = $budgetReportProjectId > 0 ? " AND ed.fk_projet = ".$budgetReportProjectId : "";

$entityShared = (lmdbadvancedproject_is_multicompany_enabled() && !empty($conf->global->LMDBADVANCEDPROJECT_MULTICOMPANY_ALL_ENTITIES)) ? 1 : 0;
$projectDisplayEntityShared = $budgetReportProjectId > 0 ? 1 : $entityShared;
$projectEntities = lmdbadvancedproject_get_entity_filter('project', $projectDisplayEntityShared);
$projectDataEntities = lmdbadvancedproject_get_entity_filter('project', 1);
$orderEntities = lmdbadvancedproject_get_entity_filter('commande', 1);
$supplierInvoiceEntities = lmdbadvancedproject_get_entity_filter('supplier_invoice', 1);
$supplierOrderEntities = lmdbadvancedproject_get_entity_filter('supplier_order', 1);
$expenseReportEntities = lmdbadvancedproject_get_entity_filter('expensereport', 1);

$budgetReportMulticompanyInfoKey = 'BudgetReportMulticompanyInactiveInfo';
if (lmdbadvancedproject_is_multicompany_enabled()) {
	$budgetReportMulticompanyInfoKey = $entityShared ? 'BudgetReportMulticompanyAllEntitiesInfo' : 'BudgetReportMulticompanyCurrentEntityInfo';
}

$sql = "SELECT p.*, cmd.total_orders, COALESCE(cmdbudget.total_budget, 0) AS total_budget FROM ".MAIN_DB_PREFIX."projet p
		INNER JOIN (
			SELECT c.fk_projet, SUM(COALESCE(c.total_ht, 0)) as total_orders
			FROM ".MAIN_DB_PREFIX."commande c
			WHERE c.fk_projet > 0 AND c.fk_statut > 0 AND c.entity IN (".$orderEntities.")".$orderProjectSqlFilter."
			GROUP BY c.fk_projet
		) cmd ON cmd.fk_projet = p.rowid
		LEFT JOIN (
			SELECT c.fk_projet, SUM(COALESCE(cd.buy_price_ht, 0) * COALESCE(cd.qty, 0)) as total_budget
			FROM ".MAIN_DB_PREFIX."commande c
			INNER JOIN ".MAIN_DB_PREFIX."commandedet cd ON cd.fk_commande = c.rowid
			WHERE c.fk_projet > 0 AND c.fk_statut > 0 AND c.entity IN (".$orderEntities.")".$orderProjectSqlFilter."
			GROUP BY c.fk_projet
		) cmdbudget ON cmdbudget.fk_projet = p.rowid
		WHERE p.fk_statut=1 AND p.entity IN (".$projectEntities.")".$projectSqlFilter."
		ORDER BY cmd.total_orders DESC";


$result = $db->query($sql);
$nbtotalofrecords = $db->num_rows($result);

$i=0;
while ($i<$nbtotalofrecords) {
	$obj = $db->fetch_object($result);
	$projectOrders = (float)$obj->total_orders;
	$projectBudget = (float)$obj->total_budget;

	$projects[$obj->rowid] = array ("ref"=>$obj->rowid,
									"project_ref"=>$obj->ref,
									"title"=>$obj->title,
									"public"=>(int) $obj->public,
									"budget"=>$projectBudget,
									"orders"=>$projectOrders,
									"spent"=>0);
	//total up all budget
	$budget += $projectBudget;
	$totalorders += $projectOrders;

	//separate budget by months
	if (empty($obj->datee) || $obj->datee<$obj->dateo) {
		$yrmo = date('Y-m',strtotime($obj->dateo));
		$cleanmos[$yrmo] = $yrmo;
		if (!isset($mobudget[$yrmo])) {
			$mobudget[$yrmo] = 0;
		}

		$mobudget[$yrmo] += $projectBudget;

	} else if (!empty($obj->dateo) && $projectBudget>0) {
		$j = 0; $molist = array();
		$yrmo = date('Y-m',strtotime($obj->dateo));
		$yrme = date('Y-m',strtotime($obj->datee));

		while ($yrmo<=$yrme && $j<37) {
			$molist[$j] = $yrmo;
			$cleanmos[$yrmo] = $yrmo;

			$j++;
			$yrmo = date("Y-m",strtotime($obj->dateo." +$j months") );
		}
		//echo $j; var_dump ($molist); exit;

		$permonth = $projectBudget/$j;
		foreach ($molist as $mos) {
			if (!isset($mobudget[$mos])) {
				$mobudget[$mos] = 0;
			}
			$mobudget[$mos] += $permonth;
		}
	}

	$i++;
}

$db->free($result);

//var_dump ($mobudget); exit;

//----start: adding timespent to spent item
$timespent = array();
$sql0 = "SELECT pt.fk_projet, ptt.element_date AS task_date, SUM((ptt.element_duration / 3600.0) * CASE
				WHEN ptt.thm IS NOT NULL AND ptt.thm > 0 THEN ptt.thm
				WHEN u.thm IS NOT NULL AND u.thm > 0 THEN u.thm
				ELSE 0
			END) AS totalspent
		FROM ".MAIN_DB_PREFIX."element_time ptt
		INNER JOIN ".MAIN_DB_PREFIX."projet_task pt ON ptt.fk_element = pt.rowid
		LEFT JOIN ".MAIN_DB_PREFIX."user u ON u.rowid = ptt.fk_user
		WHERE ptt.elementtype = 'task' AND ptt.element_duration > 0 AND pt.entity IN (".$projectDataEntities.")".$taskProjectSqlFilter."
		GROUP BY pt.fk_projet, ptt.element_date";
$result0 = $db->query($sql0);
$nbtotal0 = $db->num_rows($result0);
$i=0;
while ($i<$nbtotal0) {
	$obj = $db->fetch_object($result0);
	if ($obj->totalspent>0) {
		if (!isset($timespent[$obj->fk_projet][$obj->task_date])) {
			$timespent[$obj->fk_projet][$obj->task_date] = 0;
		}
		$timespent[$obj->fk_projet][$obj->task_date] += (float)$obj->totalspent;
	}
	$i++;
}

foreach ($projects as $pid=>$data) {
	if (isset($timespent[$pid])) {
		foreach ($timespent[$pid] as $dt=>$val) {
			$projects[$pid]["spent"] += (float)$val;
			$totaltime += (float)$val;

			$yrmo = date('Y-m',strtotime($dt));
			$cleanmos[$yrmo] = $yrmo;
			if (!isset($mospent[$yrmo])) {
				$mospent[$yrmo] = 0;
			}
			$mospent[$yrmo] += (float)$val;
		}
	}
}
//----end: adding timespent to spent item


//----start: adding vendor invoices to spent item
$vendorinvs = array();
$sql1 = "SELECT ff.datef, ff.fk_projet, SUM(ff.total_ht) as total_inv FROM ".MAIN_DB_PREFIX."facture_fourn ff
			WHERE ff.fk_projet > 0 AND ff.fk_statut IN (1,2) AND ff.entity IN (".$supplierInvoiceEntities.")".$vendorInvoiceProjectSqlFilter." GROUP BY ff.fk_projet, ff.datef";
$result1 = $db->query($sql1);
$nbtotal1 = $db->num_rows($result1);
$i=0;
while ($i<$nbtotal1) {
	$obj = $db->fetch_object($result1);
	if (!isset($vendorinvs[$obj->fk_projet][$obj->datef])) {
		$vendorinvs[$obj->fk_projet][$obj->datef] = 0;
	}
	$vendorinvs[$obj->fk_projet][$obj->datef] += (float)$obj->total_inv;
	$i++;
}

foreach ($projects as $pid=>$data) {
	if (isset($vendorinvs[$pid])) {
		foreach ($vendorinvs[$pid] as $dt=>$val) {
			$projects[$pid]["spent"] += (float)$val;
			$totalvendinv += (float)$val;

			$yrmo = date('Y-m',strtotime($dt));
			$cleanmos[$yrmo] = $yrmo;
			if (!isset($mospent[$yrmo])) {
				$mospent[$yrmo] = 0;
			}
			$mospent[$yrmo] += (float)$val;
		}
	}
}
//----end: adding vendor invoices to spent item


//----start: adding supplier orders remaining to spent item
$supplierorders = array();
$linkedSupplierInvoiceSql = "SELECT linked.order_id, SUM(linked.total_ht) AS invoiced_ht
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
$sql3 = "SELECT cf.fk_projet, COALESCE(cf.date_commande, DATE(cf.date_creation)) AS order_date,
			SUM(GREATEST(COALESCE(cf.total_ht, 0) - COALESCE(inv.invoiced_ht, 0), 0)) as total_order_remaining
			FROM ".MAIN_DB_PREFIX."commande_fournisseur cf
			LEFT JOIN (".$linkedSupplierInvoiceSql.") inv ON inv.order_id = cf.rowid
			WHERE cf.fk_projet > 0
			AND cf.fk_statut IN (3,4,5)
			AND COALESCE(cf.billed, 0) = 0
			AND cf.entity IN (".$supplierOrderEntities.")".$supplierOrderProjectSqlFilter."
			GROUP BY cf.fk_projet, order_date";
$result3 = $db->query($sql3);
$nbtotal3 = $db->num_rows($result3);
$i=0;
while ($i<$nbtotal3) {
	$obj = $db->fetch_object($result3);
	if ($obj->total_order_remaining > 0) {
		if (!isset($supplierorders[$obj->fk_projet][$obj->order_date])) {
			$supplierorders[$obj->fk_projet][$obj->order_date] = 0;
		}
		$supplierorders[$obj->fk_projet][$obj->order_date] += (float)$obj->total_order_remaining;
	}
	$i++;
}

foreach ($projects as $pid=>$data) {
	if (isset($supplierorders[$pid])) {
		foreach ($supplierorders[$pid] as $dt=>$val) {
			$projects[$pid]["spent"] += (float)$val;
			$totalsupplierordersremaining += (float)$val;

			$yrmo = date('Y-m',strtotime($dt));
			$cleanmos[$yrmo] = $yrmo;
			if (!isset($mospent[$yrmo])) {
				$mospent[$yrmo] = 0;
			}
			$mospent[$yrmo] += (float)$val;
		}
	}
}
//----end: adding supplier orders remaining to spent item


//----start: adding expenses to spent item
$expenses = array();
$sql2 = "SELECT ed.date, ed.fk_projet, SUM(ed.total_ht) as total_exp FROM ".MAIN_DB_PREFIX."expensereport_det ed
		 LEFT JOIN ".MAIN_DB_PREFIX."expensereport ex ON ed.fk_expensereport = ex.rowid
			WHERE ed.fk_projet > 0 AND ex.fk_user_approve>0 AND ex.entity IN (".$expenseReportEntities.")".$expenseProjectSqlFilter." GROUP BY ed.fk_projet, ed.date ";
$result2 = $db->query($sql2);
$nbtotal2 = $db->num_rows($result2);
$i=0;
while ($i<$nbtotal2) {
	$obj = $db->fetch_object($result2);
	if (!isset($expenses[$obj->fk_projet][$obj->date])) {
		$expenses[$obj->fk_projet][$obj->date] = 0;
	}
	$expenses[$obj->fk_projet][$obj->date] += (float)$obj->total_exp;
	$i++;
}

foreach ($projects as $pid=>$data) {
	if (isset($expenses[$pid])) {
		foreach ($expenses[$pid] as $dt=>$val) {
			$projects[$pid]["spent"] += (float)$val;
			$totalexpenses += (float)$val;

			$yrmo = date('Y-m',strtotime($dt));
			$cleanmos[$yrmo] = $yrmo;
			if (!isset($mospent[$yrmo])) {
				$mospent[$yrmo] = 0;
			}
			$mospent[$yrmo] += (float)$val;
		}
	}
}
//----end: adding expenses to spent item


//processing data for view
$totalspent = $totaltime+$totalvendinv+$totalsupplierordersremaining+$totalexpenses;
$balance = $budget-$totalspent;

$blncolor = "green";
if ($balance<0) {
	$blncolor="red";
}

$labels = array();
$budgets = array();
$spents = array();
$budgetFormattedValues = array();
$spentFormattedValues = array();

foreach ($projects as $data) {
	$labels[] = $data["title"];
	$budgets[] = lmdbadvancedproject_round_amount($data["budget"]);
	$spents[] = lmdbadvancedproject_round_amount($data["spent"]);
	$budgetFormattedValues[] = lmdbadvancedproject_format_price($data["budget"]);
	$spentFormattedValues[] = lmdbadvancedproject_format_price($data["spent"]);
}

$spentLabels = array(
	lmdbadvancedproject_trans_chart("BudgetReportTimeSpentOnTasks"),
	lmdbadvancedproject_trans_chart("BudgetReportVendorInvoices"),
	lmdbadvancedproject_trans_chart("BudgetReportSupplierOrderNotInvoiced"),
	lmdbadvancedproject_trans_chart("BudgetReportStaffExpenses"),
);
$spentValues = array(
	lmdbadvancedproject_round_amount($totaltime),
	lmdbadvancedproject_round_amount($totalvendinv),
	lmdbadvancedproject_round_amount($totalsupplierordersremaining),
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

?>

<div class="info"><?php echo $langs->trans($budgetReportMulticompanyInfoKey); ?></div>

<?php if ($budgetReportProjectId > 0 && empty($projects)) { ?>
<div class="warning"><?php echo $langs->trans("BudgetReportProjectNoData"); ?></div>
<?php return; } ?>

<div class="budgetreport-summary-fullwidth">
<div class="dashboard_budget">
	<figure>
		<div class='figurein'>
			<div class="budgettitle"><?php echo $langs->trans("BudgetReportMarket"); ?></div>
			<div class="famount">
				<?php echo lmdbadvancedproject_format_price($totalorders); ?>
			</div>
		</div>
	</figure>

	<figure>
		<div class='figurein'>
			<div class="budgettitle"><?php echo $langs->trans("BudgetReportBudget"); ?></div>
			<div class="famount">
				<?php echo lmdbadvancedproject_format_price($budget); ?>
			</div>
		</div>
	</figure>

	<figure>
		<div class='figurein'>
			<div class="budgettitle"><?php echo $langs->trans("BudgetReportSpent"); ?></div>
			<div class="famount">
				<?php echo lmdbadvancedproject_format_price($totalspent); ?>
			</div>
		</div>
	</figure>

	<figure style="border-right: 0;">
		<div class='figurein'>
			<div class="budgettitle"><?php echo $langs->trans("BudgetReportLeftToSpend"); ?></div>
			<div class="famount" style='color:<?php echo $blncolor; ?>'>
				<?php echo lmdbadvancedproject_format_price($balance); ?>
			</div>
		</div>
	</figure>
</div>
</div>

<div class="budgetreport-report">

<div class="budgetreport-charts-row">
<div class="budgetreport-chart-panel">
	<div class="budgettitle"><?php echo $langs->trans("BudgetReportBudgetByProjects"); ?></div>
	<div class="budgetchart">
	<canvas id="canvas_idgraphstatus"></canvas>
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
	function budgetReportFormatChartValue(value) {
		if (budgetReportCurrencyCode && typeof Intl !== 'undefined') {
			try {
				return new Intl.NumberFormat(undefined, {
					style: 'currency',
					currency: budgetReportCurrencyCode,
					minimumFractionDigits: 2,
					maximumFractionDigits: 2
				}).format(value);
			} catch (e) {}
		}

		value = Number(value);
		if (isNaN(value)) {
			value = 0;
		}
		value = value.toFixed(2).split('.');
		value[0] = value[0].split(/(?=(?:...)*$)/).join(',');
		return value.join('.');
	}

	var budgetFormattedValues = <?php echo json_encode(array_values($budgetFormattedValues)); ?>;
	var budget_config = {
			type: 'pie',
			data: {
				datasets: [{
					label: <?php echo json_encode($langs->trans("BudgetReportBudgetByProjects")); ?>,
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
					text: <?php echo json_encode($langs->trans("BudgetReportBudgetByProjects")); ?>
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
	<div class="budgetchart">
	<canvas id="canvas_idgraphspent"></canvas>
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
										window.chartColors.pink,
										window.chartColors.orange,
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




	<?php
	//clean up data
	$molabels = array();
	$mobudgets = array();
	$mospents = array();
	$mobudgetFormattedValues = array();
	$mospentFormattedValues = array();
	asort($cleanmos);
	foreach ($cleanmos as $id=>$data) {
		$monthBudget = empty($mobudget[$data]) ? 0 : $mobudget[$data];
		$monthSpent = empty($mospent[$data]) ? 0 : $mospent[$data];
		$molabels[] = date("M'y",strtotime($data."-01"));
		$mobudgets[] = lmdbadvancedproject_round_amount($monthBudget);
		$mospents[] = lmdbadvancedproject_round_amount($monthSpent);
		$mobudgetFormattedValues[] = lmdbadvancedproject_format_price($monthBudget);
		$mospentFormattedValues[] = lmdbadvancedproject_format_price($monthSpent);
	}
	?>

<div class="budgetreport-month-section">
	<div class="budgettitle"><?php echo $langs->trans("BudgetReportBudgetVsSpentByMonth"); ?></div>
	<div class="budgetbarchart">
	<canvas id="canvas_idgraphmonth"></canvas>
	</div>

	<script id="idgraphmonth">
	var color = Chart.helpers.color;
	var monthFormattedValues = [
		<?php echo json_encode(array_values($mobudgetFormattedValues)); ?>,
		<?php echo json_encode(array_values($mospentFormattedValues)); ?>
	];
	var month_config = {
			type: 'bar',
			data: {
				datasets: [{
					label: <?php echo json_encode(lmdbadvancedproject_trans_chart("BudgetReportBudget")); ?>,
					data: <?php echo json_encode(array_values($mobudgets)); ?>,
					backgroundColor: color(window.chartColors.blue).alpha(0.4).rgbString(),
					borderColor: window.chartColors.blue,
					borderWidth: 1,
					},
					{
					label: <?php echo json_encode(lmdbadvancedproject_trans_chart("BudgetReportSpent")); ?>,
					type: 'line',
					data: <?php echo json_encode(array_values($mospents)); ?>,
					backgroundColor: color(window.chartColors.red).alpha(0).rgbString(),
					borderColor: window.chartColors.red,
					borderWidth: 1,
					}
					],
				labels: <?php echo json_encode(array_values($molabels)); ?>,
				},

			options: {
				responsive: true,
				maintainAspectRatio: false,
				legend: {
					position: 'top',
				},
				title: {
					display: false,
					text: <?php echo json_encode(lmdbadvancedproject_trans_chart("BudgetReportBudgetVsSpentByMonth")); ?>
				},
				animation: {
					animateScale: true,
					animateRotate: true
				},

				tooltips: {
				  mode: 'label',
				  callbacks: {
						label: function(tooltipItem, data) {
							var label = data.datasets[tooltipItem.datasetIndex].label || '';
							return label+': '+monthFormattedValues[tooltipItem.datasetIndex][tooltipItem.index];
						}
				  }
				}, //end tooltips

				scales: {
					yAxes: [{
						ticks: {
							beginAtZero:true,
							userCallback: function(value, index, values) {
								return budgetReportFormatChartValue(value);
							}
						}
					}],
					xAxes: [{
						ticks: {
						}
					}]
				},  //end scales

			}
		};

	var ctx = document.getElementById("canvas_idgraphmonth").getContext("2d");
	var chart = new Chart(ctx, month_config);
	</script>
</div>

<div class="budgetreport-table-section">
<?php if ($budgetReportProjectId > 0) { ?>
	<div class="budgettitle"><?php echo $langs->trans("BudgetReportForecastBudget"); ?></div>
	<?php lmdbadvancedproject_print_project_forecast($budgetReportForecast); ?>
<?php } else { ?>
	<div class="budgettitle"><?php echo $langs->trans("BudgetReportBudgetVsSpentByProject"); ?></div>
	<table class="budgettbl">
		<tr>
			<th><?php echo $langs->trans("BudgetReportProject"); ?></th>
			<th><?php echo $langs->trans("BudgetReportMarket"); ?></th>
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
			<td align="right"><?php echo lmdbadvancedproject_format_price($data['orders']); ?></td>
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
			<td align="right"><b><?php echo lmdbadvancedproject_format_price($budget); ?></b></td>
			<td align="right"><b><?php echo lmdbadvancedproject_format_price($totalspent); ?></b></td>
			<td align="right" style='color:<?php echo $totalgrosscolor; ?>'><b><?php echo lmdbadvancedproject_format_margin($totalgrossmargin, $totalorders); ?></b></td>
			<td align="right" style='color:<?php echo $blncolor; ?>'><b><?php echo lmdbadvancedproject_format_price($balance); ?></b></td>
		</tr>
	</table>
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
	 * @return void
	 */
	function lmdbadvancedproject_render_global_budget_report()
	{
		lmdbadvancedproject_render_budget_report(0);
	}
}

if (!function_exists('lmdbadvancedproject_render_project_budget_report')) {
	/**
	 * Render the budget report for a single project.
	 *
	 * @param  int $projectId Project id
	 * @return void
	 */
	function lmdbadvancedproject_render_project_budget_report($projectId)
	{
		lmdbadvancedproject_render_budget_report((int) $projectId);
	}
}
