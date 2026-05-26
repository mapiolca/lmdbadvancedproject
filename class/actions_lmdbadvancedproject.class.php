<?php
/* Copyright (C) 2026 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once dol_buildpath('/lmdbadvancedproject/class/lmdbadvancedprojectsupplierinvoicepart.class.php');
require_once dol_buildpath('/lmdbadvancedproject/class/lmdbadvancedprojectcustomerinvoicepart.class.php');

/**
 * Hooks for Advanced Project.
 */
class ActionsLmdbadvancedproject
{
	/** @var DoliDB Database handler */
	public $db;

	/** @var string Error */
	public $error = '';

	/** @var array<string> Errors */
	public $errors = array();

	/** @var array<string> Warnings */
	public $warnings = array();

	/** @var array<string,mixed> Hook results */
	public $results = array();

	/** @var string Hook rendered output */
	public $resprints = '';

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Inject the inline split button on invoice line tables.
	 *
	 * @param  array<string,mixed> $parameters Hook parameters
	 * @param  CommonObject        $object     Current object
	 * @param  string              $action     Current action
	 * @param  HookManager         $hookmanager Hook manager
	 * @return int
	 */
	public function addHtmlHeader($parameters, $object, $action, $hookmanager)
	{
		global $langs;

		$context = $this->getInvoiceContext($parameters);
		if ($context === '' || !$this->isFeatureEnabled($context) || !$this->hasWriteAccess($context)) {
			return 0;
		}

		$langs->load('lmdbadvancedproject@lmdbadvancedproject');
		$token = function_exists('newToken') ? newToken() : (empty($_SESSION['newtoken']) ? '' : $_SESSION['newtoken']);
		$title = $langs->trans('LMDBAdvancedProjectBreakdownByProject');
		$loading = $langs->trans('Loading');
		$ajaxEnabled = $this->isAjaxEnabled();

		$html = '
		<script>
		jQuery(function($) {
			var lmdbapToken = '.json_encode($token).';
			var lmdbapTitle = '.json_encode($title).';
			var lmdbapLoading = '.json_encode($loading).';
			var lmdbapAjaxEnabled = '.($ajaxEnabled ? 'true' : 'false').';

			function lmdbapSetQueryParam(url, key, value) {
				var hash = "";
				var hashIndex = url.indexOf("#");
				if (hashIndex !== -1) {
					hash = url.substring(hashIndex);
					url = url.substring(0, hashIndex);
				}
				var regexp = new RegExp("([?&])" + key + "=[^&]*");
				if (regexp.test(url)) {
					return url.replace(regexp, "$1" + key + "=" + encodeURIComponent(value)) + hash;
				}
				return url + (url.indexOf("?") === -1 ? "?" : "&") + key + "=" + encodeURIComponent(value) + hash;
			}

			function lmdbapBuildSplitUrl(lineId) {
				var url = window.location.pathname + window.location.search;
				url = lmdbapSetQueryParam(url, "action", "lmdbadvancedproject_edit_split");
				url = lmdbapSetQueryParam(url, "lineid", lineId);
				if (lmdbapToken) {
					url = lmdbapSetQueryParam(url, "token", lmdbapToken);
				}
				return url;
			}

			function lmdbapEnsureHeader($table) {
				if (!$table.length || $table.data("lmdbap-split-header") || $table.find("thead .lmdbap-line-split-title").length) {
					return;
				}
				var $headerRow = $table.find("thead tr").last();
				if (!$headerRow.length) {
					return;
				}
				var $titleCell = $("<th>", {"class": "center lmdbap-line-split-title"}).html("&nbsp;");
				var $editTitle = $headerRow.children(".linecoledit").first();
				if ($editTitle.length) {
					$editTitle.before($titleCell);
				} else {
					var $moveTitle = $headerRow.children(".linecolmove").first();
					if ($moveTitle.length && $moveTitle.attr("colspan")) {
						$moveTitle.attr("colspan", function(index, value) {
							var span = parseInt(value, 10);
							return isNaN(span) ? value : span + 1;
						});
					} else {
						$headerRow.append($titleCell);
					}
				}
				$table.data("lmdbap-split-header", true);
			}

			function lmdbapMakeButtonCell(splitUrl) {
				var $cell = $("<td>", {"class": "center lmdbap-line-split-cell"});
				$("<a>", {
					"class": "lmdbap-edit-split classfortooltip",
					"href": splitUrl,
					"title": lmdbapTitle
				}).append($("<span>", {"class": "fas fa-sitemap"})).appendTo($cell);
				return $cell;
			}

			function lmdbapInsertButtonCell($row, $cell) {
				var $editCell = $row.children("td.linecoledit").first();
				if ($editCell.length) {
					$editCell.before($cell);
					return;
				}

				var $lastCell = $row.children("td").last();
				if ($lastCell.length && $lastCell.is("[colspan]")) {
					$lastCell.before($cell);
					return;
				}

				$row.append($cell);
			}

			function lmdbapInjectSplitButtons() {
				$("tr[data-id]").each(function() {
					var $row = $(this);
					var lineId = $row.attr("data-id");
					if (!lineId || !/^[0-9]+$/.test(lineId) || $row.children(".lmdbap-line-split-cell").length) {
						return;
					}

					var $table = $row.closest("table");
					if (!$row.children(".linecoldescription,.linecolproduct,.linecolref").length) {
						return;
					}

					lmdbapEnsureHeader($table);
					lmdbapInsertButtonCell($row, lmdbapMakeButtonCell(lmdbapBuildSplitUrl(lineId)));
				});
			}

			lmdbapInjectSplitButtons();

			$(document).on("click", ".lmdbap-edit-split", function(event) {
				if (!lmdbapAjaxEnabled || !$.fn.dialog) {
					return true;
				}
				event.preventDefault();
				var $dialog = $("#dialogforpopup");
				if (!$dialog.length) {
					$dialog = $("<div id=\"dialogforpopup\"></div>").appendTo("body");
				}
				$dialog.html(lmdbapLoading + " ...");
				$dialog.load(this.href, function() {
					$dialog.dialog({
						closeOnEscape: true,
						resizable: true,
						width: "90em",
						modal: true,
						title: lmdbapTitle
					});
					$(document).trigger("lmdbap:split-dialog-loaded", [$dialog]);
				});
			});

			$(document).on("submit", "#lmdbap-split-form", function(event) {
				if (!lmdbapAjaxEnabled) {
					return true;
				}
				event.preventDefault();
				var $form = $(this);
				$.ajax({
					url: $form.attr("action"),
					type: "POST",
					data: $form.serialize(),
					success: function(result) {
						$("#dialogforpopup").html(result);
						$(document).trigger("lmdbap:split-dialog-loaded", [$("#dialogforpopup")]);
					}
				});
				return false;
			});
		});
		</script>';

		$this->resprints .= $html;

		return 0;
	}

