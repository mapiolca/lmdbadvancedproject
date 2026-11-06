<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/security.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once __DIR__.'/lmdbadvancedprojectcompatibility.class.php';

/** Entity-owned project closure through the native scheduled-job engine. */
class LmdbAdvancedProjectAutoclose
{
	/** @var DoliDB */
	private $db;
	/** @var int Entity assigned by the native cron engine. */
	public $entity = 0;
	/** @var string */
	public $error = '';
	/** @var string[] */
	public $errors = array();
	/** @var string Native scheduled-job result. */
	public $output = '';

	/** @param DoliDB $db Database handler */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Run with the explicitly configured actor, whose rights are loaded in this
	 * entity. The scheduler's web session or CLI login never supplies authority.
	 *
	 * @return int 0 on success (native cron contract), -1 on failure
	 */
	public function run()
	{
		global $conf, $langs;
		$this->error = $this->output = '';
		$this->errors = array();
		$langs->loadLangs(array('projects', 'lmdbadvancedproject@lmdbadvancedproject'));
		$rule = getDolGlobalString('LMDBADVANCEDPROJECT_AUTO_CLOSE_RULE', 'disabled');
		if ($rule === 'disabled' || !isModEnabled('lmdbadvancedproject')) {
			$this->output = $langs->trans('ProjectAutoCloseDisabled');
			return 0;
		}
		if (!LmdbAdvancedProjectCompatibility::projectAutocloseAvailable()
			|| ($this->entity > 0 && (int) $this->entity !== (int) $conf->entity)) {
			$this->error = $langs->trans('ProjectAutoCloseUnavailable');
			return -1;
		}

		$actor = new User($this->db);
		$actorId = getDolGlobalInt('LMDBADVANCEDPROJECT_AUTO_CLOSE_USER');
		if ($actorId <= 0 || $actor->fetch($actorId) <= 0 || !$actor->statut || $actor->socid > 0
			|| !checkUserAccessToObject($actor, array('user'), $actor, 'user')) {
			$this->error = $langs->trans('ProjectAutoCloseUserRequired');
			return -1;
		}
		$actor->loadRights('', 1);
		if (!$actor->hasRight('lmdbadvancedproject', 'autoclose', 'run')
			|| !$actor->hasRight('projet', 'lire') || !$actor->hasRight('projet', 'creer')
			|| (!$actor->hasRight('agenda', 'myactions', 'read') && !$actor->hasRight('agenda', 'allactions', 'read'))
			|| ($rule !== 'orders' && (!$actor->hasRight('facture', 'lire')
				|| (getDolGlobalInt('LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT') && !$actor->hasRight('lmdbadvancedproject', 'split', 'read'))))
			|| ($rule !== 'invoices' && !$actor->hasRight('commande', 'lire'))) {
			$this->error = $langs->trans('ProjectAutoClosePermissionsRequired');
			return -1;
		}

		$cursor = $scanned = $closed = 0;
		try {
			$projectScope = new Project($this->db);
			$authorized = $projectScope->getProjectsAuthorizedForUser($actor, $actor->hasRight('projet', 'all', 'lire') ? 2 : 0, 1);
			if ($authorized === '') { $authorized = '0'; }
			if (!is_string($authorized) || !preg_match('/^[0-9]+(?:,[0-9]+)*$/D', $authorized)) {
				throw new RuntimeException('Invalid native project access scope');
			}
			do {
				// Keyset pagination remains stable as projects leave the open set.
				$sql = 'SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE entity = '.((int) $conf->entity)
					.' AND rowid IN ('.$this->db->sanitize($authorized).')'
					.' AND fk_statut = '.Project::STATUS_VALIDATED.' AND rowid > '.$cursor.' ORDER BY rowid ASC'.$this->db->plimit(100);
				$result = $this->db->query($sql);
				if (!$result) { throw new RuntimeException($this->db->lasterror()); }
				$ids = array();
				while (is_object($row = $this->db->fetch_object($result))) { $ids[] = (int) $row->rowid; }
				$this->db->free($result);
				foreach ($ids as $id) {
					$cursor = $id;
					$scanned++;
					// Only this transaction: protect empty Agenda/document ranges too,
					// including installations whose session default is READ COMMITTED.
					if (!$this->db->query('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE') || !$this->db->begin()) {
						throw new RuntimeException($this->db->lasterror());
					}
					try {
						$changed = $this->closeProject($id, $actor);
						if (!$this->db->commit()) { throw new RuntimeException($this->db->lasterror()); }
					} catch (RuntimeException $exception) {
						if (!$this->db->rollback()) { dol_syslog(__METHOD__.': rollback failed', LOG_ERR); }
						throw $exception;
					}
					$closed += $changed;
				}
			} while (count($ids) === 100);
		} catch (RuntimeException $exception) {
			dol_syslog(__METHOD__.': '.$exception->getMessage(), LOG_ERR);
			$this->error = $langs->trans('ProjectAutoCloseFailed');
			$this->output = $langs->trans('ProjectAutoCloseResult', $scanned, $closed);
			return -1;
		}
		$this->output = $langs->trans('ProjectAutoCloseResult', $scanned, $closed);
		return 0;
	}

