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
 * \file    lmdbadvancedproject/class/lmdbadvancedprojectworkflow.class.php
 * \ingroup lmdbadvancedproject
 * \brief   Workflow helpers for Advanced Project.
 */

require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/task.class.php';

/**
 * Workflow helpers for Advanced Project.
 */
class LmdbAdvancedProjectWorkflow
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
	 * Close a project and its tasks when all linked customer orders are delivered.
	 *
	 * @param  Commande $order Customer order that triggered the workflow
	 * @param  User     $user  User that closed the order
	 * @return int             <0 if KO, 0 if nothing done, >0 if project workflow ran
	 */
	public function closeProjectWhenAllLinkedOrdersDelivered($order, $user)
	{
		global $conf;

		if (!$this->isWorkflowEnabled($conf)) {
			return 0;
		}

		$projectId = $this->getProjectIdFromOrder($order);
		if ($projectId < 0) {
			return -1;
		}
		if ($projectId === 0) {
			return 0;
		}

		$project = new Project($this->db);
		$result = $project->fetch($projectId);
		if ($result <= 0) {
			if ($result < 0) {
				$this->setError($project->error, $project->errors);
				return -1;
			}
			return 0;
		}

		if ((int) $project->statut !== Project::STATUS_VALIDATED) {
			return 0;
		}

		$result = $this->areAllProjectOrdersDelivered($project);
		if ($result < 0) {
			return -1;
		}
		if ($result === 0) {
			return 0;
		}
		if (!$this->hasProjectWriteAccess($user)) {
			$this->setError('NotEnoughPermissions');
			return -1;
		}

		$result = $this->closeProjectTasks($project, $user);
		if ($result < 0) {
			return -1;
		}

		$result = $project->setClose($user);
		if ($result < 0) {
			$this->setError($project->error, $project->errors);
			return -1;
		}

		return 1;
	}

	/**
	 * Check if the workflow switch is enabled.
	 *
	 * @param  Conf $conf Dolibarr configuration
	 * @return bool
	 */
	private function isWorkflowEnabled($conf)
	{
		if (function_exists('isModEnabled')) {
			if (!isModEnabled('lmdbadvancedproject')) {
				return false;
			}
		} elseif (empty($conf->lmdbadvancedproject->enabled)) {
			return false;
		}

		if (function_exists('getDolGlobalInt')) {
			return getDolGlobalInt('LMDBADVANCEDPROJECT_WORKFLOW_CLOSE_PROJECT_ON_DELIVERED_ORDERS') > 0;
		}
		if (function_exists('getDolGlobalString')) {
			return !empty(getDolGlobalString('LMDBADVANCEDPROJECT_WORKFLOW_CLOSE_PROJECT_ON_DELIVERED_ORDERS'));
		}

		return !empty($conf->global->LMDBADVANCEDPROJECT_WORKFLOW_CLOSE_PROJECT_ON_DELIVERED_ORDERS);
	}

	/**
	 * Check if the current user can update projects and tasks.
	 *
	 * @param  User $user User object
	 * @return bool
	 */
	private function hasProjectWriteAccess($user)
	{
		return !empty($user->rights->projet->creer) || !empty($user->rights->projet->all->creer);
	}

	/**
	 * Get the linked project id from an order, with a DB fallback.
	 *
	 * @param  Commande $order Customer order
	 * @return int
	 */
	private function getProjectIdFromOrder($order)
	{
		if (!empty($order->fk_project)) {
			return (int) $order->fk_project;
		}
		if (!empty($order->fk_projet)) {
			return (int) $order->fk_projet;
		}
		if (empty($order->id)) {
			return 0;
		}

		$sql = "SELECT fk_projet FROM ".MAIN_DB_PREFIX."commande WHERE rowid = ".((int) $order->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$projectId = 0;
		if ($obj = $this->db->fetch_object($resql)) {
			$projectId = (int) $obj->fk_projet;
		}
		$this->db->free($resql);

		return $projectId;
	}

	/**
	 * Check if all validated customer orders linked to a project are delivered.
	 *
	 * @param  Project $project Project object
	 * @return int              <0 if KO, 0 if not eligible, 1 if eligible
	 */
	private function areAllProjectOrdersDelivered($project)
	{
		$sql = "SELECT COUNT(c.rowid) AS total_orders,";
		$sql .= " SUM(CASE WHEN c.fk_statut = ".Commande::STATUS_CLOSED." THEN 1 ELSE 0 END) AS delivered_orders";
		$sql .= " FROM ".MAIN_DB_PREFIX."commande AS c";
		$sql .= " WHERE c.fk_projet = ".((int) $project->id);
		$sql .= " AND c.fk_statut > ".Commande::STATUS_DRAFT;
		$sql .= " AND c.entity = ".((int) $project->entity);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$totalOrders = 0;
		$deliveredOrders = 0;
		if ($obj = $this->db->fetch_object($resql)) {
			$totalOrders = (int) $obj->total_orders;
			$deliveredOrders = (int) $obj->delivered_orders;
		}
		$this->db->free($resql);

		return ($totalOrders > 0 && $totalOrders === $deliveredOrders) ? 1 : 0;
	}

	/**
	 * Close all tasks attached to a project.
	 *
	 * @param  Project $project Project object
	 * @param  User    $user    User that triggered the workflow
	 * @return int              <0 if KO, >=0 if OK
	 */
	private function closeProjectTasks($project, $user)
	{
		$sql = "SELECT t.rowid";
		$sql .= " FROM ".MAIN_DB_PREFIX."projet_task AS t";
		$sql .= " WHERE t.fk_projet = ".((int) $project->id);
		$sql .= " AND t.entity = ".((int) $project->entity);
		$sql .= " AND (t.progress IS NULL OR t.progress < 100 OR t.fk_statut IS NULL OR t.fk_statut <> ".Task::STATUS_CLOSED.")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->setError($this->db->lasterror());
			return -1;
		}

		$taskIds = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$taskIds[] = (int) $obj->rowid;
		}
		$this->db->free($resql);

		foreach ($taskIds as $taskId) {
			$task = new Task($this->db);
			$result = $task->fetch($taskId);
			if ($result <= 0) {
				$this->setError($task->error, $task->errors);
				return -1;
			}

			$sql = "UPDATE ".MAIN_DB_PREFIX."projet_task";
			$sql .= " SET progress = 100, fk_statut = ".Task::STATUS_CLOSED;
			$sql .= " WHERE rowid = ".((int) $taskId);
			$sql .= " AND fk_projet = ".((int) $project->id);
			$sql .= " AND entity = ".((int) $project->entity);

			if (!$this->db->query($sql)) {
				$this->setError($this->db->lasterror());
				return -1;
			}

			$task->progress = 100;
			$task->fk_statut = Task::STATUS_CLOSED;
			$task->status = Task::STATUS_CLOSED;

			$result = $task->call_trigger('TASK_MODIFY', $user);
			if ($result < 0) {
				$this->setError($task->error, $task->errors);
				return -1;
			}
		}

		return count($taskIds);
	}

	/**
	 * Store an error on this workflow object.
	 *
	 * @param string        $error  Main error
	 * @param array<string> $errors Detailed errors
	 * @return void
	 */
	private function setError($error, $errors = array())
	{
		$this->error = $error;
		if (!empty($errors) && is_array($errors)) {
			$this->errors = $errors;
		} elseif (!empty($error)) {
			$this->errors = array($error);
		}
	}
}