	/**
	 * Add allocated invoice parts to the project overview referents.
	 *
	 * @param  array<string,mixed> $parameters Hook parameters
	 * @param  CommonObject        $object     Current object
	 * @param  string              $action     Current action
	 * @param  HookManager         $hookmanager Hook manager
	 * @return int
	 */
	public function completeListOfReferent($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		if (empty($object) || empty($object->element) || $object->element !== 'project') {
			return 0;
		}

		$langs->load('lmdbadvancedproject@lmdbadvancedproject');
		$results = array();

		if ($this->isFeatureEnabled('invoicesuppliercard') && $this->hasReadAccess('invoicesuppliercard')) {
			$results['lmdbadvancedproject_supplier_invoice_parts'] = array(
				'name' => $langs->trans('LMDBAdvancedProjectSupplierInvoiceParts'),
				'title' => $langs->trans('LMDBAdvancedProjectSupplierInvoicePartsList'),
				'class' => 'LmdbAdvancedProjectSupplierInvoicePart',
				'table' => 'lmdbadvancedproject_supplier_invoice_parts',
				'project_field' => 'fk_projet',
				'datefieldname' => 'date',
				'margin' => 'minus',
				'disableamount' => 0,
				'test' => 1,
			);
		}

		if ($this->isFeatureEnabled('invoicecard') && $this->hasReadAccess('invoicecard')) {
			$results['lmdbadvancedproject_customer_invoice_parts'] = array(
				'name' => $langs->trans('LMDBAdvancedProjectCustomerInvoiceParts'),
				'title' => $langs->trans('LMDBAdvancedProjectCustomerInvoicePartsList'),
				'class' => 'LmdbAdvancedProjectCustomerInvoicePart',
				'table' => 'lmdbadvancedproject_customer_invoice_parts',
				'project_field' => 'fk_projet',
				'datefieldname' => 'date',
				'margin' => 'add',
				'disableamount' => 0,
				'test' => 1,
			);
		}

		if (empty($results)) {
			return 0;
		}

		$this->results = $results;

		return 1;
	}

	/**
	 * Handle AJAX modal rendering and save actions.
	 *
	 * @param  array<string,mixed> $parameters Hook parameters
	 * @param  CommonObject        $object     Current object
	 * @param  string              $action     Current action
	 * @param  HookManager         $hookmanager Hook manager
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		$context = $this->getInvoiceContext($parameters);
		if ($context === '' || !in_array($action, array('lmdbadvancedproject_edit_split', 'lmdbadvancedproject_update_split'), true)) {
			return 0;
		}

		$langs->load('lmdbadvancedproject@lmdbadvancedproject');

		if (!$this->isFeatureEnabled($context) || !$this->hasWriteAccess($context)) {
			accessforbidden();
		}

		$messages = array();
		$errors = array();
		$lineId = GETPOST('lineid', 'int');
		$source = $this->fetchSourceLine($context, $lineId);
		if (empty($source)) {
			$errors[] = $langs->trans('LMDBAdvancedProjectSourceLineNotFound');
			$this->renderSimpleModalMessages($messages, $errors);
			exit;
		}

		if ($action === 'lmdbadvancedproject_update_split') {
			if (!$this->checkToken()) {
				accessforbidden('Bad value for token');
			}

			$result = $this->saveSplit($context, $source, $messages, $errors);
			if ($result === 1) {
				$messages[] = $langs->trans('LMDBAdvancedProjectSplitSaved');
			} elseif ($result === 2) {
				$messages[] = $langs->trans('LMDBAdvancedProjectSplitDeleted');
			}
		}

		$this->renderSplitForm($context, $source, $messages, $errors);
		exit;
	}

	/**
	 * Return invoice context handled by this hook.
	 *
	 * @param  array<string,mixed> $parameters Hook parameters
	 * @return string
	 */
	private function getInvoiceContext($parameters)
	{
		$current = empty($parameters['currentcontext']) ? '' : (string) $parameters['currentcontext'];
		if ($current === 'invoicecard' || $current === 'invoicesuppliercard') {
			return $current;
		}

		$context = empty($parameters['context']) ? '' : (string) $parameters['context'];
		if (in_array('invoicecard', explode(':', $context), true)) {
			return 'invoicecard';
		}
		if (in_array('invoicesuppliercard', explode(':', $context), true)) {
			return 'invoicesuppliercard';
		}

		return '';
	}

	/**
	 * Check feature setting for a context.
	 *
	 * @param  string $context Hook context
	 * @return bool
	 */
	private function isFeatureEnabled($context)
	{
		global $conf;

		if ($context === 'invoicesuppliercard') {
			return !empty($conf->global->LMDBADVANCEDPROJECT_ENABLE_SUPPLIER_INVOICE_SPLIT);
		}
		if ($context === 'invoicecard') {
			return !empty($conf->global->LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT);
		}

		return false;
	}

	/**
	 * Check read access for allocated parts.
	 *
	 * @param  string $context Hook context
	 * @return bool
	 */
	private function hasReadAccess($context)
	{
		global $user;

		if (empty($user->rights->lmdbadvancedproject->split->read) && empty($user->rights->lmdbadvancedproject->split->write)) {
			return false;
		}

		if (empty($user->rights->projet->lire)) {
			return false;
		}

		if ($context === 'invoicecard') {
			return !empty($user->rights->facture->lire);
		}

		return !empty($user->rights->fournisseur->facture->lire) || !empty($user->rights->fournisseur->lire);
	}

	/**
	 * Check write access for allocated parts.
	 *
	 * @param  string $context Hook context
	 * @return bool
	 */
	private function hasWriteAccess($context)
	{
		global $user;

		return !empty($user->rights->lmdbadvancedproject->split->write) && $this->hasReadAccess($context);
	}

	/**
	 * Validate CSRF token.
	 *
	 * @return bool
	 */
	private function checkToken()
	{
		$token = GETPOST('token', 'alpha');
		$expectedToken = '';
		if (function_exists('currentToken')) {
			$expectedToken = currentToken();
		} elseif (!empty($_SESSION['newtoken'])) {
			$expectedToken = $_SESSION['newtoken'];
		}

		return empty($expectedToken) || $token === $expectedToken;
	}

