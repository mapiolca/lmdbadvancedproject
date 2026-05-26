<?php
/* Copyright (C) 2026 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
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

		$hookmanager->resPrint .= '
		<script>
		jQuery(function($) {
			var lmdbapToken = '.json_encode($token).';
			var lmdbapTitle = '.json_encode($title).';
			var lmdbapLoading = '.json_encode($loading).';

			function lmdbapBuildSplitUrl(href) {
				if (!href) return "";
				var url = href.split("#")[0];
				if (url.indexOf("action=editline") !== -1) {
					url = url.replace("action=editline", "action=lmdbadvancedproject_edit_split");
				} else {
					url += (url.indexOf("?") === -1 ? "?" : "&") + "action=lmdbadvancedproject_edit_split";
				}
				if (url.indexOf("token=") === -1 && lmdbapToken) {
					url += (url.indexOf("?") === -1 ? "?" : "&") + "token=" + encodeURIComponent(lmdbapToken);
				}
				return url;
			}

			$("tbody td.linecoledit a").each(function() {
				var $editLink = $(this);
				var href = $editLink.attr("href") || "";
				if (href.indexOf("lineid=") === -1 || href.indexOf("action=editline") === -1 || $editLink.closest("tr").find(".lmdbap-line-split-cell").length) {
					return;
				}
				var splitUrl = lmdbapBuildSplitUrl(href);
				if (!splitUrl) return;
				var html = \'<td class="center lmdbap-line-split-cell"><a class="lmdbap-edit-split classfortooltip" href="\' + splitUrl + \'" title="\' + lmdbapTitle + \'"><span class="fas fa-sitemap"></span></a></td>\';
				$editLink.closest("td").before(html);
			});

			$("thead .linecolmove").attr("colspan", function(index, value) {
				var span = parseInt(value, 10);
				return isNaN(span) ? value : span + 1;
			});
			$("td.linecoledit").last().attr("colspan", function(index, value) {
				var span = parseInt(value, 10);
				return isNaN(span) ? value : span + 1;
			});

			$(document).on("click", ".lmdbap-edit-split", function(event) {
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
				});
			});

			$(document).on("submit", "#lmdbap-split-form", function(event) {
				event.preventDefault();
				var $form = $(this);
				$.ajax({
					url: $form.attr("action"),
					type: "POST",
					data: $form.serialize(),
					success: function(result) {
						$("#dialogforpopup").html(result);
					}
				});
				return false;
			});
		});
		</script>';

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

		$langs->load('lmdbadvancedproject@lmdbadvancedproject');

		if ($this->isFeatureEnabled('invoicesuppliercard') && $this->hasReadAccess('invoicesuppliercard')) {
			$hookmanager->resArray['lmdbadvancedproject_supplier_invoice_parts'] = array(
				'name' => $langs->trans('LMDBAdvancedProjectSupplierInvoiceParts'),
				'title' => $langs->trans('LMDBAdvancedProjectSupplierInvoicePartsList'),
				'class' => 'LmdbAdvancedProjectSupplierInvoicePart',
				'table' => 'lmdbadvancedproject_supplier_invoice_parts',
				'datefieldname' => 'date',
				'margin' => 'minus',
				'disableamount' => 0,
				'test' => 1,
			);
		}

		if ($this->isFeatureEnabled('invoicecard') && $this->hasReadAccess('invoicecard')) {
			$hookmanager->resArray['lmdbadvancedproject_customer_invoice_parts'] = array(
				'name' => $langs->trans('LMDBAdvancedProjectCustomerInvoiceParts'),
				'title' => $langs->trans('LMDBAdvancedProjectCustomerInvoicePartsList'),
				'class' => 'LmdbAdvancedProjectCustomerInvoicePart',
				'table' => 'lmdbadvancedproject_customer_invoice_parts',
				'datefieldname' => 'date',
				'margin' => 'add',
				'disableamount' => 0,
				'test' => 1,
			);
		}

		return 0;
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
			$sql .= " ffd.qty, ffd.total_ht, ffd.total_ttc, ffd.tva_tx, ffd.product_type";
			$sql .= " FROM ".MAIN_DB_PREFIX."facture_fourn_det ffd";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."facture_fourn ff ON ff.rowid = ffd.fk_facture_fourn";
			$sql .= " WHERE ffd.rowid = ".$lineId." AND ff.entity IN (".$entities.")";
		} else {
			$entities = function_exists('getEntity') ? getEntity('facture', 1) : '1';
			$sql = "SELECT fd.rowid AS line_id, fd.fk_facture AS invoice_id, f.ref AS document_ref, f.datef AS document_date,";
			$sql .= " f.fk_soc, f.fk_projet AS invoice_project_id, f.entity, fd.label AS line_label, fd.description AS line_description,";
			$sql .= " fd.qty, fd.total_ht, fd.total_ttc, fd.tva_tx, fd.product_type";
			$sql .= " FROM ".MAIN_DB_PREFIX."facturedet fd";
			$sql .= " INNER JOIN ".MAIN_DB_PREFIX."facture f ON f.rowid = fd.fk_facture";
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

		$config = $this->getContextConfig($context);
		$parts = $this->fetchParts($context, (int) $source->line_id);
		$hasExistingParts = !empty($parts);
		$projects = $this->fetchProjects();
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

		if (empty($parts)) {
			$parts[] = array(
				'fk_projet' => empty($source->invoice_project_id) ? 0 : (int) $source->invoice_project_id,
				'qty' => (float) $source->qty,
				'total_ht' => (float) $source->total_ht,
				'total_ttc' => (float) $source->total_ttc,
				'input_mode' => $mode,
			);
		}

		$token = function_exists('newToken') ? newToken() : (empty($_SESSION['newtoken']) ? '' : $_SESSION['newtoken']);
		$actionUrl = $_SERVER['PHP_SELF'].'?facid='.((int) $source->invoice_id).'&lineid='.((int) $source->line_id).'&action=lmdbadvancedproject_update_split';

		$this->renderSimpleModalMessages($messages, $errors);

		print '<form method="POST" id="lmdbap-split-form" action="'.$this->escape($actionUrl).'">';
		print '<input type="hidden" name="token" value="'.$this->escape($token).'">';
		print '<input type="hidden" name="lineid" value="'.((int) $source->line_id).'">';
		print '<div class="lmdbap-split-source">';
		print '<div><strong>'.$langs->trans('Ref').'</strong> '.$this->escape($source->document_ref).'</div>';
		print '<div><strong>'.$langs->trans('Label').'</strong> '.$this->escape($this->getSourceLineLabel($source)).'</div>';
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
		print '<thead><tr class="liste_titre"><th>'.$langs->trans('Project').'</th><th class="right">'.$langs->trans('AmountHT').'</th><th class="right">'.$langs->trans('Qty').'</th><th></th></tr></thead>';
		print '<tbody>';
		foreach ($parts as $part) {
			$this->renderSplitRow($projects, $part);
		}
		$this->renderSplitRow($projects, array('fk_projet' => 0, 'total_ht' => '', 'qty' => ''), true);
		print '</tbody>';
		print '<tfoot><tr><td><strong>'.$langs->trans('BudgetReportTotal').'</strong></td><td class="right" id="lmdbap-total-amount"></td><td class="right" id="lmdbap-total-qty"></td><td></td></tr></tfoot>';
		print '</table>';

		print '<div class="tabsAction">';
		print '<button type="button" class="button" id="lmdbap-add-row">'.$langs->trans('Add').'</button> ';
		if ($hasExistingParts) {
			print '<button type="button" class="button button-delete lmdbap-delete-split">'.$langs->trans('LMDBAdvancedProjectDeleteBreakdown').'</button> ';
		}
		print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'"'.(!$canAmount && !$canQuantity ? ' disabled' : '').'>';
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
	 * @param array<int,array<string,string>> $projects Project options
	 * @param array<string,mixed>             $part     Existing/default part
	 * @param bool                           $template Is template row
	 * @return void
	 */
	private function renderSplitRow($projects, $part, $template = false)
	{
		global $langs;

		$class = $template ? ' class="lmdbap-split-row lmdbap-template-row" style="display:none"' : ' class="lmdbap-split-row oddeven"';
		$projectId = empty($part['fk_projet']) ? 0 : (int) $part['fk_projet'];
		$amount = array_key_exists('total_ht', $part) ? $part['total_ht'] : '';
		$qty = array_key_exists('qty', $part) ? $part['qty'] : '';

		print '<tr'.$class.'>';
		print '<td><select class="flat minwidth300 lmdbap-project-select" name="lmdbap_project[]">';
		print '<option value="0">&nbsp;</option>';
		foreach ($projects as $project) {
			$selected = ((int) $project['id'] === $projectId) ? ' selected' : '';
			print '<option value="'.((int) $project['id']).'"'.$selected.'>'.$this->escape($project['label']).'</option>';
		}
		print '</select></td>';
		print '<td class="right"><input type="text" class="flat maxwidth75 right lmdbap-amount-input" name="lmdbap_amount[]" value="'.$this->escape($this->formatInputNumber($amount)).'"></td>';
		print '<td class="right"><input type="text" class="flat maxwidth75 right lmdbap-qty-input" name="lmdbap_qty[]" value="'.$this->escape($this->formatInputNumber($qty)).'"></td>';
		print '<td class="center"><button type="button" class="button button-delete lmdbap-remove-row" title="'.$this->escape($langs->trans('Delete')).'"><span class="fas fa-trash"></span></button></td>';
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

			function parseNumber(value) {
				value = String(value || "").replace(/\s/g, "").replace(",", ".");
				var number = parseFloat(value);
				return isNaN(number) ? 0 : number;
			}

			function updateMode() {
				var mode = $form.find("input[name='lmdbap_mode']:checked").val() || <?php echo json_encode($initialMode); ?>;
				$form.find(".lmdbap-amount-input").prop("readonly", mode !== "amount");
				$form.find(".lmdbap-qty-input").prop("readonly", mode !== "quantity");
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

			$form.off("change.lmdbap input.lmdbap click.lmdbap");
			$form.on("change.lmdbap", "input[name='lmdbap_mode']", function() {
				updateMode();
			});
			$form.on("input.lmdbap", ".lmdbap-amount-input,.lmdbap-qty-input", updateTotals);
			$form.on("click.lmdbap", ".lmdbap-remove-row", function() {
				var $rows = $form.find("tbody tr.lmdbap-split-row:visible");
				if ($rows.length <= 1) return;
				$(this).closest("tr").remove();
				updateTotals();
			});
			$form.on("click.lmdbap", ".lmdbap-delete-split", function() {
				$form.find("input[name='lmdbap_delete_split']").remove();
				$("<input type='hidden' name='lmdbap_delete_split' value='1'>").appendTo($form);
				$form.trigger("submit");
			});
			$form.on("click.lmdbap", ".button-save", function() {
				$form.find("input[name='lmdbap_delete_split']").remove();
			});
			$("#lmdbap-add-row").off("click.lmdbap").on("click.lmdbap", function() {
				var $template = $form.find("tbody tr.lmdbap-template-row").first();
				var $row = $template.clone();
				$row.removeClass("lmdbap-template-row").addClass("oddeven").show();
				$row.find("select").val("0");
				$row.find("input[type='text']").val("");
				$template.before($row);
				updateMode();
				updateTotals();
			});

			updateMode();
			updateTotals();
		});
		</script>
		<?php
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
	 * @return array<int,array<string,string>>
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
				$projects[] = array('id' => (int) $obj->rowid, 'label' => $label);
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
		$label = trim((string) $source->line_label);
		if ($label === '') {
			$label = trim(strip_tags((string) $source->line_description));
		}
		return $label === '' ? '-' : $label;
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
