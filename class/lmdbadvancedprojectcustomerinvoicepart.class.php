<?php
/* Copyright (C) 2026 SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Customer invoice line part allocated to a project.
 */
class LmdbAdvancedProjectCustomerInvoicePart extends CommonObject
{
	public $module = 'lmdbadvancedproject';
	public $element = 'lmdbadvancedproject_customer_invoice_parts';
	public $table_element = 'lmdbadvancedproject_customer_invoice_parts';
	public $picto = 'bill';

	public $rowid;
	public $ref;
	public $date;
	public $datep;
	public $qty;
	public $total_ht;
	public $total_ttc;
	public $amount;
	public $label;
	public $note;
	public $fk_soc;
	public $fk_projet;
	public $fk_project;
	public $fk_facture;
	public $fk_facture_det;
	public $entity;
	public $fk_user_author;
	public $fk_user_modif;
	public $status;
	public $statut;

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
	 * Return a link to the source customer invoice.
	 *
	 * @param  int    $withpicto             0=No picto, 1=Include picto, 2=Only picto
	 * @param  string $option                Link option
	 * @param  int    $save_lastsearch_value Save lastsearch values
	 * @param  int    $notooltip             Disable tooltip
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $save_lastsearch_value = -1, $notooltip = 0)
	{
		global $conf, $langs;

		$langs->load('lmdbadvancedproject@lmdbadvancedproject');

		if (!empty($conf->dol_no_mouse_hover)) {
			$notooltip = 1;
		}

		$ref = empty($this->ref) ? (string) $this->id : (string) $this->ref;
		$url = DOL_URL_ROOT.'/compta/facture/card.php?facid='.(int) $this->fk_facture;
		if ($save_lastsearch_value == 1 || ($save_lastsearch_value == -1 && !empty($_SERVER['PHP_SELF']) && preg_match('/list\.php/', $_SERVER['PHP_SELF']))) {
			$url .= '&save_lastsearch_values=1';
		}

		$label = '<u>'.$langs->trans('ShowInvoice').'</u><br><b>'.$langs->trans('Ref').':</b> '.$ref;
		$linkclose = '';
		if (empty($notooltip)) {
			$linkclose = ' title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip"';
		}

		if ($option === 'nolink') {
			return $withpicto == 2 ? img_object($label, $this->picto) : $ref;
		}

		$result = '<a href="'.$url.'"'.$linkclose.'>';
		if ($withpicto) {
			$result .= img_object(($notooltip ? '' : $label), $this->picto, ($withpicto != 2 ? 'class="paddingright"' : ''), 0, 0, $notooltip ? 0 : 1);
		}
		if ($withpicto != 2) {
			$result .= $ref;
		}
		$result .= '</a>';

		return $result;
	}

	/**
	 * Load object from database.
	 *
	 * @param  int  $id   Object id
	 * @param  User $user User loading the object
	 * @return int
	 */
	public function fetch($id, $user = null)
	{
		$sql = "SELECT v.rowid, v.ref, v.date, v.datep, v.qty, v.total_ht, v.total_ttc, v.label, v.note,";
		$sql .= " v.fk_soc, v.fk_projet, v.fk_facture, v.fk_facture_det, v.entity, v.fk_user_author, v.fk_user_modif";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." v";
		$sql .= " WHERE v.rowid = ".((int) $id);

		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);
				$this->id = (int) $obj->rowid;
				$this->rowid = (int) $obj->rowid;
				$this->ref = $obj->ref;
				$this->date = $this->db->jdate($obj->date);
				$this->datep = $this->db->jdate($obj->datep);
				$this->qty = $obj->qty;
				$this->total_ht = $obj->total_ht;
				$this->amount = $obj->total_ht;
				$this->total_ttc = $obj->total_ttc;
				$this->label = $obj->label;
				$this->note = $obj->note;
				$this->fk_soc = (int) $obj->fk_soc;
				$this->fk_projet = (int) $obj->fk_projet;
				$this->fk_project = (int) $obj->fk_projet;
				$this->fk_facture = (int) $obj->fk_facture;
				$this->fk_facture_det = (int) $obj->fk_facture_det;
				$this->entity = (int) $obj->entity;
				$this->fk_user_author = (int) $obj->fk_user_author;
				$this->fk_user_modif = (int) $obj->fk_user_modif;
				$this->status = 1;
				$this->statut = 1;
			}
			$this->db->free($resql);

			return 1;
		}

		$this->error = "Error ".$this->db->lasterror();
		return -1;
	}
}