	/**
	 * Recheck under a project row lock, shared by concurrent job executions.
	 * The scheduled entry point owns the serializable outer transaction; other
	 * internal callers must provide their transaction's isolation guarantees.
	 * An inaccessible document makes the selected rule unknown, never complete.
	 *
	 * @param int $id Project ID
	 * @param User $actor Explicit actor in the current entity
	 * @param int|null $referenceTime UTC decision time; null uses the native clock
	 * @return int 1 if closed, 0 if ineligible or inaccessible
	 * @throws RuntimeException Database or native closure failure
	 */
	public function closeProject(int $id, User $actor, ?int $referenceTime = null): int
	{
		global $conf;
		$referenceTime = $referenceTime ?? dol_now();
		$rule = getDolGlobalString('LMDBADVANCEDPROJECT_AUTO_CLOSE_RULE', 'disabled');
		if ($id <= 0 || $rule === 'disabled' || !LmdbAdvancedProjectCompatibility::projectAutocloseAvailable()
			|| ($this->entity > 0 && (int) $this->entity !== (int) $conf->entity)
			|| $actor->id <= 0 || !$actor->statut || $actor->socid > 0
			|| !$actor->hasRight('lmdbadvancedproject', 'autoclose', 'run')
			|| !$actor->hasRight('projet', 'lire') || !$actor->hasRight('projet', 'creer')
			|| (!$actor->hasRight('agenda', 'myactions', 'read') && !$actor->hasRight('agenda', 'allactions', 'read'))
			|| ($rule !== 'orders' && (!$actor->hasRight('facture', 'lire')
				|| (getDolGlobalInt('LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT') && !$actor->hasRight('lmdbadvancedproject', 'split', 'read'))))
			|| ($rule !== 'invoices' && !$actor->hasRight('commande', 'lire'))
			|| !checkUserAccessToObject($actor, array('user'), $actor, 'user')) {
			return 0;
		}
		$savepoint = '';
		if (!$this->db->begin()) { throw new RuntimeException($this->db->lasterror()); }
		try {
			$result = $this->db->query('SELECT rowid FROM '.MAIN_DB_PREFIX.'projet WHERE rowid = '.$id
				.' AND entity = '.((int) $conf->entity).' AND fk_statut = '.Project::STATUS_VALIDATED.' FOR UPDATE');
			if (!$result) { throw new RuntimeException($this->db->lasterror()); }
			$found = is_object($this->db->fetch_object($result));
			$this->db->free($result);
			if (!$found) {
				if (!$this->db->rollback()) { throw new RuntimeException($this->db->lasterror()); }
				return 0;
			}
			$project = new Project($this->db);
			if ($project->fetch($id) <= 0) { throw new RuntimeException($project->error); }
			if ((int) $project->entity !== (int) $conf->entity
				|| $project->restrictedProjectArea($actor, 'read') <= 0 || $project->restrictedProjectArea($actor, 'write') <= 0) {
				if (!$this->db->rollback()) { throw new RuntimeException($this->db->lasterror()); }
				return 0;
			}
			$invoices = $rule === 'orders' ? false : $this->documentsComplete($project, $actor, 'invoices');
			$orders = $rule === 'invoices' ? false : $this->documentsComplete($project, $actor, 'orders');
			$eligible = $rule === 'both' ? ($invoices && $orders) : ($invoices || $orders);
			if ($invoices === null || $orders === null || !$eligible) {
				if (!$this->db->rollback()) { throw new RuntimeException($this->db->lasterror()); }
				return 0;
			}
			// Inspect current Agenda rows last, after any wait for document locks.
			if (!$this->isInactive($project, $actor, $referenceTime)) {
				if (!$this->db->rollback()) { throw new RuntimeException($this->db->lasterror()); }
				return 0;
			}
			$project->oldcopy = clone $project;
			$project->context['trigger_reason'] = 'lmdbadvancedproject_auto_close';
			$project->context['auto_close_rule'] = $rule;
			// Native nested rollback only changes its counter. Isolate native writes
			// so a caller can catch a trigger failure and still commit its own work.
			$closureSavepoint = 'lmdbap_close_'.bin2hex(random_bytes(8));
			if (!$this->db->query('SAVEPOINT '.$closureSavepoint)) { throw new RuntimeException($this->db->lasterror()); }
			$savepoint = $closureSavepoint;
			// Native PROJECT_CLOSE owns its Agenda/notification effects. No custom trigger.
			if ($project->setClose($actor) < 0) { throw new RuntimeException($project->error); }
			if (!$this->db->query('RELEASE SAVEPOINT '.$savepoint)) { throw new RuntimeException($this->db->lasterror()); }
			$savepoint = '';
			// Preserve native nesting; run() also checks its final persistence commit.
			if (!$this->db->commit()) { throw new RuntimeException($this->db->lasterror()); }
			return 1;
		} catch (RuntimeException $exception) {
			$savepointRollbackFailed = false;
			if ($savepoint !== '') {
				if (!$this->db->query('ROLLBACK TO SAVEPOINT '.$savepoint)) {
					$savepointRollbackFailed = true;
					dol_syslog(__METHOD__.': savepoint rollback failed', LOG_ERR);
				} elseif (!$this->db->query('RELEASE SAVEPOINT '.$savepoint)) {
					dol_syslog(__METHOD__.': savepoint release failed', LOG_ERR);
				}
			}
			// A nested native rollback cannot abort the caller's transaction.
			// If local recovery fails, abort all SQL writes before control returns.
			if ($savepointRollbackFailed && !$this->db->query('ROLLBACK')) {
				$connectionClosed = $this->db->close();
				throw new RuntimeException('Automatic closure rollback failed; database connection '.($connectionClosed ? 'closed' : 'unusable'), 0, $exception);
			}
			if (!$this->db->rollback()) { dol_syslog(__METHOD__.': rollback failed', LOG_ERR); }
			if ($savepointRollbackFailed) {
				throw new RuntimeException('Automatic closure rollback failed; entire caller transaction rolled back', 0, $exception);
			}
			throw $exception;
		}
	}

