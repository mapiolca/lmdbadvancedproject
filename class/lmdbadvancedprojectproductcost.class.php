<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

require_once __DIR__.'/lmdbadvancedprojectcostledger.class.php';
require_once __DIR__.'/lmdbadvancedprojectcompatibility.class.php';

/**
 * Native document selection and auditable price snapshots for the cost ledger.
 *
 * @phpstan-import-type CostLine from LmdbAdvancedProjectCostLedger
 * @phpstan-import-type CostReport from LmdbAdvancedProjectCostLedger
 * @phpstan-type Tariff array{price:?float,date:string,id:int,history:int,status:string,currency:string}
 */
class LmdbAdvancedProjectProductCost
{
	/** @var DoliDB */
	private $db;
	/** @var string */
	public $error = '';
	/** @var array<int,string> Native base currencies, loaded only for authorized entities. */
	private $currencies = array();

	/**
	 * @param DoliDB $db */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/** Only the ledger owns accessible referenced products; legacy free lines stay intact. */
	public static function legacyLineFilter(string $alias): string
	{
		global $db;
		if (!LmdbAdvancedProjectCompatibility::isShipmentCostEnabled()) {
			return '';
		}
		if (!in_array($alias, array('ffd', 'cfd'), true)) {
			throw new InvalidArgumentException('Invalid source alias');
		}
		return ' AND COALESCE('.$alias.'.fk_product,0) NOT IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'product WHERE entity IN ('.$db->sanitize(getEntity('product')).'))';
	}

	/**
	 * Read-only report, restricted to the project IDs already authorized by the caller.
	 *
	 * @param list<int> $projectIds
	 *
	 * @param array<string,mixed> $filters
	 *
	 * @param array{ref?:string,label?:string,type?:string,entities?:list<int>} $search Native list filters, applied in SQL before reconciliation
	 *
	 * @return CostReport
	 */
	public function load(array $projectIds, array $filters = array(), array $search = array()): array
	{
		global $conf, $user;
		$empty = array('products' => array(), 'events' => array(), 'issues' => array(), 'complete' => true);
		if (!LmdbAdvancedProjectCompatibility::isShipmentCostEnabled() || !$projectIds) {
			return $empty;
		}
		if (!$user->hasRight('projet', 'lire') || !$user->hasRight('lmdbadvancedproject', 'budgetreport', 'read')) {
			throw new RuntimeException('BudgetCostAccessDenied');
		}
		$authorized = lmdbadvancedproject_get_budget_report_authorized_project_ids();
		if (is_array($authorized)) {
			$projectIds = array_values(array_intersect($projectIds, $authorized));
		}
		if (!$projectIds) {
			return $empty;
		}
		$ids = implode(',', array_map('intval', $projectIds));
		$ids = implode(',', array_map(static function ($row): int { return (int) $row->rowid; }, $this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE rowid IN ('.$ids.') AND entity IN ('.$this->db->sanitize(getEntity('project')).')')));
		if ($ids === '') {
			return $empty;
		}
		$this->loadCurrencies();
		$productEntities = $this->db->sanitize(getEntity('product'));
		$lines = array();
		$queries = array();
		$base = " p.rowid AS product_id, p.ref AS product_ref, p.label AS product_label, p.fk_product_type AS product_type,
			COALESCE(NULLIF(l.fk_unit,0),p.fk_unit,0) AS unit_id, u.label AS unit_label, p.entity AS product_entity, p.fk_unit AS product_unit";
		$productJoin = ' INNER JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid = l.fk_product AND p.entity IN ('.$productEntities.')
			LEFT JOIN '.MAIN_DB_PREFIX.'c_units u ON u.rowid = COALESCE(NULLIF(l.fk_unit,0),p.fk_unit,0)';
		$category = lmdbadvancedproject_build_category_sql_parts('commandedet_extrafields', 'l');
		$queries[] = "SELECT 'customer' AS kind, c.rowid AS document_id, c.ref, c.entity, c.fk_projet AS project_id,
			c.date_commande AS document_date, l.rowid AS line_id, l.qty, l.total_ht AS amount, 0 AS credit, ".$base.', '.$category['select'].'
			FROM '.MAIN_DB_PREFIX.'commande c INNER JOIN '.MAIN_DB_PREFIX.'commandedet l ON l.fk_commande = c.rowid
			'.$productJoin.$category['join'].' WHERE c.fk_projet IN ('.$ids.') AND c.fk_statut IN (1,2,3)
			AND c.entity IN ('.$this->db->sanitize(getEntity('commande')).')';

		$category = lmdbadvancedproject_build_category_sql_parts('facture_fourn_det_extrafields', 'l');
		$invoiceEntities = $this->db->sanitize(getEntity('supplier_invoice'));
		$split = lmdbadvancedproject_supplier_invoice_split_report_enabled();
		$exclusion = lmdbadvancedproject_supplier_invoice_split_source_exclusion_sql('ff', 'l', $invoiceEntities);
		$queries[] = "SELECT 'invoice' AS kind, ff.rowid AS document_id, ff.ref, ff.entity, ff.fk_projet AS project_id,
			ff.datef AS document_date, l.rowid AS line_id, l.qty, l.total_ht AS amount, CASE WHEN ff.type = 2 THEN 1 ELSE 0 END AS credit, ".$base.', '.$category['select'].'
			FROM '.MAIN_DB_PREFIX.'facture_fourn ff INNER JOIN '.MAIN_DB_PREFIX.'facture_fourn_det l ON l.fk_facture_fourn = ff.rowid
			'.$productJoin.$category['join'].' WHERE ff.fk_projet IN ('.$ids.') AND ff.fk_statut IN (1,2)
			AND ff.entity IN ('.$invoiceEntities.')'.$exclusion;
		if ($split) {
			$queries[] = "SELECT 'invoice' AS kind, ff.rowid AS document_id, ff.ref, ff.entity, sip.fk_projet AS project_id,
				ff.datef AS document_date, l.rowid AS line_id, sip.qty, sip.total_ht AS amount, CASE WHEN ff.type = 2 THEN 1 ELSE 0 END AS credit, ".$base.', '.$category['select'].'
				FROM '.MAIN_DB_PREFIX.'lmdbadvancedproject_supplier_invoice_parts sip
				INNER JOIN '.MAIN_DB_PREFIX.'facture_fourn_det l ON l.rowid = sip.fk_facture_fourn_det AND l.fk_facture_fourn = sip.fk_facture_fourn
				INNER JOIN '.MAIN_DB_PREFIX.'facture_fourn ff ON ff.rowid = sip.fk_facture_fourn
				'.$productJoin.$category['join'].' WHERE sip.fk_projet IN ('.$ids.') AND ff.fk_statut IN (1,2)
				AND ff.entity IN ('.$invoiceEntities.') AND sip.entity = ff.entity';
		}
		if ($split) {
			// A precise native order/invoice link releases commitments invoiced to
			// another project, without making those quantities cover its shipments.
			$links = "SELECT fk_source AS order_id, fk_target AS invoice_id FROM ".MAIN_DB_PREFIX."element_element
				WHERE sourcetype IN ('order_supplier','supplier_order') AND targettype IN ('invoice_supplier','supplier_invoice')
				UNION SELECT fk_target AS order_id, fk_source AS invoice_id FROM ".MAIN_DB_PREFIX."element_element
				WHERE targettype IN ('order_supplier','supplier_order') AND sourcetype IN ('invoice_supplier','supplier_invoice')";
			$linkedProjects = 'SELECT links.invoice_id, MIN(oc.fk_projet) AS project_id
				FROM ('.$links.') links INNER JOIN '.MAIN_DB_PREFIX.'commande_fournisseur oc ON oc.rowid = links.order_id
				WHERE oc.fk_projet > 0 AND oc.fk_statut IN (3,4,5) AND oc.entity IN ('.$this->db->sanitize(getEntity('supplier_order')).')
				GROUP BY links.invoice_id HAVING COUNT(DISTINCT oc.fk_projet) = 1';
			$queries[] = "SELECT 'purchase_coverage' AS kind, ff.rowid AS document_id, ff.ref, ff.entity, origin.project_id,
				ff.datef AS document_date, l.rowid AS line_id, sip.qty, 0 AS amount, 0 AS credit, ".$base.', '.$category['select'].'
				FROM ('.$linkedProjects.') origin INNER JOIN '.MAIN_DB_PREFIX.'facture_fourn ff ON ff.rowid = origin.invoice_id
				INNER JOIN '.MAIN_DB_PREFIX.'facture_fourn_det l ON l.fk_facture_fourn = ff.rowid
				INNER JOIN '.MAIN_DB_PREFIX.'lmdbadvancedproject_supplier_invoice_parts sip ON sip.fk_facture_fourn_det = l.rowid AND sip.fk_facture_fourn = ff.rowid AND sip.entity = ff.entity
				'.$productJoin.$category['join'].' WHERE origin.project_id IN ('.$ids.') AND sip.fk_projet <> origin.project_id
				AND ff.fk_statut IN (1,2) AND ff.type <> 2 AND sip.qty > 0 AND ff.entity IN ('.$invoiceEntities.')';
		}

		$category = lmdbadvancedproject_build_category_sql_parts('commande_fournisseurdet_extrafields', 'l');
		$queries[] = "SELECT CASE WHEN c.fk_statut IN (1,2) THEN 'supplier_pending' WHEN c.fk_statut = 3 THEN 'ordered' ELSE 'delivered' END AS kind,
			c.rowid AS document_id, c.ref, c.entity, c.fk_projet AS project_id,
			COALESCE(c.date_commande,DATE(c.date_creation)) AS document_date, l.rowid AS line_id, l.qty, l.total_ht AS amount, 0 AS credit, ".$base.', '.$category['select'].'
			FROM '.MAIN_DB_PREFIX.'commande_fournisseur c INNER JOIN '.MAIN_DB_PREFIX.'commande_fournisseurdet l ON l.fk_commande = c.rowid
			'.$productJoin.$category['join'].' WHERE c.fk_projet IN ('.$ids.') AND c.fk_statut IN (1,2,3,4,5)
			AND c.entity IN ('.$this->db->sanitize(getEntity('supplier_order')).')';

		$category = lmdbadvancedproject_build_category_sql_parts('commandedet_extrafields', 'l');
		$query = "SELECT 'shipment' AS kind, e.rowid AS document_id, e.ref, e.entity,
			CASE WHEN ed.element_type = 'commande' AND ed.fk_elementdet > 0 AND c.rowid IS NULL THEN 1 ELSE 0 END AS source_unavailable,
			COALESCE(NULLIF(e.fk_projet,0),c.fk_projet) AS project_id, e.fk_projet AS shipment_project, c.fk_projet AS order_project,
			COALESCE(e.date_expedition,e.date_valid) AS document_date, e.date_valid, ed.rowid AS line_id, ed.qty, 0 AS amount, 0 AS credit,
			p.rowid AS product_id, p.ref AS product_ref, p.label AS product_label, p.fk_product_type AS product_type,
			COALESCE(NULLIF(l.fk_unit,0),p.fk_unit,0) AS unit_id, u.label AS unit_label, p.entity AS product_entity, p.fk_unit AS product_unit, ".$category['select'].',
			e.date_expedition, sc.snapshot_pmp, sc.snapshot_tariff, sc.tariff_date, sc.fk_supplier_price, sc.pmp_status, sc.tariff_status,
			sc.currency, sc.tariff_currency, sc.snapshot_qty, sc.fk_product AS snapshot_product, sc.fk_unit AS snapshot_unit,
			sc.date_validation AS snapshot_validation, sc.date_shipping AS snapshot_shipping
			FROM '.MAIN_DB_PREFIX.'expedition e INNER JOIN '.MAIN_DB_PREFIX.'expeditiondet ed ON ed.fk_expedition = e.rowid
			LEFT JOIN '.MAIN_DB_PREFIX."commandedet l ON l.rowid = ed.fk_elementdet AND ed.element_type = 'commande'
			LEFT JOIN ".MAIN_DB_PREFIX.'commande c ON c.rowid = l.fk_commande AND c.entity IN ('.$this->db->sanitize(getEntity('commande')).')
			INNER JOIN '.MAIN_DB_PREFIX.'product p ON p.rowid = COALESCE(NULLIF(ed.fk_product,0),l.fk_product) AND p.entity IN ('.$productEntities.')
			LEFT JOIN '.MAIN_DB_PREFIX.'c_units u ON u.rowid = COALESCE(NULLIF(l.fk_unit,0),p.fk_unit,0)
			LEFT JOIN '.MAIN_DB_PREFIX.'lmdbap_shipment_cost sc ON sc.fk_expeditiondet = ed.rowid AND sc.entity = e.entity AND sc.active = 1
			'.$category['join'].' WHERE (e.fk_projet IN ('.$ids.') OR c.fk_projet IN ('.$ids.'))
			AND e.fk_statut IN (1,2,3) AND e.entity IN ('.$this->db->sanitize(getEntity('expedition')).')';
		// Filter the product set, then retain every authorized source of those products
		// so an environment filter cannot remove an invoice that covers a shipment.
		$predicates = array();
		if (lmdbadvancedproject_budget_report_content_period_is_active($filters) && !empty($filters['date_end'])) {
			$predicates[] = "DATE(cs.document_date) <= '".$this->db->escape($filters['date_end'])."'";
		}
		foreach (array('ref' => 'product_ref', 'label' => 'product_label') as $field => $column) {
			if (isset($search[$field]) && $search[$field] !== '') {
				$predicates[] = natural_search('cs.'.$column, $search[$field], 0, 1);
			}
		}
		if (isset($search['type']) && in_array($search['type'], array('0', '1'), true)) {
			$predicates[] = 'cs.product_type = '.(int) $search['type'];
		}
		if (!empty($search['entities'])) {
			$sourceQueries = array_merge($queries, array($query));
			$matches = array();
			foreach ($sourceQueries as $sourceQuery) {
				$matches[] = 'SELECT fs.product_id FROM ('.$sourceQuery.') fs WHERE fs.entity IN ('.implode(',', array_map('intval', $search['entities'])).')';
			}
			$predicates[] = 'cs.product_id IN ('.implode(' UNION ', $matches).')';
		}
		$filterSql = $predicates ? ' WHERE '.implode(' AND ', $predicates) : '';
		foreach ($queries as $sourceQuery) {
			foreach ($this->rows('SELECT * FROM ('.$sourceQuery.') cs'.$filterSql) as $row) {
				$lines[] = $this->sourceLine($row);
			}
		}
		$shipmentRows = $this->rows('SELECT * FROM ('.$query.') cs'.$filterSql);
		$tariffs = $this->tariffCandidates(array_map(static function ($row): int { return (int) $row->product_id; }, $shipmentRows));
		$method = getDolGlobalString('LMDBADVANCEDPROJECT_SHIPMENT_COST_METHOD', 'supplier_tariff');
		$issues = array();
		foreach ($shipmentRows as $row) {
			if ((int) $row->source_unavailable === 1 || ((int) $row->shipment_project > 0 && (int) $row->order_project > 0 && (int) $row->shipment_project !== (int) $row->order_project)) {
				$issue = (int) $row->source_unavailable === 1 ? 'BudgetCostSourceUnavailable' : 'BudgetCostProjectConflict';
				// Keep the product and document quantity visible, without assigning cost
				// or coverage to either contradictory project.
				$row->project_id = in_array((int) $row->shipment_project, array_map('intval', explode(',', $ids)), true) ? $row->shipment_project : $row->order_project;
				$line = $this->sourceLine($row);
				$line['kind'] = 'anomaly';
				$line['issues'][] = $issue;
				$lines[] = $line;
				$issues[] = $issue;
				continue;
			}
			$line = $this->sourceLine($row);
			$validSnapshot = $row->snapshot_validation !== null && $row->snapshot_validation === $row->date_valid
				&& $row->snapshot_shipping === $row->document_date && (int) $row->snapshot_product === $line['product']
				&& (int) $row->snapshot_unit === $line['unit'] && abs((float) $row->snapshot_qty - $line['qty']) < 1.0e-8;
			if ($validSnapshot) {
				$value = $method === 'pmp' ? $row->snapshot_pmp : $row->snapshot_tariff;
				$priceCurrency = $method === 'pmp' ? $row->currency : $row->tariff_currency;
				$line['price'] = $value === null || $priceCurrency !== $conf->currency ? null : (float) $value;
				$line['price_status'] = (string) ($method === 'pmp' ? $row->pmp_status : $row->tariff_status);
				$line['currency'] = (string) $priceCurrency;
				$line['price_date'] = (string) ($method === 'pmp' ? $row->date_valid : $row->tariff_date);
				$line['price_source'] = $method === 'pmp' ? 'BudgetCostPmp' : 'BudgetCostSupplierTariff';
			} elseif ($method === 'supplier_tariff' && $row->snapshot_validation === null && $row->date_valid) {
				// Historical backfill is read-only and only uses provable net prices.
				$tariff = self::selectTariff($tariffs[$line['product']] ?? array(), min((string) $row->document_date, (string) $row->date_valid), (string) $row->date_valid);
				$line['price'] = $tariff['currency'] === $conf->currency ? $tariff['price'] : null;
				$line['price_date'] = $tariff['date'];
				$line['price_status'] = $tariff['status'];
				$line['currency'] = $tariff['currency'];
				$line['price_source'] = 'BudgetCostSupplierTariff';
			}
			if (in_array('BudgetCostIncompatibleUnits', $line['issues'], true)) { $line['price'] = null; }
			if (!$row->date_expedition) {
				$line['price_source'] = $line['price_source'] ?: ($method === 'pmp' ? 'BudgetCostPmp' : 'BudgetCostSupplierTariff');
			}
			$line['date_fallback'] = !$row->date_expedition;
			$lines[] = $line;
		}
		$period = lmdbadvancedproject_budget_report_content_period_is_active($filters);
		$report = LmdbAdvancedProjectCostLedger::build($lines, $period ? (string) ($filters['date_start'] ?? '') : '', $period ? (string) ($filters['date_end'] ?? '') : '');
		$report['issues'] = array_values(array_unique(array_merge($report['issues'], $issues)));
		$report['complete'] = !$report['issues'];
		return $report;
	}

	/**
	 * @param stdClass $row
	 * @return CostLine */
	private function sourceLine($row): array
	{
		global $conf;
		$category = lmdbadvancedproject_get_forecast_category($row);
		$currency = $this->currencies[(int) $row->entity] ?? '';
		$currencyMatches = $currency === $conf->currency;
		$issues = $currencyMatches ? array() : array('BudgetCostCurrencyUnknown');
		if ((int) $row->unit_id > 0 && (int) $row->product_unit > 0 && (int) $row->unit_id !== (int) $row->product_unit) {
			$issues[] = 'BudgetCostIncompatibleUnits';
		}
		if ((int) $row->credit === 1 || (float) $row->qty < 0) {
			$issues[] = 'BudgetCostCreditQuantityUnknown';
		}
		if (!$row->document_date) {
			throw new RuntimeException('BudgetCostMissingDate');
		}
		return array(
			'key' => (string) $row->kind.':'.sprintf('%020d', (int) $row->line_id).':'.(int) $row->project_id,
			'kind' => (string) $row->kind, 'project' => (int) $row->project_id, 'product' => (int) $row->product_id,
			'unit' => (int) $row->unit_id, 'qty' => (float) $row->qty, 'amount' => $currencyMatches ? (float) price2num($row->amount, 'MT') : null,
			'date' => (string) $row->document_date, 'document_id' => (int) $row->document_id, 'ref' => (string) $row->ref,
			'entity' => (int) $row->entity, 'product_ref' => (string) $row->product_ref, 'label' => (string) $row->product_label,
			'product_type' => (int) $row->product_type, 'unit_label' => (string) $row->unit_label,
			'category' => (string) $category['key'], 'category_label' => (string) $category['label'],
			'currency' => $currency, 'price_status' => '', 'date_fallback' => false, 'price' => null, 'price_source' => '', 'price_date' => '', 'issues' => $issues,
		);
	}

	/** Read base currencies before combining shared native monetary amounts. */
	private function loadCurrencies(): void
	{
		global $conf;
		$this->currencies = array();
		$scope = array((int) $conf->entity);
		foreach (array('productsupplierprice', 'supplier_invoice', 'supplier_order', 'expedition', 'commande') as $element) {
			$scope = array_merge($scope, array_map('intval', explode(',', getEntity($element))));
		}
		$rows = $this->rows("SELECT entity, value FROM ".MAIN_DB_PREFIX."const WHERE name = 'MAIN_MONNAIE' AND entity IN (0,".implode(',', array_unique($scope)).") ORDER BY entity");
		$globalCurrency = '';
		foreach ($rows as $row) {
			if ((int) $row->entity === 0) { $globalCurrency = (string) $row->value; }
			$this->currencies[(int) $row->entity] = (string) $row->value;
		}
		foreach ($scope as $entity) {
			if (!isset($this->currencies[$entity])) { $this->currencies[$entity] = $globalCurrency; }
		}
		$this->currencies[(int) $conf->entity] = $conf->currency;
	}

	/** Query failure must never turn into a complete report with zero costs.
	 *
	 * @return list<stdClass>
	 */
	private function rows(string $sql): array
	{
		$result = $this->db->query($sql);
		if (!$result) {
			dol_syslog(__METHOD__.': '.$this->db->lasterror(), LOG_ERR);
			throw new RuntimeException('BudgetCostReadFailed');
		}
		$rows = array();
		while (is_object($row = $this->db->fetch_object($result))) {
			$rows[] = $row;
		}
		$this->db->free($result);
		return $rows;
	}

	/**
	 * Return all known price changes. A newer unprovable price blocks an older
	 * known price, instead of silently falling back to a stale supplier tariff.
	 *
	 * @param list<int> $productIds
	 *
	 * @return array<int,list<array<string,mixed>>>
	 */
	private function tariffCandidates(array $productIds): array
	{
		global $conf;
		if (!$productIds) {
			return array();
		}
		$this->loadCurrencies();
		$ids = implode(',', array_unique(array_map('intval', $productIds)));
		$entities = $this->db->sanitize(getEntity('productsupplierprice'));
		$rows = $this->rows('SELECT rowid AS history, fk_product AS product, fk_supplier_price AS id, date_effective AS date,
			date_capture AS observed, snapshot_unit_ht AS price, price_status AS status, currency, deleted, 2 AS priority,
			EXISTS(SELECT 1 FROM '.MAIN_DB_PREFIX.'product_fournisseur_price live WHERE live.rowid = h.fk_supplier_price AND live.entity = h.entity) AS source_exists,
			EXISTS(SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbap_tariff_history del WHERE del.fk_supplier_price = h.fk_supplier_price AND del.entity = h.entity AND del.deleted = 1) AS deletion_recorded
			FROM '.MAIN_DB_PREFIX.'lmdbap_tariff_history h WHERE fk_soc IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity IN ('.$this->db->sanitize(getEntity('societe')).')) AND fk_product IN ('.$ids.') AND entity IN ('.$entities.')');
		foreach ($rows as $historyRow) {
			if (!$historyRow->source_exists && !$historyRow->deletion_recorded) {
				$historyRow->price = null;
				$historyRow->status = 'BudgetCostMissingPrice';
			}
		}
		$current = $this->rows('SELECT rowid, entity, fk_product, datec, unitprice, quantity, remise_percent, remise, fk_supplier_price_expression
			FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE fk_soc IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity IN ('.$this->db->sanitize(getEntity('societe')).')) AND fk_product IN ('.$ids.') AND entity IN ('.$entities.')');
		foreach ($current as $price) {
			$net = $this->netTariff($price);
			$rows[] = (object) array('history' => 0, 'product' => $price->fk_product, 'id' => $price->rowid, 'date' => $price->datec,
				'observed' => $price->datec, 'price' => $net, 'status' => $net === null ? 'BudgetCostMissingPrice' : 'known',
				'currency' => $this->currencies[(int) $price->entity] ?? '', 'deleted' => 0, 'priority' => 1);
		}
		$history = $this->rows('SELECT 0 AS history, p.fk_product AS product, p.rowid AS id, h.datec AS date, h.datec AS observed,
			NULL AS price, 0 AS deleted, 0 AS priority FROM '.MAIN_DB_PREFIX.'product_fournisseur_price_log h
			INNER JOIN '.MAIN_DB_PREFIX.'product_fournisseur_price p ON p.rowid = h.fk_product_fournisseur
			WHERE p.fk_soc IN (SELECT rowid FROM '.MAIN_DB_PREFIX.'societe WHERE entity IN ('.$this->db->sanitize(getEntity('societe')).')) AND p.fk_product IN ('.$ids.') AND p.entity IN ('.$entities.')');
		$result = array();
		foreach (array_merge($rows, $history) as $row) {
			$result[(int) $row->product][] = (array) $row;
		}
		return $result;
	}

	/**
	 * @param list<array<string,mixed>> $candidates
	 * @return Tariff */
	public static function selectTariff(array $candidates, string $cutoff, string $knownAt): array
	{
		$byPrice = array();
		foreach ($candidates as $candidate) {
			if (empty($candidate['date']) || $candidate['date'] > $cutoff || $candidate['observed'] > $knownAt) {
				continue;
			}
			$id = (int) $candidate['id'];
			$rank = array($candidate['date'], (int) $candidate['priority'], (int) $candidate['history']);
			if (!isset($byPrice[$id]) || $rank > $byPrice[$id]['rank']) {
				$byPrice[$id] = array('rank' => $rank, 'value' => $candidate);
			}
		}
		$latest = null;
		foreach ($byPrice as $entry) {
			$candidate = $entry['value'];
			if (!empty($candidate['deleted'])) {
				continue;
			}
			if ($latest === null || array($candidate['date'], (int) $candidate['id']) > array($latest['date'], (int) $latest['id'])) {
				$latest = $candidate;
			}
		}
		return array('price' => $latest !== null && $latest['price'] !== null ? (float) $latest['price'] : null,
			'date' => (string) ($latest['date'] ?? ''), 'id' => (int) ($latest['id'] ?? 0), 'history' => (int) ($latest['history'] ?? 0),
			'status' => (string) ($latest['status'] ?? 'BudgetCostHistoricalDiscountUnknown'), 'currency' => (string) ($latest['currency'] ?? ''));
	}

	/**
	 * @param stdClass $row
	 * @return float|null */
	private function netTariff($row): ?float
	{
		if ((float) $row->quantity <= 0 || (int) $row->fk_supplier_price_expression > 0) {
			return null;
		}
		// Native supplier-card unit tariff formula. A commercial line total would
		// prematurely apply MT precision here and lose unit-price decimals.
		return (float) price2num((float) $row->unitprice * (1 - (float) $row->remise_percent / 100) - (float) $row->remise, 'MU');
	}

	/** Capture the persisted native supplier tariff inside its own transaction.
	 *
	 * @param int $priceId
	 * @param User $user
	 * @param bool $deleted
	 * @return void
	 */
	public function captureTariff(int $priceId, $user, bool $deleted = false): void
	{
		global $conf;
		foreach ($this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'product_fournisseur_price WHERE rowid = '.$priceId.' AND entity = '.(int) $conf->entity.' FOR UPDATE') as $row) {
			$now = $this->db->idate(dol_now());
			$date = $deleted ? $now : (string) $row->datec;
			$net = $this->netTariff($row);
			$status = $net === null ? 'BudgetCostMissingPrice' : 'known';
			$previous = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbap_tariff_history WHERE entity = '.(int) $row->entity.' AND fk_supplier_price = '.$priceId.' ORDER BY rowid DESC LIMIT 1');
			if ($previous && $previous[0]->date_effective === $date && (int) $previous[0]->deleted === (int) $deleted
				&& $previous[0]->currency === $conf->currency && (float) $previous[0]->snapshot_min_qty === (float) $row->quantity
				&& ($previous[0]->snapshot_unit_ht === null ? null : (float) $previous[0]->snapshot_unit_ht) === $net) {
				return;
			}
			// Chaining distinguishes A -> B -> A changes within one second, while
			// replaying the latest identical native state is a no-op.
			$hash = hash('sha256', implode('|', array((int) $row->entity, $priceId, $date, $net === null ? 'null' : $net,
				(float) $row->quantity, (int) $deleted, $previous ? (int) $previous[0]->rowid : 0)));
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbap_tariff_history (entity,fk_product,fk_supplier_price,fk_soc,date_effective,date_capture,snapshot_unit_ht,snapshot_min_qty,currency,price_status,deleted,fingerprint,fk_user_author) VALUES (';
			$sql .= (int) $row->entity.','.(int) $row->fk_product.','.$priceId.','.(int) $row->fk_soc.", '".$this->db->escape($date)."', '".$now."',";
			$sql .= ($net === null ? 'NULL' : (string) $net).','.(float) $row->quantity.", '".$this->db->escape($conf->currency)."', '".$status."',".(int) $deleted.", '".$hash."',".(int) $user->id.') ON DUPLICATE KEY UPDATE fingerprint = fingerprint';
			if (!$this->db->query($sql)) {
				throw new RuntimeException('BudgetCostCaptureFailed');
			}
		}
	}

	/**
	 * @param int $shipmentId
	 * @return void */
	public function retireShipment(int $shipmentId): void
	{
		global $conf;
		if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbap_shipment_cost SET active = 0 WHERE fk_expedition = '.$shipmentId.' AND entity = '.(int) $conf->entity)) {
			throw new RuntimeException('BudgetCostCaptureFailed');
		}
	}

	/** Called once per successful native validation, in its existing transaction.
	 *
	 * @param int $shipmentId
	 * @param User $user
	 * @return void
	 */
	public function captureShipment(int $shipmentId, $user): void
	{
		global $conf;
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$shipments = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'expedition WHERE rowid = '.$shipmentId.' AND entity = '.(int) $conf->entity.' FOR UPDATE');
		if (!$shipments || !$shipments[0]->date_valid) {
			throw new RuntimeException('BudgetCostCaptureFailed');
		}
		$shipment = $shipments[0];
		$shippingDate = $shipment->date_expedition ?: $shipment->date_valid;
		$rows = $this->rows('SELECT ed.rowid, ed.qty, COALESCE(NULLIF(ed.fk_product,0),l.fk_product) AS product_id,
			COALESCE(NULLIF(l.fk_unit,0),p.fk_unit,0) AS unit_id, p.fk_unit AS product_unit FROM '.MAIN_DB_PREFIX.'expeditiondet ed
			LEFT JOIN '.MAIN_DB_PREFIX."commandedet l ON l.rowid = ed.fk_elementdet AND ed.element_type = 'commande'
			INNER JOIN ".MAIN_DB_PREFIX.'product p ON p.rowid = COALESCE(NULLIF(ed.fk_product,0),l.fk_product)
			AND p.entity IN ('.$this->db->sanitize(getEntity('product')).') WHERE ed.fk_expedition = '.$shipmentId);
		$tariffs = $this->tariffCandidates(array_map(static function ($row): int { return (int) $row->product_id; }, $rows));
		$pmps = array();
		foreach ($rows as $row) {
			$previous = $this->rows('SELECT * FROM '.MAIN_DB_PREFIX.'lmdbap_shipment_cost WHERE entity = '.(int) $shipment->entity.' AND fk_expeditiondet = '.(int) $row->rowid.' ORDER BY revision DESC LIMIT 1');
			if ($previous && (int) $previous[0]->active === 1 && $previous[0]->date_validation === $shipment->date_valid) {
				continue;
			}
			if (!array_key_exists((int) $row->product_id, $pmps)) {
				$product = new Product($this->db);
				$fetched = $product->fetch((int) $row->product_id);
				$pmps[(int) $row->product_id] = $fetched > 0 && isModEnabled('stock') && isset($product->pmp) ? (float) price2num($product->pmp, 'MU') : null;
			}
			$pmp = $pmps[(int) $row->product_id];
			$tariff = self::selectTariff($tariffs[(int) $row->product_id] ?? array(), min((string) $shippingDate, (string) $shipment->date_valid), (string) $shipment->date_valid);
			$unitMismatch = (int) $row->unit_id > 0 && (int) $row->product_unit > 0 && (int) $row->unit_id !== (int) $row->product_unit;
			if ($unitMismatch) { $pmp = null; $tariff['price'] = null; $tariff['status'] = 'BudgetCostIncompatibleUnits'; }
			$revision = $previous ? (int) $previous[0]->revision + 1 : 1;
			if (!$this->db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbap_shipment_cost SET active = 0 WHERE entity = '.(int) $shipment->entity.' AND fk_expeditiondet = '.(int) $row->rowid)) {
				throw new RuntimeException('BudgetCostCaptureFailed');
			}
			$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbap_shipment_cost (entity,fk_expedition,fk_expeditiondet,fk_product,fk_unit,revision,active,snapshot_qty,date_validation,date_shipping,date_capture,snapshot_pmp,snapshot_tariff,tariff_date,fk_supplier_price,fk_tariff_history,currency,tariff_currency,pmp_status,tariff_status,fk_user_author) VALUES (';
			$sql .= (int) $shipment->entity.','.$shipmentId.','.(int) $row->rowid.','.(int) $row->product_id.','.(int) $row->unit_id.','.$revision.',1,'.(float) $row->qty;
			$sql .= ",'".$this->db->escape($shipment->date_valid)."','".$this->db->escape($shippingDate)."','".$this->db->idate(dol_now())."',".($pmp === null ? 'NULL' : (string) $pmp).',';
			$sql .= ($tariff['price'] === null ? 'NULL' : (string) $tariff['price']).','.($tariff['date'] === '' ? 'NULL' : "'".$this->db->escape($tariff['date'])."'").','.$tariff['id'].','.$tariff['history'];
			$sql .= ",'".$this->db->escape($conf->currency)."','".$this->db->escape($tariff['currency'])."','".($pmp === null ? 'BudgetCostMissingPrice' : 'known')."','".$this->db->escape($tariff['status'])."',".(int) $user->id.')';
			if (!$this->db->query($sql)) {
				throw new RuntimeException('BudgetCostCaptureFailed');
			}
		}
	}
}
