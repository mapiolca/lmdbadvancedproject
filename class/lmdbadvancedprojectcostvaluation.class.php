<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * Optional price evidence and explicit project/product analytical instructions.
 * No mutation of native products, documents or external price providers.
 *
 * @phpstan-type CostChoice array{price:float,currency:string,source:string,id:int,history:int,date:string,label:string}
 */
class LmdbAdvancedProjectCostValuation
{
	/** @var DoliDB */
	private $db;
	/** @var array<int,Product|null> Request-local authorized product cache. */
	private $products = array();
	/** @var array<string,array<string,CostChoice>> */
	private $choiceCache = array();
	/** @var array<string,bool> */
	private $availability = array();

	/** @param DoliDB $db */
	public function __construct($db) { $this->db = $db; }

	/** Authorize and load price evidence, not a proxy for functional permissions.
	 * @return Product|null
	 */
	public function product(int $id)
	{
		global $user;
		if (array_key_exists($id, $this->products)) { return $this->products[$id]; }
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
		$product = new Product($this->db);
		$this->products[$id] = null;
		if ($product->fetch($id) <= 0 || !in_array((int) $product->entity, array_map('intval', explode(',', getEntity('product'))), true)) { return null; }
		$permission = (int) $product->type === Product::TYPE_SERVICE ? 'service' : 'product';
		if (!$user->hasRight($permission, 'read')
			|| (getDolGlobalInt('MAIN_USE_ADVANCED_PERMS') && !$user->hasRight($permission, $permission.'_advance', 'read_prices'))
			|| !checkUserAccessToObject($user, array($permission), $product, 'product&product')) { return null; }
		$this->products[$id] = $product;
		return $product;
	}

	/** Strict normalization, including a deliberately entered zero. */
	public static function amount(string $input, bool $userInput = false): ?float
	{
		if (trim($input) === '') { return null; }
		// A rounded price2num('invalid', 'MU') returns zero. Validate the raw
		// amount first; SQL amounts use universal notation, user input its locale.
		if ($userInput && !preg_match('/^\+?[0-9][0-9.,\s\x{00a0}\x{202f}]*$/u', trim($input))) { return null; }
		$value = $userInput ? price2num($input, '', 2) : $input;
		if (!is_numeric($value) || !is_finite((float) $value) || (float) $value < 0) { return null; }
		return (float) price2num($value, 'MU', 1);
	}

	/** Optional modules must be enabled in the consultation and source entities. */
	private function providerAvailable(string $module, int $entity): bool
	{
		global $conf;
		$key = $module.':'.$entity;
		if (isset($this->availability[$key])) { return $this->availability[$key]; }
		$available = LmdbAdvancedProjectCompatibility::costProviderAvailable($module);
		if ($available && $entity !== (int) $conf->entity) {
			$settings = array('MAIN_MODULE_'.strtoupper($module) => 0, 'DYNAMICPRICES_COST_ENABLE' => 1);
			foreach ($this->rows("SELECT name,value FROM ".MAIN_DB_PREFIX."const WHERE entity IN (0,".$entity.") AND name IN ('MAIN_MODULE_".strtoupper($module)."','DYNAMICPRICES_COST_ENABLE') ORDER BY entity") as $row) {
				$settings[(string) $row->name] = (int) $row->value;
			}
			$available = !empty($settings['MAIN_MODULE_'.strtoupper($module)]) && ($module !== 'dynamicsprices' || !empty($settings['DYNAMICPRICES_COST_ENABLE']));
		}
		return $this->availability[$key] = $available;
	}

	/** Read a valid DynamicPrices amount without invoking its calculator.
	 * @return CostChoice|null
	 */
	public function dynamicPrice(int $productId, int $entity): ?array
	{
		global $user, $conf;
		if (!$this->providerAvailable('dynamicsprices', $entity) || !$user->hasRight('dynamicsprices', 'cost', 'read') || !$this->product($productId)
			|| $this->currency($entity) !== $conf->currency) { return null; }
		$service = new DynamicPricesCostService($this->db);
		$record = $service->getDynamicCostRecord($productId, $entity);
		if (!is_object($record) || (int) ($record->entity ?? 0) !== $entity || (int) ($record->fk_product ?? 0) !== $productId
			|| (int) ($record->status ?? 0) <= 0 || (int) ($record->calculation_status ?? 0) <= 0
			|| $record->price_base_type !== 'HT' || $record->currency_code !== $conf->currency || !$record->date_calculation) { return null; }
		$value = self::amount((string) $record->dynamic_cost_price);
		return $value !== null && $value > 0 ? $this->choice($value, 'dynamicprices', (int) $record->rowid, 0, (string) $record->date_calculation) : null;
	}

