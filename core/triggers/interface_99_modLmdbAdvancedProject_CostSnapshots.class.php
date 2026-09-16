<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once __DIR__.'/../../class/lmdbadvancedprojectproductcost.class.php';

/** Capture analytical evidence inside the native mutation transaction. */
class InterfaceCostSnapshots extends DolibarrTriggers
{
	/**
	 * @param DoliDB $db */
	public function __construct($db)
	{
		$this->db = $db;
		$this->name = preg_replace('/^Interface/i', '', get_class($this));
		$this->family = 'lmdbadvancedproject';
		$this->description = 'Historical net tariffs and shipment analytical costs';
		$this->version = 'dolibarr';
		$this->picto = 'project';
	}

	/**
	 * Native SHIPPING_* / BUYPRICE_* events are consumed, never re-emitted.
	 *
	 * @param string $action
	 * @param CommonObject $object
	 * @param User $user
	 *
	 * @param Translate $langs
	 * @param Conf $conf
	 * @return int
	 */
	public function runTrigger($action, $object, $user, $langs, $conf)
	{
		if (!isModEnabled('lmdbadvancedproject')) {
			return 0;
		}
		$prefix = version_compare(DOL_VERSION, '23.0.0', '>=') ? 'PRODUCT_BUYPRICE_' : 'SUPPLIER_PRODUCT_BUYPRICE_';
		$events = array('SHIPPING_VALIDATE', 'SHIPPING_CANCEL', 'SHIPPING_DELETE', 'SHIPMENT_UNVALIDATE', $prefix.'CREATE', $prefix.'MODIFY', $prefix.'DELETE');
		if (!in_array($action, $events, true)) {
			return 0;
		}
		// Retire existing evidence even while the optional calculation is switched
		// off; otherwise a cancelled and revalidated line could reuse an old PMP.
		$retiring = in_array($action, array('SHIPPING_CANCEL', 'SHIPPING_DELETE', 'SHIPMENT_UNVALIDATE'), true);
		if (!LmdbAdvancedProjectCompatibility::shipmentCostAvailable() || (!$retiring && !LmdbAdvancedProjectCompatibility::isShipmentCostEnabled())) {
			return 0;
		}
		try {
			$service = new LmdbAdvancedProjectProductCost($this->db);
			if ($retiring && $object instanceof Expedition) {
				$service->retireShipment((int) $object->id);
			} elseif ($action === 'SHIPPING_VALIDATE' && $object instanceof Expedition) {
				$service->captureShipment((int) $object->id, $user);
			} elseif ($object instanceof ProductFournisseur && !empty($object->product_fourn_price_id)) {
				$service->captureTariff((int) $object->product_fourn_price_id, $user, $action === $prefix.'DELETE');
			}
		} catch (RuntimeException $exception) {
			$langs->load('lmdbadvancedproject@lmdbadvancedproject');
			$this->error = $langs->trans('BudgetCostCaptureFailed');
			dol_syslog(__METHOD__.': '.$exception->getMessage(), LOG_ERR);
			return -1;
		}
		return 0;
	}
}
