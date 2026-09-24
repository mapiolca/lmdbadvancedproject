<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr> */

/**
 * Restrict a commercial document's third party independently of project access.
 * Aliases are internal SQL identifiers, never request values. An absent optional
 * third party is distinct from an inaccessible or dangling third-party link.
 *
 * @param string $alias Internal document alias
 * @return string
 */
function lmdbadvancedproject_billing_document_scope($alias)
{
	global $db, $user;
	if (!preg_match('/^[a-z][a-z0-9_]*$/', $alias)) {
		throw new InvalidArgumentException('Invalid document alias');
	}
	$thirdparty = 'SELECT bs.rowid FROM '.MAIN_DB_PREFIX.'societe bs WHERE bs.rowid = '.$alias.'.fk_soc'
		.' AND bs.entity IN ('.$db->sanitize(getEntity('societe')).')';
	if ($user->socid > 0) {
		$thirdparty .= ' AND bs.rowid = '.((int) $user->socid);
	} elseif (!$user->hasRight('societe', 'client', 'voir')) {
		$thirdparty .= ' AND EXISTS (SELECT bsc.fk_soc FROM '.MAIN_DB_PREFIX.'societe_commerciaux bsc'
			.' WHERE bsc.fk_soc = bs.rowid AND bsc.fk_user = '.((int) $user->id).')';
	}
	return ' AND ('.($user->socid > 0 ? '' : 'COALESCE('.$alias.'.fk_soc, 0) = 0 OR ').'EXISTS ('.$thirdparty.'))';
}

/**
 * One definition of customer contributions for list, report and summary.
 * The report permission grants analytical totals; native document permissions
 * are still required by the callers that expose links to individual documents.
 * A split replaces its complete source line, including allocations elsewhere.
 *
 * @param array<string,mixed> $filters Report observation period
 * @return array{orders:string,invoices:string}
 */
function lmdbadvancedproject_billing_sources(array $filters = array())
{
	global $db;
	$filters = lmdbadvancedproject_normalize_budget_report_filters($filters);
	$orderWhere = 'c.fk_projet > 0 AND c.fk_statut > 0 AND c.entity IN ('.$db->sanitize(getEntity('commande')).')'
		.' AND '.lmdbadvancedproject_build_content_date_sql_condition('c.date_commande', $filters)
		.lmdbadvancedproject_billing_document_scope('c');
	$orders = 'SELECT c.fk_projet, c.rowid AS document_id, c.ref, c.date_commande AS document_date, COALESCE(c.total_ht, 0) AS amount'
		.' FROM '.MAIN_DB_PREFIX.'commande c WHERE '.$orderWhere;
	$invoiceEntities = $db->sanitize(getEntity('facture'));
	$invoiceWhere = 'f.fk_statut IN (1,2) AND f.entity IN ('.$invoiceEntities.')'
		.' AND '.lmdbadvancedproject_build_content_date_sql_condition('f.datef', $filters)
		.lmdbadvancedproject_billing_document_scope('f');
	if (lmdbadvancedproject_customer_invoice_split_report_enabled()) {
		$invoices = 'SELECT f.fk_projet, f.rowid AS document_id, f.ref, f.type, f.datef AS document_date, COALESCE(fd.total_ht, 0) AS amount'
			.' FROM '.MAIN_DB_PREFIX.'facture f INNER JOIN '.MAIN_DB_PREFIX.'facturedet fd ON fd.fk_facture = f.rowid'
			.' WHERE f.fk_projet > 0 AND '.$invoiceWhere
			.' AND NOT EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbadvancedproject_customer_invoice_parts cipx WHERE cipx.fk_facture_det = fd.rowid)'
			.' UNION ALL SELECT cip.fk_projet, f.rowid AS document_id, f.ref, f.type, f.datef AS document_date, COALESCE(cip.total_ht, 0) AS amount'
			.' FROM '.MAIN_DB_PREFIX.'lmdbadvancedproject_customer_invoice_parts cip'
			.' INNER JOIN '.MAIN_DB_PREFIX.'facture f ON f.rowid = cip.fk_facture'
			.' INNER JOIN '.MAIN_DB_PREFIX.'facturedet fd ON fd.rowid = cip.fk_facture_det AND fd.fk_facture = f.rowid'
			.' WHERE cip.fk_projet > 0 AND cip.entity IN ('.$invoiceEntities.') AND '.$invoiceWhere;
	} else {
		$invoices = 'SELECT f.fk_projet, f.rowid AS document_id, f.ref, f.type, f.datef AS document_date, COALESCE(f.total_ht, 0) AS amount'
			.' FROM '.MAIN_DB_PREFIX.'facture f WHERE f.fk_projet > 0 AND '.$invoiceWhere;
	}
	return array('orders' => $orders, 'invoices' => $invoices);
}

/**
 * Correlated aggregates for the native project alias p. No GROUP BY is added
 * to the list FROM/WHERE: native count rewriting must remain valid in v20–v24.
 *
 * @param array<string,mixed> $filters Report observation period
 * @return array{orders:string,invoiced:string,rate:string}
 */
function lmdbadvancedproject_billing_expressions(array $filters = array())
{
	$sources = lmdbadvancedproject_billing_sources($filters);
	$orders = '(SELECT COALESCE(SUM(bo.amount), 0) FROM ('.$sources['orders'].') bo WHERE bo.fk_projet = p.rowid)';
	$invoiced = '(SELECT COALESCE(SUM(bi.amount), 0) FROM ('.$sources['invoices'].') bi WHERE bi.fk_projet = p.rowid)';
	return array('orders' => $orders, 'invoiced' => $invoiced,
		'rate' => '(CASE WHEN '.$orders.' > 0 THEN 100.0 * '.$invoiced.' / '.$orders.' ELSE NULL END)');
}

/**
 * Financial ratio with an explicit undefined denominator. Only the displayed
 * bar is bounded; the value remains signed and can exceed one hundred percent.
 */
function lmdbadvancedproject_billing_rate(float $invoiced, float $orders): ?float
{
	return $orders > 0 ? 100.0 * $invoiced / $orders : null;
}

/** Native project progress markup, independent of Task workload semantics. */
function lmdbadvancedproject_billing_progress(float $invoiced, float $orders): string
{
	global $langs;
	$rate = lmdbadvancedproject_billing_rate($invoiced, $orders);
	if ($rate === null) {
		return '<span class="opacitymedium classfortooltip" title="'.dol_escape_htmltag($langs->trans('BudgetBillingNotComputable')).'">—</span>';
	}
	$label = price(round($rate), 0, $langs, 1, 0, 0).'%';
	$width = max(0.0, min(100.0, $rate));
	$tooltip = $langs->trans('BudgetBillingAmounts', price(price2num($invoiced, 'MT')), price(price2num($orders, 'MT')));
	return '<div class="progress-group classfortooltip" title="'.dol_escape_htmltag($tooltip).'">'
		.'<span class="progress-text">'.$label.'</span><div class="progress sm">'
		.'<div class="progress-bar progress-bar-info" role="progressbar" aria-valuemin="0" aria-valuemax="100"'
		.' aria-valuenow="'.$width.'" aria-valuetext="'.dol_escape_htmltag($label).'" style="width: '.$width.'%"></div></div></div>';
}