	/**
	 * Fetch source invoice line.
	 *
	 * @param  string $context Hook context
	 * @param  int    $lineId  Invoice line id
	 * @return stdClass|null
	 */
	private function fetchSourceLine($context, $lineId)
	{
		$lineId = (int) $lineId;
		if ($lineId <= 0) {
			return null;
		}

		if ($context === 'invoicesuppliercard') {
			$entities = function_exists('getEntity') ? getEntity('supplier_invoice', 1) : '1';
			$sql = "SELECT ffd.rowid AS line_id, ffd.fk_facture_fourn AS invoice_id, ff.ref AS document_ref, ff.datef AS document_date,";
			$sql .= " ff.fk_soc, ff.fk_projet AS invoice_project_id, ff.entity, ffd.label AS line_label, ffd.description AS line_description,";
			$sql .= " ffd.fk_product, p.ref AS product_ref, p.label AS product_label, p.description AS product_description,";
			$sql .= " ffd.qty, ffd.total_ht, ffd.total_ttc, ffd.tva_tx, ffd.product_type";
			$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn_det ffd";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = ffd.fk_facture_fourn";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ffd.fk_product";
			$sql .= " WHERE ffd.rowid = ".$lineId." AND ff.entity IN (".$entities.")";
		} else {
			$entities = function_exists('getEntity') ? getEntity('facture', 1) : '1';
			$sql = "SELECT fd.rowid AS line_id, fd.fk_facture AS invoice_id, f.ref AS document_ref, f.datef AS document_date,";
			$sql .= " f.fk_soc, f.fk_projet AS invoice_project_id, f.entity, fd.label AS line_label, fd.description AS line_description,";
			$sql .= " fd.fk_product, p.ref AS product_ref, p.label AS product_label, p.description AS product_description,";
			$sql .= " fd.qty, fd.total_ht, fd.total_ttc, fd.tva_tx, fd.product_type";
			$sql .= " FROM ".MAIN_DB_PREFIX."facturedet fd";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = fd.fk_facture";
			$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = fd.fk_product";
			$sql .= " WHERE fd.rowid = ".$lineId." AND f.entity IN (".$entities.")";
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return null;
		}

		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		return $obj ?: null;
	}

	/**
	 * Save complete split rows.
	 *
	 * @param  string        $context  Hook context
	 * @param  stdClass      $source   Source line
	 * @param  array<string> $messages Success messages
	 * @param  array<string> $errors   Error messages
	 * @return int
	 */
	private function saveSplit($context, $source, &$messages, &$errors)
	{
		global $langs, $user;

		$config = $this->getContextConfig($context);
		if (GETPOST('lmdbap_delete_split', 'int')) {
			return $this->deleteSplit($config, $source, $errors) > 0 ? 2 : -1;
		}

		$mode = GETPOST('lmdbap_mode', 'alpha');
		if (!in_array($mode, array('amount', 'quantity'), true)) {
			$mode = 'amount';
		}

		$sourceAmount = (float) $source->total_ht;
		$sourceQty = (float) $source->qty;
		$sourceTtc = (float) $source->total_ttc;
		if ($mode === 'amount' && abs($sourceAmount) < 0.00000001) {
			$errors[] = $langs->trans('LMDBAdvancedProjectAmountModeUnavailable');
			return -1;
		}
		if ($mode === 'quantity' && abs($sourceQty) < 0.00000001) {
			$errors[] = $langs->trans('LMDBAdvancedProjectQuantityModeUnavailable');
			return -1;
		}

		$projects = GETPOST('lmdbap_project', 'array');
		$amounts = GETPOST('lmdbap_amount', 'array');
		$qtys = GETPOST('lmdbap_qty', 'array');
		if (!is_array($projects)) {
			$projects = array();
		}
		if (!is_array($amounts)) {
			$amounts = array();
		}
		if (!is_array($qtys)) {
			$qtys = array();
		}

		$parts = array();
		$seenProjects = array();
		$totalAmount = 0;
		$totalQty = 0;
		$count = count($projects);
		for ($i = 0; $i < $count; $i++) {
			$projectId = empty($projects[$i]) ? 0 : (int) $projects[$i];
			$rawAmount = isset($amounts[$i]) ? $amounts[$i] : '';
			$rawQty = isset($qtys[$i]) ? $qtys[$i] : '';

			if ($projectId <= 0 && trim((string) $rawAmount) === '' && trim((string) $rawQty) === '') {
				continue;
			}
			if ($projectId <= 0) {
				$errors[] = $langs->trans('LMDBAdvancedProjectProjectRequired');
				continue;
			}
			if (isset($seenProjects[$projectId])) {
				$errors[] = $langs->trans('LMDBAdvancedProjectDuplicateProject');
				continue;
			}
			$seenProjects[$projectId] = 1;
			if (!$this->isProjectAllowed($projectId)) {
				$errors[] = $langs->trans('LMDBAdvancedProjectProjectNotAllowed');
				continue;
			}

			if ($mode === 'amount') {
				$amount = price2num($rawAmount);
				if (abs($amount) < 0.00000001) {
					$errors[] = $langs->trans('LMDBAdvancedProjectAmountRequired');
					continue;
				}
				$ratio = $amount / $sourceAmount;
				$qty = $sourceQty * $ratio;
				$totalTtc = $sourceTtc * $ratio;
			} else {
				$qty = price2num($rawQty);
				if (abs($qty) < 0.00000001) {
					$errors[] = $langs->trans('LMDBAdvancedProjectQuantityRequired');
					continue;
				}
				$ratio = $qty / $sourceQty;
				$amount = $sourceAmount * $ratio;
				$totalTtc = $sourceTtc * $ratio;
			}

			$parts[] = array(
				'fk_projet' => $projectId,
				'qty' => $qty,
				'total_ht' => $amount,
				'total_ttc' => $totalTtc,
			);
			$totalAmount += $amount;
			$totalQty += $qty;
		}

		if (empty($parts)) {
			$errors[] = $langs->trans('LMDBAdvancedProjectAtLeastOnePartRequired');
		}
		if ($mode === 'amount' && abs($totalAmount - $sourceAmount) > 0.01) {
			$errors[] = $langs->trans('LMDBAdvancedProjectAmountTotalMismatch', price($sourceAmount), price($totalAmount));
		}
		if ($mode === 'quantity' && abs($totalQty - $sourceQty) > 0.000001) {
			$errors[] = $langs->trans('LMDBAdvancedProjectQuantityTotalMismatch', price($sourceQty), price($totalQty));
		}
		if (!empty($errors)) {
			return -1;
		}

		$lineLabel = $this->getSourceLineLabel($source);
		$ref = $this->truncate($source->document_ref.' - '.$lineLabel, 128);
		$label = $this->truncate($source->document_ref.' (part)', 255);
		$date = empty($source->document_date) ? '' : (string) $source->document_date;
		$dateSql = $date === '' ? 'NULL' : "'".$this->db->escape($date)."'";
		$datec = $this->db->idate(dol_now());

		$this->db->begin();
		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$config['table']." WHERE ".$config['line_fk']." = ".((int) $source->line_id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->db->rollback();
			$errors[] = $this->db->lasterror();
			return -1;
		}

		foreach ($parts as $part) {
			$sql = "INSERT INTO ".MAIN_DB_PREFIX.$config['table']." (";
			$sql .= "ref, date, datep, qty, total_ht, total_ttc, input_mode, label, fk_soc, fk_projet, ".$config['invoice_fk'].", ".$config['line_fk'].", entity, note, datec, fk_user_author, fk_user_modif";
			$sql .= ") VALUES (";
			$sql .= "'".$this->db->escape($ref)."', ".$dateSql.", ".$dateSql.", ".$this->sqlNumber($part['qty']).", ".$this->sqlNumber($part['total_ht']).", ".$this->sqlNumber($part['total_ttc']).",";
			$sql .= " '".$this->db->escape($mode)."', '".$this->db->escape($label)."', ".((int) $source->fk_soc).", ".((int) $part['fk_projet']).", ".((int) $source->invoice_id).", ".((int) $source->line_id).", ".((int) $source->entity).", '', '".$this->db->escape($datec)."', ".((int) $user->id).", ".((int) $user->id).")";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->db->rollback();
				$errors[] = $this->db->lasterror();
				return -1;
			}
		}

