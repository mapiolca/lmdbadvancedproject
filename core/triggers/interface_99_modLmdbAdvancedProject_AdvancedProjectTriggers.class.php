<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
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
 */

/**
 * \file    lmdbadvancedproject/core/triggers/interface_99_modLmdbAdvancedProject_AdvancedProjectTriggers.class.php
 * \ingroup lmdbadvancedproject
 * \brief   Advanced Project business triggers.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once dol_buildpath('/lmdbadvancedproject/class/lmdbadvancedprojectworkflow.class.php');

/**
 * Advanced Project business triggers.
 */
class InterfaceAdvancedProjectTriggers extends DolibarrTriggers
{
	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = 'project';
		$this->description = 'Advanced Project workflow triggers.';
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'project';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 *
	 * @param  string       $action Event action code
	 * @param  CommonObject $object Object
	 * @param  User         $user   Object user
	 * @param  Translate    $langs  Object langs
	 * @param  Conf         $conf   Object conf
	 * @return int                  <0 if KO, 0 if no trigger ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if ($action !== 'ORDER_CLOSE') {
			return 0;
		}
		if (function_exists('isModEnabled')) {
			if (!isModEnabled('lmdbadvancedproject')) {
				return 0;
			}
		} elseif (empty($conf->lmdbadvancedproject->enabled)) {
			return 0;
		}
		if (empty($object) || empty($object->element) || $object->element !== 'commande') {
			return 0;
		}

		$workflow = new LmdbAdvancedProjectWorkflow($this->db);
		$result = $workflow->closeProjectWhenAllLinkedOrdersDelivered($object, $user);
		if ($result < 0) {
			$this->error = $workflow->error;
			$this->errors = $workflow->errors;
			return -1;
		}

		return $result;
	}
}