	/**
	 * Keep only the latest historical state; a newer missing price blocks older prices.
	 * Changes of targeting or threshold are not reconstructed using today's rules.
	 * @param list<stdClass> $history
	 * @param stdClass $current Targeting metadata of the authorized, natively selected price row
	 * @return stdClass|null
	 */
	public static function historicalRow(array $history, $current, string $cutoff): ?stdClass
	{
		$selected = null;
		foreach ($history as $row) {
			if (!isset($row->datec, $row->rowid) || !$row->datec) { return null; }
			if ((string) $row->datec <= $cutoff && ($selected === null || array($row->datec, (int) $row->rowid) > array($selected->datec, (int) $selected->rowid))) { $selected = $row; }
		}
		if ($selected === null) { return null; }
		foreach (array('fk_product', 'entity', 'from_qty', 'fk_soc', 'fk_cat', 'fk_cat_propal', 'fk_cat_order', 'fk_cat_invoice', 'fk_cat_contract', 'cost_price') as $column) {
			if (!property_exists($selected, $column) || ($column !== 'cost_price' && !property_exists($current, $column))) { return null; }
		}
		if ((int) $selected->fk_product !== (int) $current->fk_product
			|| (int) $selected->entity !== (int) $current->entity || (float) $selected->from_qty !== (float) $current->from_qty) { return null; }
		foreach (array('fk_soc', 'fk_cat', 'fk_cat_propal', 'fk_cat_order', 'fk_cat_invoice', 'fk_cat_contract') as $column) {
			if ((int) $selected->$column !== (int) $current->$column) { return null; }
		}
		$source = isset($selected->cost_price_source) ? (string) $selected->cost_price_source : (!empty($selected->use_product_cost_price) ? 'product' : 'custom');
		$value = self::amount((string) $selected->cost_price);
		return $source === 'custom' && $value !== null && $value > 0 ? $selected : null;
	}

	/** PriceList owns selection and category priority; this module only reads evidence.
	 * @return array<string,CostChoice>
	 */
	public function priceListChoices(int $productId, int $orderId, int $lineId, string $cutoff = ''): array
	{
		global $user, $conf;
		$key = implode(':', array($productId, $orderId, $lineId, $cutoff));
		if (isset($this->choiceCache[$key])) { return $this->choiceCache[$key]; }
		$this->choiceCache[$key] = array();
		if ($orderId <= 0 || $lineId <= 0 || !isModEnabled('pricelist') || !$user->hasRight('commande', 'lire')) { return array(); }
		$product = $this->product($productId);
		if ($product === null) { return array(); }
		require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
		$order = new Commande($this->db);
		if ($order->fetch($orderId) <= 0 || !in_array((int) $order->entity, array_map('intval', explode(',', getEntity('commande'))), true)
			|| !checkUserAccessToObject($user, array('commande'), $order, 'commande', '', 'fk_soc')
			|| $order->fetch_thirdparty() <= 0 || !is_object($order->thirdparty)
			|| !checkUserAccessToObject($user, array('societe'), $order->thirdparty, 'societe', '', '')
			|| !$this->providerAvailable('pricelist', (int) $order->entity) || $this->currency((int) $order->entity) !== $conf->currency) { return array(); }
		$quantity = null;
		foreach ($order->lines as $line) {
			if ((int) $line->id === $lineId && (int) $line->fk_product === $productId) { $quantity = (float) $line->qty; break; }
		}
		if ($quantity === null || $quantity <= 0) { return array(); }
		$list = new PriceList($this->db);
		$row = $list->get_price($productId, $order->thirdparty, $quantity, $order);
		if (!is_object($row) || empty($row->rowid)) { return array(); }
		// get_price() returns no targeting/entity metadata. fetch() is limited to
		// the consultation entity, so read only this missing metadata for the
		// already selected row; do not reimplement PriceList's selection rules.
		$entities = array_unique(array((int) $order->entity, (int) $product->entity));
		$metadata = $this->rows('SELECT rowid,entity,fk_product,from_qty,fk_soc,fk_cat,fk_cat_propal,fk_cat_order,fk_cat_invoice,fk_cat_contract FROM '.MAIN_DB_PREFIX.'pricelist WHERE rowid='.(int) $row->rowid.' AND fk_product='.$productId.' AND entity IN ('.implode(',', $entities).')');
		if (!$metadata || $this->currency((int) $metadata[0]->entity) !== $conf->currency) { return array(); }
		$current = $metadata[0];
		$list->id = (int) $current->rowid;
		$list->entity = (int) $current->entity;
		$history = $list->getHistory();
		if (!is_array($history)) { return array(); }
		$choices = array();
		if ($cutoff !== '') {
			$historic = self::historicalRow($history, $current, $cutoff);
			if ($historic !== null) {
				$choices['pricelist_history:'.$historic->rowid] = $this->choice((float) price2num($historic->cost_price, 'MU'), 'pricelist_history', (int) $list->id, (int) $historic->rowid, (string) $historic->datec);
			}
		} else {
			$value = $list->getEffectiveCostPriceForRow($row);
			if ($value !== null && is_finite((float) $value) && $value > 0) {
				$choices['pricelist:'.$orderId.':'.$lineId] = $this->choice((float) price2num($value, 'MU'), 'pricelist', (int) $list->id, 0, '', $order->ref.' / '.price($current->from_qty));
			}
			foreach ($history as $historic) {
				// The modal may deliberately choose an older stored amount, but never
				// a derived amount whose historical value was not actually persisted.
				$valid = self::historicalRow(array($historic), $current, (string) $historic->datec);
				if ($valid !== null) {
					$choices['pricelist_history:'.$historic->rowid] = $this->choice((float) price2num($historic->cost_price, 'MU'), 'pricelist_history', (int) $list->id, (int) $historic->rowid, (string) $historic->datec, $order->ref.' / '.price($historic->from_qty));
				}
			}
		}
		return $this->choiceCache[$key] = $choices;
	}

