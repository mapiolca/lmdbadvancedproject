<?php
/* Copyright (C) 2004-2018 Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019 Nicolas ZABOURI         <info@inovea-conseil.com>
 * Copyright (C) 2019-2020 Frederic France         <frederic.france@netlogic.fr>
 * Copyright (C) 2022      SuperAdmin
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \defgroup   lmdbadvancedproject     Module Advanced Project
 * \brief      Advanced Project module descriptor.
 *
 * \file       htdocs/lmdbadvancedproject/core/modules/modLmdbAdvancedProject.class.php
 * \ingroup    lmdbadvancedproject
 * \brief      Description and activation file for module Advanced Project
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module Advanced Project.
 */
class modLmdbAdvancedProject extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		$this->numero = 450021;
		$this->rights_class = 'lmdbadvancedproject';
		$this->family = 'Les Métiers du Bâtiment';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'ModuleLmdbAdvancedProjectDesc';
		$this->descriptionlong = 'ModuleLmdbAdvancedProjectDesc';

		$this->editor_name = 'Les Métiers du Bâtiment';
		$this->editor_url = 'https://lesmetiersdubatiment.fr';
		$this->editor_email = 'developpeur@lesmetiersdubatiment.fr';

		$this->version = '1.2.1';
		$this->const_name = 'MAIN_MODULE_LMDBADVANCEDPROJECT';
		$this->picto = 'project';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 1,
			'printing' => 0,
			'theme' => 0,
			'css' => array(
				'/lmdbadvancedproject/css/budgetreport.css.php',
			),
			'js' => array(),
			'hooks' => array('data' => array(
				'invoicesuppliercard',
				'invoicecard',
				'projectoverview',
				'projectOverview',
				'projectcard',
				'projectCard',
			), 'entity' => '0'),
			'moduleforexternal' => 0,
		);

		$this->dirs = array('/lmdbadvancedproject/temp');
		$this->config_page_url = array('setup.php@lmdbadvancedproject');

		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array('lmdbadvancedproject@lmdbadvancedproject');
		$this->phpmin = array(8, 0);
		$this->need_dolibarr_version = array(20, 0);
		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();
		$this->const = array(
			array('LMDBADVANCEDPROJECT_ENABLE_SUPPLIER_INVOICE_SPLIT', 'chaine', 0, 'Enable project breakdown on supplier invoice lines', 0, 'current', 0),
			array('LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT', 'chaine', 0, 'Enable project breakdown on customer invoice lines', 0, 'current', 0),
		);

		if (empty($conf->lmdbadvancedproject) || !is_object($conf->lmdbadvancedproject)) {
			$conf->lmdbadvancedproject = new stdClass();
		}
		if (!isset($conf->lmdbadvancedproject->enabled)) {
			$conf->lmdbadvancedproject->enabled = 0;
		}

		$this->tabs = array();
		$this->tabs[] = array(
			'data' => 'project:+budgetreport:BudgetReportProjectTab:lmdbadvancedproject@lmdbadvancedproject:$user->rights->lmdbadvancedproject->budgetreport->read:/lmdbadvancedproject/tabs/project_budgetreport.php?id=__ID__',
		);

		$this->dictionaries = array();
		if (!isModEnabled('dynamicsprices')) {
			$commercialCategoryHasEntity = $this->tableExists(MAIN_DB_PREFIX."c_commercial_category") && $this->columnExists(MAIN_DB_PREFIX."c_commercial_category", 'entity');
			$commercialCategorySelectSql = $commercialCategoryHasEntity
				? 'SELECT t.rowid as rowid, t.entity, t.code, t.label, t.active FROM '.MAIN_DB_PREFIX.'c_commercial_category AS t WHERE t.entity = '.((int) $conf->entity)
				: 'SELECT t.rowid as rowid, t.code, t.label, t.active FROM '.MAIN_DB_PREFIX.'c_commercial_category AS t';
			$commercialCategoryFieldValue = $commercialCategoryHasEntity ? 'code,entity,label' : 'code,label';
			$commercialCategoryHelp = array(
				'code' => is_object($langs) ? $langs->trans('LMDB_CodeTooltipHelp') : 'LMDB_CodeTooltipHelp',
				'entity' => is_object($langs) ? $langs->trans('LMDB_ENtityTooltipHelp') : 'LMDB_ENtityTooltipHelp',
				'label' => is_object($langs) ? $langs->trans('LMDB_LabelTooltipHelp') : 'LMDB_LabelTooltipHelp',
				'active' => is_object($langs) ? $langs->trans('LMDB_ActiveTooltipHelp') : 'LMDB_ActiveTooltipHelp',
			);

			$this->dictionaries = array(
				'langs' => 'lmdbadvancedproject@lmdbadvancedproject',
				'tabname' => array(MAIN_DB_PREFIX."c_commercial_category"),
				'tablib' => array('LMDB_commercialcategories'),
				'tabsql' => array($commercialCategorySelectSql),
				'tabsqlsort' => array('label ASC'),
				'tabfield' => array('code,label'),
				'tabfieldvalue' => array($commercialCategoryFieldValue),
				'tabfieldinsert' => array($commercialCategoryFieldValue),
				'tabrowid' => array('rowid'),
				'tabcond' => array(!empty($conf->lmdbadvancedproject->enabled)),
				'tabhelp' => array($commercialCategoryHelp),
			);
		}
		$this->boxes = array();
		$this->cronjobs = array();

		$this->rights = array();
		$r = 0;
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'ReadBudgetReport';
		$this->rights[$r][4] = 'budgetreport';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'ReadInvoiceBreakdown';
		$this->rights[$r][4] = 'split';
		$this->rights[$r][5] = 'read';
		$r++;
		$this->rights[$r][0] = $this->numero * 100 + $r;
		$this->rights[$r][1] = 'WriteInvoiceBreakdown';
		$this->rights[$r][4] = 'split';
		$this->rights[$r][5] = 'write';

		$this->menu = array();
		$r = 0;
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=project,fk_leftmenu=projects',
			'mainmenu' => 'project',
			'leftmenu' => 'budgetreport',
			'type' => 'left',
			'titre' => 'BudgetReportArea',
			'prefix' => img_picto('', '', 'class="fas fa-chart-pie paddingright pictofixedwidth valignmiddle"'),
			'url' => '/lmdbadvancedproject/budgetreportindex.php',
			'langs' => 'lmdbadvancedproject@lmdbadvancedproject',
			'position' => '9',
			'enabled' => '$conf->lmdbadvancedproject->enabled',
			'perms' => '$user->rights->lmdbadvancedproject->budgetreport->read',
			'target' => '',
			'user' => 0,
		);
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param  string $options Options when enabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/lmdbadvancedproject/sql/');
		if ($result < 0) {
			return -1;
		}

		$result = $this->ensureCommercialCategoryExtraFields();
		if ($result < 0) {
			return -1;
		}

		$this->remove($options);

		return $this->_init(array(), $options);
	}

	/**
	 * Check if a table exists.
	 *
	 * @param  string $tableName Full table name
	 * @return bool
	 */
	private function tableExists($tableName)
	{
		$sql = "SHOW TABLES LIKE '".$this->db->escape($tableName)."'";
		$resql = $this->db->query($sql);

		return ($resql && $this->db->num_rows($resql) > 0);
	}

	/**
	 * Check if a column exists on a table.
	 *
	 * @param  string $tableName  Full table name
	 * @param  string $columnName Column name
	 * @return bool
	 */
	private function columnExists($tableName, $columnName)
	{
		$sql = "SHOW COLUMNS FROM ".$tableName." LIKE '".$this->db->escape($columnName)."'";
		$resql = $this->db->query($sql);

		return ($resql && $this->db->num_rows($resql) > 0);
	}

	/**
	 * Create commercial category extrafields when they do not exist yet.
	 *
	 * @return int 1 if OK, <0 if KO
	 */
	private function ensureCommercialCategoryExtraFields()
	{
		include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

		$extrafields = new ExtraFields($this->db);
		$param = array('options' => array('c_commercial_category:label:rowid::(active:=:1)' => null));
		$fields = array(
			'product' => array(
				'pos' => 100,
				'enabled' => '$conf->lmdbadvancedproject->enabled',
			),
			'propaldet' => array(
				'pos' => 100,
				'enabled' => '$conf->lmdbadvancedproject->enabled',
			),
			'commandedet' => array(
				'pos' => 100,
				'enabled' => '$conf->lmdbadvancedproject->enabled',
			),
			'facturedet' => array(
				'pos' => 100,
				'enabled' => '$conf->lmdbadvancedproject->enabled',
			),
			'commande_fournisseurdet' => array(
				'pos' => 100,
				'enabled' => '$conf->lmdbadvancedproject->enabled',
			),
			'facture_fourn_det' => array(
				'pos' => 100,
				'enabled' => '$conf->lmdbadvancedproject->enabled',
			),
		);

		foreach ($fields as $element => $field) {
			$extrafields->fetch_name_optionals_label($element);
			if (!empty($extrafields->attributes[$element]['label']['lmdb_commercial_category'])) {
				$result = $this->repairCommercialCategoryExtraField($element, $field['enabled'], $param);
				if ($result < 0) {
					return -1;
				}
				continue;
			}

			$result = $extrafields->addExtraField(
				'lmdb_commercial_category',
				'LMDB_CommercialCategoryExtrafield',
				'sellist',
				$field['pos'],
				255,
				$element,
				0,
				0,
				'',
				$param,
				1,
				'',
				-1,
				'',
				'',
				0,
				'lmdbadvancedproject@lmdbadvancedproject',
				$field['enabled'],
				0,
				0
			);
			if ($result < 0) {
				$this->error = $extrafields->error;
				$this->errors = $extrafields->errors;
				return -1;
			}
		}

		return 1;
	}

	/**
	 * Repair the definition of an existing commercial category extrafield.
	 *
	 * @param  string                    $element Element type
	 * @param  string                    $enabled Enabled expression
	 * @param  array<string,array<mixed>> $param   Extra field parameters
	 * @return int             1 if OK, <0 if KO
	 */
	private function repairCommercialCategoryExtraField($element, $enabled, $param)
	{
		$serializedParam = serialize($param);

		$sql = "UPDATE ".MAIN_DB_PREFIX."extrafields";
		$sql .= " SET enabled = '".$this->db->escape($enabled)."',";
		$sql .= " param = '".$this->db->escape($serializedParam)."'";
		$sql .= " WHERE name = 'lmdb_commercial_category'";
		$sql .= " AND elementtype = '".$this->db->escape($element)."'";
		$sql .= " AND (enabled IS NULL OR enabled <> '".$this->db->escape($enabled)."'";
		$sql .= " OR param IS NULL OR param <> '".$this->db->escape($serializedParam)."')";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		return 1;
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param  string $options Options when disabling module
	 * @return int             1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}
