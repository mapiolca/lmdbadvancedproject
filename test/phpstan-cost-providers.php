<?php
// Declaration-only optional contracts; never loaded at runtime.
// Read from DynamicPrices 3.0.1 and PriceList 2.3.0 (2026-09-22).
class DynamicPricesCostService
{
	/** @param DoliDB $db */
	public function __construct($db) {}
	/** @return stdClass|null */
	public function getDynamicCostRecord(int $productId, int $entity = 0) {}
}
class PriceList extends CommonObject
{
	/** @var int */ public $product_id;
	/** @var int|null */ public $catid;
	/** @var int|null */ public $catid_propal;
	/** @var int|null */ public $catid_order;
	/** @var int|null */ public $catid_invoice;
	/** @var int|null */ public $catid_contract;
	/** @var float */ public $from_qty;
	/** @param DoliDB $db */ public function __construct($db) {}
	/** @return int */ public function fetch($id) {}
	/** @param Societe $soc @param CommonObject|null $sourceObject @return stdClass|int */
	public function get_price($productId, $soc, $qty, $sourceObject = null) {}
	/** @param stdClass|PriceList $row @return float|null */
	public function getEffectiveCostPriceForRow($row) {}
	/** @return list<stdClass>|null */ public function getHistory() {}
}