	/** @return CostChoice|null */
	public function automatic(int $productId, int $entity, int $orderId, int $lineId, string $cutoff): ?array
	{
		$choices = $this->priceListChoices($productId, $orderId, $lineId, $cutoff);
		if ($choices) { return reset($choices); }
		$choice = $this->dynamicPrice($productId, $entity);
		return $choice !== null && $choice['date'] <= $cutoff ? $choice : null;
	}

	/** @return CostChoice */
	private function choice(float $price, string $source, int $id = 0, int $history = 0, string $date = '', string $detail = ''): array
	{
		global $conf, $langs;
		$label = $langs->trans('BudgetCostSource_'.$source).' — '.price($price).' '.$conf->currency;
		if ($date !== '') { $label .= ' — '.dol_print_date($this->db->jdate($date), 'dayhour'); }
		if ($detail !== '') { $label .= ' — '.$detail; }
		return array('price' => $price, 'currency' => $conf->currency, 'source' => $source, 'id' => $id, 'history' => $history, 'date' => $date, 'label' => $label);
	}

	/** @param array<string,mixed> $row Reconciled product row
	 * @return array<string,CostChoice>
	 */
	public function choices(array $row): array
	{
		global $conf, $user;
		$product = $this->product((int) $row['product']);
		if ($product === null) { throw new RuntimeException('BudgetCostAccessDenied'); }
		$choices = array();
		// Product::fetch owns PMP entity semantics. Never pretend a PMP loaded
		// in the consultation entity belongs to a different source entity.
		if ($this->currency((int) $product->entity) === $conf->currency) {
			$value = self::amount((string) $product->cost_price);
			if ($value !== null) { $choices['native'] = $this->choice($value, 'native', (int) $product->id); }
		}
		if (isModEnabled('stock')) {
			$value = self::amount((string) $product->pmp);
			if ($value !== null) { $choices['pmp'] = $this->choice($value, 'pmp', (int) $product->id); }
		}
		$dynamic = $this->dynamicPrice((int) $product->id, (int) $conf->entity);
		if ($dynamic !== null) { $choices['dynamicprices'] = $dynamic; }
		foreach ($row['lines'] as $line) {
			if ($line['kind'] === 'shipment') {
				$choices += $this->priceListChoices((int) $product->id, (int) ($line['order_id'] ?? 0), (int) ($line['order_line_id'] ?? 0));
			}
		}
		if (isModEnabled('fournisseur') && $user->hasRight('fournisseur', 'lire') && $user->hasRight('societe', 'lire')) {
			require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.product.class.php';
			require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
			$supplierProduct = new ProductFournisseur($this->db);
			$prices = $supplierProduct->list_product_fournisseur_price((int) $product->id);
			if (!is_array($prices)) { throw new RuntimeException('BudgetCostReadFailed'); }
			foreach ($prices as $price) {
				$supplier = new Societe($this->db);
				if ($supplier->fetch((int) $price->fourn_id) <= 0
					|| !in_array((int) $supplier->entity, array_map('intval', explode(',', getEntity('societe'))), true)
					|| !checkUserAccessToObject($user, array('societe'), $supplier, 'societe', '', '')
					|| $this->currency((int) $price->product_fourn_entity) !== $conf->currency || (float) $price->fourn_qty <= 0) { continue; }
				// An expression is never silently replaced by a stored, stale amount.
				if (!empty($price->fk_supplier_price_expression)) { continue; }
				$value = self::amount((string) ((float) $price->fourn_unitprice * (1 - (float) $price->fourn_remise_percent / 100) - (float) $price->fourn_remise));
				if ($value !== null) {
					$choices['supplier:'.$price->product_fourn_price_id] = $this->choice($value, 'supplier', (int) $price->product_fourn_price_id, 0, '', $supplier->name.' / '.$price->ref_supplier.' / '.price($price->fourn_qty));
				}
			}
		}
		return $choices;
	}