	/**
	 * Agenda occurrence/end and recording/modification dates prevent premature
	 * closure. Outstanding actions and active recurrences are still activity.
	 * Project edits/reopening also start a complete observation period.
	 *
	 * @param Project $project Loaded owner project
	 * @param User $actor Explicit actor
	 * @param int $referenceTime UTC decision time
	 * @return bool Whether the complete project Agenda has been inactive long enough
	 * @throws RuntimeException Database failure
	 */
	private function isInactive(Project $project, User $actor, int $referenceTime): bool
	{
		// An inactivity day is 24 elapsed hours, including across DST changes.
		$cutoff = dol_time_plus_duree($referenceTime, -24 * getDolGlobalInt('LMDBADVANCEDPROJECT_AUTO_CLOSE_DAYS', 30), 'h');
		$last = max((int) $project->date_c, (int) $project->date_start, (int) $project->date_m);
		$taskLink = "(a.elementtype IN ('project_task', 'project_task@projet') AND a.fk_element = t.rowid)";
		if (version_compare(DOL_VERSION, '21.0.0', '>=')) {
			$taskLink .= ' OR a.fk_task = t.rowid';
		}
		$link = '(a.fk_project = '.((int) $project->id)
			." OR (a.elementtype IN ('project', 'project@projet') AND a.fk_element = ".((int) $project->id).')'
			.' OR EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'projet_task t WHERE t.fk_projet = '.((int) $project->id)
			.' AND ('.$taskLink.')))';
		$entities = $this->db->sanitize(getEntity('agenda'));
		$result = $this->db->query('SELECT a.id FROM '.MAIN_DB_PREFIX.'actioncomm a WHERE '.$link
			.' AND a.entity NOT IN ('.$entities.')'.$this->db->plimit(1));
		if (!$result) { throw new RuntimeException($this->db->lasterror()); }
		$hidden = is_object($this->db->fetch_object($result));
		$this->db->free($result);
		if ($hidden) { return false; }
		$result = $this->db->query('SELECT a.id, a.datep, a.datep2, a.datec, a.tms, a.percent, a.fulldayevent, a.recurrule, a.recurdateend'
			.' FROM '.MAIN_DB_PREFIX.'actioncomm a WHERE '
			.$link.' AND a.entity IN ('.$entities.') ORDER BY a.id FOR UPDATE');
		if (!$result) { throw new RuntimeException($this->db->lasterror()); }
		while (is_object($row = $this->db->fetch_object($result))) {
			if (!checkUserAccessToObject($actor, array('agenda'), (int) $row->id, 'actioncomm&societe', '', 'fk_soc', 'id')) {
				$this->db->free($result); return false;
			}
			$start = (int) $this->db->jdate($row->datep);
			$end = (int) $this->db->jdate($row->datep2);
			// -1 = native automatic/history event, 100 = completed action.
			if ($start <= 0 || $row->percent === null || !in_array((int) $row->percent, array(-1, 100), true)
				|| ($end > 0 && $end < $start) || ($row->fulldayevent && $end <= 0)) {
				$this->db->free($result); return false;
			}
			$recurrenceEnd = 0;
			if (trim((string) $row->recurrule) !== '') {
				$recurrenceEnd = (int) $this->db->jdate($row->recurdateend);
				if ($recurrenceEnd <= 0) { $this->db->free($result); return false; }
			}
			$eventDate = max($start, $end, $recurrenceEnd, (int) $this->db->jdate($row->datec), (int) $this->db->jdate($row->tms));
			$last = max($last, $eventDate);
		}
		$this->db->free($result);
		return $last > 0 && $last <= $cutoff;
	}