		$this->db->commit();

		return 1;
	}

	/**
	 * Delete all split rows for a source line.
	 *
	 * @param  array<string,string> $config Context config
	 * @param  stdClass             $source Source line
	 * @param  array<string>        $errors Error messages
	 * @return int
	 */
	private function deleteSplit($config, $source, &$errors)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$config['table']." WHERE ".$config['line_fk']." = ".((int) $source->line_id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$errors[] = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Render split modal form.
	 *
	 * @param string        $context  Hook context
	 * @param stdClass      $source   Source line
	 * @param array<string> $messages Success messages
	 * @param array<string> $errors   Error messages
	 * @return void
	 */
	private function renderSplitForm($context, $source, $messages, $errors)
	{
		global $langs;

		$parts = $this->fetchParts($context, (int) $source->line_id);
		$hasExistingParts = !empty($parts);
		$projects = $this->fetchProjects();
		$editRow = $this->getRequestRowNumber('editrow');
		$deleteRow = $this->getRequestRowNumber('deleterow');
		$canAmount = abs((float) $source->total_ht) > 0.00000001;
		$canQuantity = abs((float) $source->qty) > 0.00000001;
		$mode = $canAmount ? 'amount' : ($canQuantity ? 'quantity' : 'amount');
		if (!empty($parts[0]['input_mode']) && in_array($parts[0]['input_mode'], array('amount', 'quantity'), true)) {
			$mode = $parts[0]['input_mode'];
		}
		if ($mode === 'amount' && !$canAmount && $canQuantity) {
			$mode = 'quantity';
		}
		if ($mode === 'quantity' && !$canQuantity && $canAmount) {
			$mode = 'amount';
		}

		if ($deleteRow !== null && $deleteRow > 0 && isset($parts[$deleteRow - 1])) {
			array_splice($parts, $deleteRow - 1, 1);
			$editRow = null;
		}

		if (empty($parts)) {
			$parts[] = array(
				'fk_projet' => empty($source->invoice_project_id) ? 0 : (int) $source->invoice_project_id,
				'qty' => (float) $source->qty,
				'total_ht' => (float) $source->total_ht,
				'total_ttc' => (float) $source->total_ttc,
				'input_mode' => $mode,
			);
			$editRow = 1;
		} elseif ($editRow !== null && ($editRow < 1 || $editRow > count($parts))) {
			$editRow = null;
		}

		$token = function_exists('newToken') ? newToken() : (empty($_SESSION['newtoken']) ? '' : $_SESSION['newtoken']);
		$actionUrl = $this->buildSplitModalUrl('lmdbadvancedproject_update_split', $source);
		$cancelUrl = $this->buildInvoiceCardUrl($source);
		$sourceDisplay = $this->getSourceDisplayParts($source);

		$this->renderSimpleModalMessages($messages, $errors);

		print '<form method="POST" id="lmdbap-split-form" action="'.$this->escape($actionUrl).'">';
		print '<input type="hidden" name="token" value="'.$this->escape($token).'">';
		print '<input type="hidden" name="lineid" value="'.((int) $source->line_id).'">';
		print '<div class="lmdbap-split-source">';
		print '<div><strong>'.$langs->trans('Ref').'</strong> '.$this->escape($source->document_ref).'</div>';
		print '<div><strong>'.$langs->trans('Label').'</strong> '.$this->renderSourceLabel($sourceDisplay).'</div>';
		print '<div><strong>'.$langs->trans('Qty').'</strong> '.price($source->qty).' &nbsp; <strong>'.$langs->trans('AmountHT').'</strong> '.price($source->total_ht).' &nbsp; <strong>'.$langs->trans('AmountTTC').'</strong> '.price($source->total_ttc).'</div>';
		print '</div>';

		if (!$canAmount) {
			print '<div class="warning">'.$langs->trans('LMDBAdvancedProjectAmountModeUnavailable').'</div>';
		}
		if (!$canQuantity) {
			print '<div class="warning">'.$langs->trans('LMDBAdvancedProjectQuantityModeUnavailable').'</div>';
		}

		print '<div class="lmdbap-split-mode">';
		print '<label><input type="radio" name="lmdbap_mode" value="amount"'.($mode === 'amount' ? ' checked' : '').($canAmount ? '' : ' disabled').'> '.$langs->trans('LMDBAdvancedProjectSplitByAmount').'</label>';
		print '<label><input type="radio" name="lmdbap_mode" value="quantity"'.($mode === 'quantity' ? ' checked' : '').($canQuantity ? '' : ' disabled').'> '.$langs->trans('LMDBAdvancedProjectSplitByQuantity').'</label>';
		print '</div>';

		print '<table class="noborder centpercent lmdbap-split-table">';
		print '<thead><tr class="liste_titre"><th class="lmdbap-project-col">'.$langs->trans('Project').'</th><th class="right lmdbap-amount-col">'.$langs->trans('AmountHT').'</th><th class="right lmdbap-qty-col">'.$langs->trans('Qty').'</th><th class="lmdbap-action-col"></th></tr></thead>';
		print '<tbody>';
		foreach ($parts as $index => $part) {
			$rowNo = $index + 1;
			$this->renderSplitRow($projects, $part, $source, $token, $rowNo, $editRow === $rowNo);
		}
		$this->renderSplitRow($projects, array('fk_projet' => 0, 'total_ht' => '', 'qty' => ''), $source, $token, 0, true, true);
		print '</tbody>';
		print '<tfoot><tr><td><strong>'.$langs->trans('BudgetReportTotal').'</strong></td><td class="right lmdbap-amount-col" id="lmdbap-total-amount"></td><td class="right lmdbap-qty-col" id="lmdbap-total-qty"></td><td></td></tr></tfoot>';
		print '</table>';

		print '<div class="tabsAction lmdbap-add-actions">';
		print '<button type="button" class="button" id="lmdbap-add-row">'.$langs->trans('Add').'</button> ';
		print '</div>';
		print '<div class="tabsAction lmdbap-dialog-footer">';
		print '<a class="button lmdbap-cancel-split" href="'.$this->escape($cancelUrl).'">'.$langs->trans('Cancel').'</a> ';
		if ($hasExistingParts) {
			print '<button type="button" class="button button-delete lmdbap-delete-split">'.$langs->trans('LMDBAdvancedProjectDeleteBreakdown').'</button> ';
		}
		print '<input type="submit" form="lmdbap-split-form" class="button button-save" value="'.$langs->trans('Save').'"'.(!$canAmount && !$canQuantity ? ' disabled' : '').'>';
		print '</div>';
		print '</form>';

		$this->renderSplitFormScript($mode);
	}

	/**
	 * Render modal messages.
	 *
	 * @param array<string> $messages Success messages
	 * @param array<string> $errors   Error messages
	 * @return void
	 */
	private function renderSimpleModalMessages($messages, $errors)
	{
		foreach ($messages as $message) {
			print '<div class="ok">'.$this->escape($message).'</div>';
		}
		foreach ($errors as $error) {
			print '<div class="error">'.$this->escape($error).'</div>';
		}
	}

	/**
	 * Render one allocation row.
	 *
	 * @param array<int,array<string,string|int>> $projects Project options
	 * @param array<string,mixed>             $part     Existing/default part
	 * @param stdClass                       $source   Source line
	 * @param string                         $token    CSRF token
	 * @param int                            $rowNo    1-based row number
	 * @param bool                           $editing  Render row form visible
	 * @param bool                           $template Is template row
	 * @return void
	 */
	private function renderSplitRow($projects, $part, $source, $token, $rowNo = 0, $editing = false, $template = false)
	{
		global $langs;

		$class = $template ? ' class="lmdbap-split-row lmdbap-template-row lmdbap-editing-row" style="display:none"' : ' class="lmdbap-split-row oddeven'.($editing ? ' lmdbap-editing-row' : '').'"';
		$projectId = empty($part['fk_projet']) ? 0 : (int) $part['fk_projet'];
		$amount = array_key_exists('total_ht', $part) ? $part['total_ht'] : '';
		$qty = array_key_exists('qty', $part) ? $part['qty'] : '';
		$viewStyle = ($editing || $template) ? ' style="display:none"' : '';
		$editStyle = ($editing || $template) ? '' : ' style="display:none"';
		$editUrl = $template ? '#' : $this->buildSplitModalUrl('lmdbadvancedproject_edit_split', $source, array('editrow' => $rowNo), $token);
		$deleteUrl = $template ? '#' : $this->buildSplitModalUrl('lmdbadvancedproject_edit_split', $source, array('deleterow' => $rowNo), $token);

		print '<tr'.$class.'>';
		print '<td class="lmdbap-project-col">';
		print '<span class="lmdbap-row-view"'.$viewStyle.'>'.$this->renderProjectDisplay($projects, $projectId).'</span>';
		print '<span class="lmdbap-row-edit"'.$editStyle.'><select class="flat minwidth300 lmdbap-project-select" name="lmdbap_project[]">';
		print '<option value="0">&nbsp;</option>';
		foreach ($projects as $project) {
			$selected = ((int) $project['id'] === $projectId) ? ' selected' : '';
			print '<option value="'.((int) $project['id']).'"'.$selected.'>'.$this->escape($project['label']).'</option>';
		}
		print '</select></span></td>';
		print '<td class="right lmdbap-amount-col">';
		print '<span class="lmdbap-row-view lmdbap-amount-display"'.$viewStyle.'>'.$this->formatDisplayNumber($amount).'</span>';
		print '<span class="lmdbap-row-edit"'.$editStyle.'><input type="text" class="flat maxwidth75 right lmdbap-amount-input" name="lmdbap_amount[]" value="'.$this->escape($this->formatInputNumber($amount)).'"></span>';
		print '</td>';
		print '<td class="right lmdbap-qty-col">';
		print '<span class="lmdbap-row-view lmdbap-qty-display"'.$viewStyle.'>'.$this->formatDisplayNumber($qty).'</span>';
		print '<span class="lmdbap-row-edit"'.$editStyle.'><input type="text" class="flat maxwidth75 right lmdbap-qty-input" name="lmdbap_qty[]" value="'.$this->escape($this->formatInputNumber($qty)).'"></span>';
		print '</td>';
		print '<td class="center lmdbap-action-col">';
		print '<span class="lmdbap-row-actions">';
		if (!$template) {
			print '<a class="editfielda reposition lmdbap-edit-row classfortooltip" href="'.$this->escape($editUrl).'" title="'.$this->escape($langs->trans('Edit')).'" aria-label="'.$this->escape($langs->trans('Edit')).'">'.$this->renderEditIcon().'</a>';
		}
		print '<a class="reposition lmdbap-remove-row classfortooltip" href="'.$this->escape($deleteUrl).'" title="'.$this->escape($langs->trans('Delete')).'" aria-label="'.$this->escape($langs->trans('Delete')).'">'.$this->renderDeleteIcon().'</a>';
		print '</span>';
		print '</td>';
		print '</tr>';
	}

	/**
	 * Render client-side behavior for the modal form.
	 *
	 * @param string $initialMode Initial mode
	 * @return void
	 */
	private function renderSplitFormScript($initialMode)
	{
		?>
		<script>
		jQuery(function($) {
			var $form = $("#lmdbap-split-form");
			if (!$form.length) return;
			var lmdbapAjaxEnabled = <?php echo $this->isAjaxEnabled() ? 'true' : 'false'; ?>;

			function parseNumber(value) {
				value = String(value || "").replace(/\s/g, "").replace(",", ".");
				var number = parseFloat(value);
				return isNaN(number) ? 0 : number;
			}

			function currentMode() {
				return $form.find("input[name='lmdbap_mode']:checked").val() || <?php echo json_encode($initialMode); ?>;
			}

			function initProjectSelects($scope) {
				if (!$.fn.select2) return;
				var $dropdownParent = $("#dialogforpopup");
				if (!$dropdownParent.length) {
					$dropdownParent = $form.closest(".ui-dialog-content");
				}
				if (!$dropdownParent.length) {
					$dropdownParent = $form;
				}

				$scope.find("select.lmdbap-project-select").each(function() {
					var $select = $(this);
					if ($select.closest(".lmdbap-template-row").length || !$select.closest(".lmdbap-row-edit").is(":visible") || $select.data("select2") || $select.hasClass("select2-hidden-accessible") || $select.hasClass("select2-offscreen")) {
						return;
					}
					try {
						$select.select2({
							width: "100%",
							dropdownParent: $dropdownParent
						});
					} catch (e) {
						try {
							$select.select2({width: "100%"});
						} catch (e2) {}
					}
				});
			}

			function destroyProjectSelects($scope) {
				if (!$.fn.select2) return;
				$scope.find("select.lmdbap-project-select").each(function() {
					var $select = $(this);
					if ($select.data("select2") || $select.hasClass("select2-hidden-accessible") || $select.hasClass("select2-offscreen")) {
						try {
							$select.select2("destroy");
						} catch (e) {}
					}
				});
			}

			function updateMode() {
				var mode = currentMode();
				var amountMode = mode === "amount";
				$form.find(".lmdbap-amount-col").toggle(amountMode);
				$form.find(".lmdbap-qty-col").toggle(!amountMode);
				$form.find(".lmdbap-amount-input").prop("disabled", !amountMode);
				$form.find(".lmdbap-qty-input").prop("disabled", amountMode);
			}

			function updateTotals() {
				var totalAmount = 0;
				var totalQty = 0;
				$form.find("tbody tr.lmdbap-split-row:visible").each(function() {
					totalAmount += parseNumber($(this).find(".lmdbap-amount-input").val());
					totalQty += parseNumber($(this).find(".lmdbap-qty-input").val());
				});
				$("#lmdbap-total-amount").text(totalAmount.toFixed(2));
				$("#lmdbap-total-qty").text(totalQty.toFixed(6).replace(/0+$/, "").replace(/\.$/, ""));
			}

			function moveFooterToDialog() {
				var $dialog = $form.closest(".ui-dialog-content");
				var $footer = $form.find(".lmdbap-dialog-footer");
				if (!$dialog.length || !$footer.length || !$.fn.dialog || !$dialog.dialog("instance")) return;
				var $pane = $dialog.closest(".ui-dialog").find(".ui-dialog-buttonpane");
				if (!$pane.length) {
					$pane = $("<div class='ui-dialog-buttonpane ui-widget-content ui-helper-clearfix'><div class='ui-dialog-buttonset'></div></div>");
					$dialog.after($pane);
				}
				var $buttonset = $pane.find(".ui-dialog-buttonset");
				if (!$buttonset.length) {
					$buttonset = $("<div class='ui-dialog-buttonset'></div>").appendTo($pane);
				}
				$buttonset.empty().append($footer.children().detach());
				$footer.remove();
				$pane.show();
			}

			$(document).off("lmdbap:split-dialog-loaded.lmdbap").on("lmdbap:split-dialog-loaded.lmdbap", function(event, $dialog) {
				if (!$dialog || !$dialog.find || !$dialog.find("#lmdbap-split-form").length) return;
				moveFooterToDialog();
			});

			$form.off("change.lmdbap input.lmdbap click.lmdbap");
			$form.on("change.lmdbap", "input[name='lmdbap_mode']", function() {
				updateMode();
			});
			$form.on("input.lmdbap", ".lmdbap-amount-input,.lmdbap-qty-input", updateTotals);
			$form.on("click.lmdbap", ".lmdbap-edit-row", function(event) {
				if (!lmdbapAjaxEnabled) {
					return true;
				}
				event.preventDefault();
				var $row = $(this).closest("tr.lmdbap-split-row");
				$row.addClass("lmdbap-editing-row");
				$row.find(".lmdbap-row-view").hide();
				$row.find(".lmdbap-row-edit").show();
				initProjectSelects($row);
				updateMode();
				return false;
			});
			$form.on("click.lmdbap", ".lmdbap-remove-row", function(event) {
				if (!lmdbapAjaxEnabled && $(this).attr("href") !== "#") {
					return true;
				}
				event.preventDefault();
				var $rows = $form.find("tbody tr.lmdbap-split-row:visible");
				if ($rows.length <= 1) return false;
				var $row = $(this).closest("tr");
				destroyProjectSelects($row);
				$row.remove();
				updateTotals();
				return false;
			});
			$(document).off("click.lmdbapDeleteSplit").on("click.lmdbapDeleteSplit", ".lmdbap-delete-split", function(event) {
				event.preventDefault();
				var $targetForm = $("#lmdbap-split-form");
				$targetForm.find("input[name='lmdbap_delete_split']").remove();
				$("<input type='hidden' name='lmdbap_delete_split' value='1'>").appendTo($targetForm);
				$targetForm.trigger("submit");
				return false;
			});
			$(document).off("click.lmdbapCancelSplit").on("click.lmdbapCancelSplit", ".lmdbap-cancel-split", function(event) {
				if (!lmdbapAjaxEnabled) {
					return true;
				}
				var $dialog = $("#dialogforpopup");
				if (!$dialog.length || !$.fn.dialog || !$dialog.dialog("instance")) {
					return true;
				}
				event.preventDefault();
				$dialog.dialog("close");
				return false;
			});
			$(document).off("click.lmdbapSaveSplit").on("click.lmdbapSaveSplit", "#lmdbap-split-form .button-save,.ui-dialog-buttonpane .button-save", function() {
				$("#lmdbap-split-form").find("input[name='lmdbap_delete_split']").remove();
			});
			$("#lmdbap-add-row").off("click.lmdbap").on("click.lmdbap", function() {
				var $template = $form.find("tbody tr.lmdbap-template-row").first();
				var $row = $template.clone();
				$row.removeClass("lmdbap-template-row").addClass("oddeven lmdbap-editing-row").show();
				$row.find(".lmdbap-row-view").hide();
				$row.find(".lmdbap-row-edit").show();
				$row.find("select").val("0");
				$row.find("input[type='text']").val("");
				$template.before($row);
				initProjectSelects($row);
				updateMode();
				updateTotals();
			});

			initProjectSelects($form);
			updateMode();
			updateTotals();
			moveFooterToDialog();
		});
		</script>
		<?php
	}

	/**
	 * Return a request row number, preserving zero/absent differences.
	 *
	 * @param  string $name Request parameter name
	 * @return int|null
	 */
	private function getRequestRowNumber($name)
	{
		$isset = function_exists('GETPOSTISSET') ? GETPOSTISSET($name) : (isset($_GET[$name]) || isset($_POST[$name]));
		if (!$isset) {
			return null;
		}

		return (int) GETPOST($name, 'int');
	}

	/**
	 * Build a modal URL that keeps the invoice identifier used by the current page.
	 *
	 * @param  string              $action Action name
	 * @param  stdClass            $source Source line
	 * @param  array<string,mixed> $extra  Extra query parameters
	 * @param  string              $token  Optional token
	 * @return string
	 */
	private function buildSplitModalUrl($action, $source, $extra = array(), $token = '')
	{
		$query = array();
		$facid = GETPOST('facid', 'int');
		$id = GETPOST('id', 'int');
		if ($facid > 0) {
			$query['facid'] = $facid;
		} elseif ($id > 0) {
			$query['id'] = $id;
		} else {
			$query['facid'] = (int) $source->invoice_id;
		}

		$query['lineid'] = (int) $source->line_id;
		$query['action'] = $action;
		if ($token !== '') {
			$query['token'] = $token;
		}
		foreach ($extra as $key => $value) {
			$query[$key] = $value;
		}

		return $_SERVER['PHP_SELF'].'?'.http_build_query($query, '', '&');
	}

	/**
	 * Build the current invoice card URL without split modal action parameters.
	 *
	 * @param  stdClass $source Source line
	 * @return string
	 */
	private function buildInvoiceCardUrl($source)
	{
		$query = array();
		$facid = GETPOST('facid', 'int');
		$id = GETPOST('id', 'int');
		if ($facid > 0) {
			$query['facid'] = $facid;
		} elseif ($id > 0) {
			$query['id'] = $id;
		} else {
			$query['facid'] = (int) $source->invoice_id;
		}

		return $_SERVER['PHP_SELF'].'?'.http_build_query($query, '', '&');
	}

	/**
	 * Render the compact source label with a native Dolibarr tooltip.
	 *
	 * @param  array<string,string> $sourceDisplay Label/description pair
	 * @return string
	 */
	private function renderSourceLabel($sourceDisplay)
	{
		global $form;

		$label = $this->escape($sourceDisplay['label']);
		$description = empty($sourceDisplay['description']) ? '' : $this->escapeTooltip($sourceDisplay['description']);
		if ($description === '') {
			return $label;
		}
		if (!is_object($form)) {
			$form = new Form($this->db);
		}
		if (is_object($form) && method_exists($form, 'textwithpicto') && $this->isGlobalFlagEnabled('MAIN_ENABLE_AJAX_TOOLTIP')) {
			return $label.' '.$form->textwithpicto('', $description);
		}
		if (is_object($form) && method_exists($form, 'textwithtooltip')) {
			return $form->textwithtooltip($label, $description, 3, 0, '', 0, 2);
		}

		return $label.' <span class="fas fa-info-circle classfortooltip lmdbap-source-info" title="'.$description.'" aria-label="Info"></span>';
	}

	/**
	 * Render a project using Dolibarr native project links.
	 *
	 * @param  array<int,array<string,string|int>> $projects  Project options
	 * @param  int                                 $projectId Selected project id
	 * @return string
	 */
	private function renderProjectDisplay($projects, $projectId)
	{
		foreach ($projects as $project) {
			if ((int) $project['id'] !== (int) $projectId) {
				continue;
			}

			$projectObject = new Project($this->db);
			$projectObject->id = (int) $project['id'];
			$projectObject->ref = (string) $project['ref'];
			$projectObject->title = (string) $project['title'];
			$link = method_exists($projectObject, 'getNomUrl') ? $projectObject->getNomUrl(1) : $this->escape($projectObject->ref);
			$title = trim((string) $project['title']);

			return $link.($title !== '' ? ' - '.$this->escape($title) : '');
		}

		return '&nbsp;';
	}

	/**
	 * Render an edit icon matching Dolibarr native line actions.
	 *
	 * @return string
	 */
	private function renderEditIcon()
	{
		global $langs;

		if (function_exists('img_edit')) {
			return img_edit($langs->trans('Edit'));
		}

		return '<span class="fas fa-pencil-alt"></span>';
	}

	/**
	 * Render a delete icon matching Dolibarr native line actions.
	 *
	 * @return string
	 */
	private function renderDeleteIcon()
	{
		global $langs;

		if (function_exists('img_delete')) {
			return img_delete($langs->trans('Delete'));
		}

		return '<span class="fas fa-trash"></span>';
	}

	/**
	 * Fetch existing parts.
	 *
	 * @param  string $context Hook context
	 * @param  int    $lineId  Source line id
	 * @return array<int,array<string,mixed>>
	 */
	private function fetchParts($context, $lineId)
	{
		$config = $this->getContextConfig($context);
		$parts = array();
		$sql = "SELECT rowid, fk_projet, qty, total_ht, total_ttc, input_mode FROM ".MAIN_DB_PREFIX.$config['table']." WHERE ".$config['line_fk']." = ".((int) $lineId)." ORDER BY rowid ASC";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$parts[] = array(
					'rowid' => (int) $obj->rowid,
					'fk_projet' => (int) $obj->fk_projet,
					'qty' => (float) $obj->qty,
					'total_ht' => (float) $obj->total_ht,
					'total_ttc' => (float) $obj->total_ttc,
					'input_mode' => (string) $obj->input_mode,
				);
			}
			$this->db->free($resql);
		}

		return $parts;
	}

	/**
	 * Fetch project options.
	 *
	 * @return array<int,array<string,string|int>>
	 */
	private function fetchProjects()
	{
		$projects = array();
		$entities = function_exists('getEntity') ? getEntity('project') : '1';
		$sql = "SELECT rowid, ref, title FROM ".MAIN_DB_PREFIX."projet WHERE entity IN (".$entities.") ORDER BY ref ASC, title ASC";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$label = trim((string) $obj->ref.' - '.(string) $obj->title);
				$projects[] = array('id' => (int) $obj->rowid, 'ref' => (string) $obj->ref, 'title' => (string) $obj->title, 'label' => $label);
			}
			$this->db->free($resql);
		}

		return $projects;
	}

	/**
	 * Check selected project is available in current entity scope.
	 *
	 * @param  int $projectId Project id
	 * @return bool
	 */
	private function isProjectAllowed($projectId)
	{
		$entities = function_exists('getEntity') ? getEntity('project') : '1';
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."projet WHERE rowid = ".((int) $projectId)." AND entity IN (".$entities.")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}

		$allowed = $this->db->num_rows($resql) > 0;
		$this->db->free($resql);

		return $allowed;
	}

	/**
	 * Return config for context.
	 *
	 * @param  string $context Hook context
	 * @return array<string,string>
	 */
	private function getContextConfig($context)
	{
		if ($context === 'invoicesuppliercard') {
			return array(
				'table' => 'lmdbadvancedproject_supplier_invoice_parts',
				'invoice_fk' => 'fk_facture_fourn',
				'line_fk' => 'fk_facture_fourn_det',
			);
		}

		return array(
			'table' => 'lmdbadvancedproject_customer_invoice_parts',
			'invoice_fk' => 'fk_facture',
			'line_fk' => 'fk_facture_det',
		);
	}

	/**
	 * Get a readable source line label.
	 *
	 * @param  stdClass $source Source line
	 * @return string
	 */
	private function getSourceLineLabel($source)
	{
		$display = $this->getSourceDisplayParts($source);

		return $display['label'];
	}

	/**
	 * Return display label and tooltip description for the source line.
	 *
	 * @param  stdClass $source Source line
	 * @return array<string,string>
	 */
	private function getSourceDisplayParts($source)
	{
		$productLabelLines = $this->splitSourceText(empty($source->product_label) ? '' : $source->product_label);
		$productRefLines = $this->splitSourceText(empty($source->product_ref) ? '' : $source->product_ref);
		$lineLabelLines = $this->splitSourceText(empty($source->line_label) ? '' : $source->line_label);
		$lineDescriptionLines = $this->splitSourceText(empty($source->line_description) ? '' : $source->line_description);

		$label = '';
		if (!empty($source->fk_product) && !empty($productLabelLines)) {
			$label = $productLabelLines[0];
		}
		if (!empty($source->fk_product) && $label === '' && !empty($productRefLines)) {
			$label = $productRefLines[0];
		}
		if ($label === '' && !empty($lineLabelLines)) {
			$label = $lineLabelLines[0];
		}
		if ($label === '' && !empty($lineDescriptionLines)) {
			$label = $lineDescriptionLines[0];
		}
		if ($label === '') {
			$label = '-';
		}

		$tooltipParts = array();
		if (!empty($lineLabelLines)) {
			if ($this->normalizeComparableText($lineLabelLines[0]) === $this->normalizeComparableText($label)) {
				array_shift($lineLabelLines);
			}
			$this->appendTooltipText($tooltipParts, implode("\n", $lineLabelLines), $label);
		}
		$this->appendTooltipText($tooltipParts, empty($source->line_description) ? '' : $source->line_description, $label);
		$this->appendTooltipText($tooltipParts, empty($source->product_description) ? '' : $source->product_description, $label);

		return array(
			'label' => $label,
			'description' => implode("\n\n", $tooltipParts),
		);
	}

	/**
	 * Append a unique tooltip text part.
	 *
	 * @param array<string> $parts        Existing text parts
	 * @param string        $text         New text
	 * @param string        $visibleLabel Visible label
	 * @return void
	 */
	private function appendTooltipText(&$parts, $text, $visibleLabel)
	{
		$text = $this->normalizeSourceText($text);
		if ($text === '') {
			return;
		}

		$comparableText = $this->normalizeComparableText($text);
		if ($comparableText === '' || $comparableText === $this->normalizeComparableText($visibleLabel)) {
			return;
		}

		foreach ($parts as $part) {
			if ($this->normalizeComparableText($part) === $comparableText) {
				return;
			}
		}

		$parts[] = $text;
	}

	/**
	 * Split a source text into non-empty display lines.
	 *
	 * @param  string $value Raw text
	 * @return array<int,string>
	 */
	private function splitSourceText($value)
	{
		$text = $this->normalizeSourceText($value);
		if ($text === '') {
			return array();
		}

		$lines = preg_split('/\n+/', $text);
		$result = array();
		foreach ($lines as $line) {
			$line = trim($line);
			if ($line !== '') {
				$result[] = $line;
			}
		}

		return $result;
	}

	/**
	 * Normalize source text for display.
	 *
	 * @param  string $value Raw text
	 * @return string
	 */
	private function normalizeSourceText($value)
	{
		$value = str_replace(array("\\r\\n", "\\n", "\\r"), "\n", (string) $value);
		$value = preg_replace('/<br\s*\/?>/i', "\n", $value);
		$value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
		$value = str_replace(array("\r\n", "\r"), "\n", $value);
		$value = preg_replace('/[ \t]+/', ' ', $value);
		$value = preg_replace('/ *\n+ */', "\n", $value);

		return trim($value);
	}

	/**
	 * Normalize text for duplicate comparisons.
	 *
	 * @param  string $value Raw text
	 * @return string
	 */
	private function normalizeComparableText($value)
	{
		return preg_replace('/\s+/', ' ', trim($this->normalizeSourceText($value)));
	}

	/**
	 * Format number for SQL.
	 *
	 * @param  float|int $value Value
	 * @return string
	 */
	private function sqlNumber($value)
	{
		return number_format((float) $value, 8, '.', '');
	}

	/**
	 * Format number for inputs.
	 *
	 * @param  mixed $value Value
	 * @return string
	 */
	private function formatInputNumber($value)
	{
		if ($value === '' || $value === null) {
			return '';
		}

		$value = number_format((float) $value, 8, '.', '');
		$value = rtrim(rtrim($value, '0'), '.');

		return $value === '-0' ? '0' : $value;
	}

	/**
	 * Format a number for read-only display.
	 *
	 * @param  mixed $value Value
	 * @return string
	 */
	private function formatDisplayNumber($value)
	{
		if ($value === '' || $value === null) {
			return '&nbsp;';
		}

		return price((float) $value);
	}

	/**
	 * Check a Dolibarr global flag.
	 *
	 * @param  string $name Constant name
	 * @return bool
	 */
	private function isGlobalFlagEnabled($name)
	{
		global $conf;

		if (function_exists('getDolGlobalInt')) {
			return (bool) getDolGlobalInt($name);
		}

		return !empty($conf->global->{$name});
	}

	/**
	 * Check if Dolibarr AJAX behaviors are enabled.
	 *
	 * @return bool
	 */
	private function isAjaxEnabled()
	{
		return !$this->isGlobalFlagEnabled('MAIN_DISABLE_AJAX');
	}

	/**
	 * Escape HTML.
	 *
	 * @param  string $value Value
	 * @return string
	 */
	private function escape($value)
	{
		if (function_exists('dol_escape_htmltag')) {
			return dol_escape_htmltag((string) $value);
		}

		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Escape a tooltip value.
	 *
	 * @param  string $value Value
	 * @return string
	 */
	private function escapeTooltip($value)
	{
		if (function_exists('dol_escape_htmltag')) {
			return dol_escape_htmltag((string) $value, 1);
		}

		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Truncate string.
	 *
	 * @param  string $value  Value
	 * @param  int    $length Length
	 * @return string
	 */
	private function truncate($value, $length)
	{
		$value = (string) $value;
		if (function_exists('dol_trunc')) {
			return dol_trunc($value, $length);
		}

		return strlen($value) > $length ? substr($value, 0, $length) : $value;
	}
}