	/** Load a writable native project with independent module and object permissions.
	 * @return Project
	 */
	public function project(int $id)
	{
		global $user;
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
		$project = new Project($this->db);
		if (!LmdbAdvancedProjectCompatibility::costValuationAvailable()
			|| !$user->hasRight('lmdbadvancedproject', 'budgetreport', 'read') || !$user->hasRight('projet', 'lire')
			|| (!$user->hasRight('projet', 'creer') && !$user->hasRight('projet', 'all', 'creer'))
			|| $project->fetch($id) <= 0 || !in_array((int) $project->entity, array_map('intval', explode(',', getEntity('project'))), true)
			|| $project->restrictedProjectArea($user, 'read') <= 0 || $project->restrictedProjectArea($user, 'write') <= 0
			|| !checkUserAccessToObject($user, array('projet'), $project, 'projet&project', '', 'fk_soc')) { throw new RuntimeException('BudgetCostAccessDenied'); }
		return $project;
	}

	/** Detect changes to the candidate and target set between opening and submission.
	 * @param CostChoice $choice
	 */
	public static function fingerprint(array $choice): string
	{
		unset($choice['label']);
		return hash('sha256', (string) json_encode($choice));
	}

	/** @return string */
	public function currency(int $entity): string
	{
		global $conf;
		if ($entity === (int) $conf->entity) { return (string) $conf->currency; }
		$rows = $this->rows("SELECT value FROM ".MAIN_DB_PREFIX."const WHERE name='MAIN_MONNAIE' AND entity IN (0,".$entity.") ORDER BY entity DESC");
		return $rows ? (string) $rows[0]->value : '';
	}

	/** @return list<stdClass> */
	private function rows(string $sql): array
	{
		$result = $this->db->query($sql);
		if (!$result) { throw new RuntimeException('BudgetCostReadFailed'); }
		$rows = array();
		while (is_object($row = $this->db->fetch_object($result))) { $rows[] = $row; }
		$this->db->free($result);
		return $rows;
	}