	/**
	 * Inspect all persisted links, including drafts and historical allocations.
	 * Never drop an out-of-scope pending document from the completeness test.
	 *
	 * @param Project $project Loaded owner project
	 * @param User $actor Explicit actor
	 * @param string $kind Internal value: invoices or orders
	 * @return bool|null Complete nonempty set, incomplete/empty set, or inaccessible
	 * @throws RuntimeException Database failure
	 */
	private function documentsComplete(Project $project, User $actor, string $kind): ?bool
	{
		$invoice = $kind === 'invoices';
		$table = $invoice ? 'facture' : 'commande';
		$entities = $this->db->sanitize(getEntity($table));
		$link = 'd.fk_projet = '.((int) $project->id);
		if ($invoice) {
			// The editing switch does not erase persisted debts or repair broken links.
			$parts = $this->db->query('SELECT cip.rowid, f.rowid AS invoice_id, fd.rowid AS line_id,'
				.' CASE WHEN cip.entity = f.entity AND cip.entity IN ('.$entities.') THEN 1 ELSE 0 END AS visible_entity'
				.' FROM '.MAIN_DB_PREFIX.'lmdbadvancedproject_customer_invoice_parts cip'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'facture f ON f.rowid = cip.fk_facture'
				.' LEFT JOIN '.MAIN_DB_PREFIX.'facturedet fd ON fd.rowid = cip.fk_facture_det AND fd.fk_facture = cip.fk_facture'
				.' WHERE cip.fk_projet = '.((int) $project->id).' AND (cip.total_ht <> 0 OR cip.total_ttc <> 0 OR cip.qty <> 0)'
				.' ORDER BY cip.rowid FOR UPDATE');
			if (!$parts) { throw new RuntimeException($this->db->lasterror()); }
			while (is_object($part = $this->db->fetch_object($parts))) {
				if (!$actor->hasRight('lmdbadvancedproject', 'split', 'read') || !$part->invoice_id || !$part->line_id || !$part->visible_entity) {
					$this->db->free($parts); return null;
				}
			}
			$this->db->free($parts);
			$link = '('.$link.' OR EXISTS (SELECT 1 FROM '.MAIN_DB_PREFIX.'lmdbadvancedproject_customer_invoice_parts cip'
				.' INNER JOIN '.MAIN_DB_PREFIX.'facturedet fd ON fd.rowid = cip.fk_facture_det AND fd.fk_facture = cip.fk_facture'
				.' WHERE cip.fk_facture = d.rowid AND cip.fk_projet = '.((int) $project->id)
				.' AND cip.entity = d.entity AND (cip.total_ht <> 0 OR cip.total_ttc <> 0 OR cip.qty <> 0)))';
		}
		// Replaced invoices are superseded; other abandoned unpaid invoices still block.
		$active = $invoice ? "NOT (d.fk_statut = ".Facture::STATUS_ABANDONED." AND COALESCE(d.close_code, '') = '".Facture::CLOSECODE_REPLACED."')"
			: 'd.fk_statut <> '.Commande::STATUS_CANCELED;
		$result = $this->db->query('SELECT d.rowid FROM '.MAIN_DB_PREFIX.$table.' d WHERE '.$link.' AND '.$active
			.' AND d.entity NOT IN ('.$entities.')'.$this->db->plimit(1));
		if (!$result) { throw new RuntimeException($this->db->lasterror()); }
		$hidden = is_object($this->db->fetch_object($result));
		$this->db->free($result);
		if ($hidden) { return null; }
		$sql = 'SELECT d.rowid, d.fk_soc, d.fk_statut, s.rowid AS parent_id'.($invoice ? ', d.paye' : '')
			.' FROM '.MAIN_DB_PREFIX.$table.' d LEFT JOIN '.MAIN_DB_PREFIX.'societe s ON s.rowid = d.fk_soc'
			.' AND s.entity IN ('.$this->db->sanitize(getEntity('societe')).')'
			.' WHERE '.$link.' AND '.$active.' AND d.entity IN ('.$entities.') ORDER BY d.rowid FOR UPDATE';
		$result = $this->db->query($sql);
		if (!$result) { throw new RuntimeException($this->db->lasterror()); }
		$count = 0;
		$complete = true;
		while (is_object($row = $this->db->fetch_object($result))) {
			$count++;
			if (((int) $row->fk_soc > 0 && !$row->parent_id)
				|| !checkUserAccessToObject($actor, array($table), (int) $row->rowid, $table, '', 'fk_soc')
				|| ((int) $row->fk_soc > 0 && !checkUserAccessToObject($actor, array('societe'), (int) $row->fk_soc, 'societe'))) {
				$this->db->free($result);
				return null;
			}
			if ($invoice ? ((int) $row->paye !== 1 || (int) $row->fk_statut !== Facture::STATUS_CLOSED)
				: (int) $row->fk_statut !== Commande::STATUS_CLOSED) {
				$complete = false;
			}
		}
		$this->db->free($result);
		return $count > 0 && $complete;
	}
}