	/** Persist one immutable instruction per existing eligible project/product pair.
	 * The page supplies a server-session quote, never trusted browser price metadata.
	 * @param array{project:int,product:int,unit:int,choices:array<string,CostChoice>,targets:array<int,string>} $quote
	 * @return int Number of projects updated (zero for an identical replay)
	 */
	public function save(array $quote, string $source, string $input, bool $all, string $requestKey): int
	{
		global $conf, $user;
		$this->products = $this->choiceCache = $this->availability = array();
		$originProject = $this->project($quote['project']);
		if (!$this->product($quote['product'])) { throw new RuntimeException('BudgetCostAccessDenied'); }
		$price = $source === 'free' ? self::amount($input, true) : null;
		if ($source === 'free' && $price === null) { throw new RuntimeException('BudgetCostInvalidPrice'); }
		$this->db->begin();
		try {
			$ids = $all ? array_keys($quote['targets']) : array($quote['project']);
			sort($ids, SORT_NUMERIC);
			if (!$ids) { throw new RuntimeException('BudgetCostConflict'); }
			// Consistent parent locking serializes this operation across sessions.
			$this->rows('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE entity IN ('.$this->db->sanitize(getEntity('project')).') AND rowid IN ('.implode(',', array_map('intval', $ids)).') ORDER BY rowid FOR UPDATE');
			$service = new LmdbAdvancedProjectProductCost($this->db);
			$report = $service->load($ids, array(), array('product_id' => $quote['product']));
			$origin = $report['products'][$quote['project'].':'.$quote['product']] ?? null;
			$replayed = $this->rows('SELECT request_key FROM '.MAIN_DB_PREFIX.'lmdbap_cost_instruction WHERE entity='.(int) $originProject->entity.' AND fk_project='.(int) $quote['project'].' AND fk_product='.(int) $quote['product']);
			if ($replayed && hash_equals((string) $replayed[0]->request_key, $requestKey)) { $this->db->commit(); return 0; }
			if ($origin === null || empty($origin['can_value'])) { throw new RuntimeException('BudgetCostConflict'); }
			if ($source === 'free') {
				$choice = $this->choice((float) $price, 'free');
			} else {
				$choices = $this->choices($origin);
				if (!isset($quote['choices'][$source], $choices[$source]) || !hash_equals(self::fingerprint($quote['choices'][$source]), self::fingerprint($choices[$source]))) { throw new RuntimeException('BudgetCostConflict'); }
				$choice = $choices[$source];
			}
			$count = 0;
			foreach ($ids as $id) {
				$project = $this->project((int) $id);
				$row = $report['products'][$id.':'.$quote['product']] ?? null;
				if ($row === null || empty($row['can_value']) || (int) $row['valuation_unit'] !== $quote['unit']
					|| $this->currency((int) $project->entity) !== $choice['currency']
					|| !isset($quote['targets'][$id]) || !hash_equals($quote['targets'][$id], self::targetFingerprint($row))) { throw new RuntimeException('BudgetCostConflict'); }
				$columns = 'entity,fk_project,fk_product,fk_unit,snapshot_unit_ht,currency,source_code,fk_source,fk_source_history,source_date,date_creation,fk_user_author,request_key';
				$sql = 'INSERT INTO '.MAIN_DB_PREFIX.'lmdbap_cost_instruction ('.$columns.') VALUES ('.(int) $project->entity.','.(int) $id.','.$quote['product'].','.$quote['unit'].','.(float) $choice['price'];
				$sql .= ",'".$this->db->escape($choice['currency'])."','".$this->db->escape($choice['source'])."',".$choice['id'].','.$choice['history'];
				$sql .= ','.($choice['date'] === '' ? 'NULL' : "'".$this->db->escape($choice['date'])."'").",'".$this->db->idate(dol_now())."',".(int) $user->id.",'".$this->db->escape($requestKey)."')";
				if (!$this->db->query($sql)) { throw new RuntimeException('BudgetCostConflict'); }
				$count++;
			}
			$this->db->commit();
			return $count;
		} catch (Throwable $exception) {
			$this->db->rollback();
			throw $exception;
		}
	}

	/** @param array<string,mixed> $row */
	public static function targetFingerprint(array $row): string
	{
		return hash('sha256', (string) json_encode(array($row['lines'], $row['events'], $row['valuation_unit'])));
	}

	/** Prepare an authorized, bounded-in-time target set for explicit confirmation.
	 * @return array{project:int,product:int,unit:int,choices:array<string,CostChoice>,targets:array<int,string>}
	 */
	public function prepare(int $projectId, int $productId): array
	{
		global $conf;
		$this->project($projectId);
		if (!$this->product($productId)) { throw new RuntimeException('BudgetCostAccessDenied'); }
		$authorized = lmdbadvancedproject_get_budget_report_authorized_project_ids();
		$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE entity IN ('.$this->db->sanitize(getEntity('project')).')';
		if (is_array($authorized)) { $sql .= ' AND rowid IN ('.($authorized ? implode(',', array_map('intval', $authorized)) : '0').')'; }
		$ids = array_map(static function ($row): int { return (int) $row->rowid; }, $this->rows($sql));
		$report = (new LmdbAdvancedProjectProductCost($this->db))->load($ids, array(), array('product_id' => $productId));
		$origin = $report['products'][$projectId.':'.$productId] ?? null;
		if ($origin === null || empty($origin['can_value'])) { throw new RuntimeException('BudgetCostConflict'); }
		$targets = array();
		foreach ($report['products'] as $row) {
			if (empty($row['can_value']) || (int) $row['valuation_unit'] !== (int) $origin['valuation_unit']) { continue; }
			try { $project = $this->project((int) $row['project']); } catch (RuntimeException $exception) { continue; }
			if ($this->currency((int) $project->entity) === $conf->currency) { $targets[(int) $project->id] = self::targetFingerprint($row); }
		}
		if (!isset($targets[$projectId])) { throw new RuntimeException('BudgetCostAccessDenied'); }
		return array('project' => $projectId, 'product' => $productId, 'unit' => (int) $origin['valuation_unit'], 'choices' => $this->choices($origin), 'targets' => $targets);
	}
}
