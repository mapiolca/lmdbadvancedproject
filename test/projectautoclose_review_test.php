<?php
/* Behavioral regressions identified while reviewing PR #10. */
require __DIR__.'/projectautoclose_test.php';
$reviewFailures = array();
function reviewClosure(string $label, callable $scenario): void {
	global $reviewFailures;
	try { $scenario(); }
	catch (RuntimeException $exception) { $reviewFailures[] = $label.': '.$exception->getMessage(); }
}
reviewClosure('Recent event modification', static function () use ($job, $actor, $db, $old, $recent) {
	resetClosure(); addActivity($old, $old, $old);
	$db->query("UPDATE ".MAIN_DB_PREFIX."actioncomm SET tms='".$recent."',percent=100");
	assertClosure($job->closeProject(1,$actor), 0, 'A recent Agenda edit restarts the inactivity delay');
});
foreach (array(0,50,null) as $progress) {
	reviewClosure('Unfinished or unknown Agenda status '.var_export($progress,true), static function () use ($job,$actor,$db,$old,$progress) {
		resetClosure(); addActivity($old,$old,$old);
		$db->query('UPDATE '.MAIN_DB_PREFIX.'actioncomm SET percent='.($progress === null ? 'NULL' : $progress));
		assertClosure($job->closeProject(1,$actor), 0, 'An overdue uncompleted event cannot justify closure');
	});
}
reviewClosure('Undated event', static function () use ($job,$actor,$db,$old) {
	resetClosure(); addActivity(null,null,$old);
	$db->query('UPDATE '.MAIN_DB_PREFIX.'actioncomm SET percent=100');
	assertClosure($job->closeProject(1,$actor), 0, 'Creation time does not replace an unknown occurrence date');
});
reviewClosure('Recent project modification/reopening', static function () use ($job,$actor,$db,$recent) {
	resetClosure(); $db->query("UPDATE ".MAIN_DB_PREFIX."projet SET tms='".$recent."'");
	assertClosure($job->closeProject(1,$actor), 0, 'A recently reopened project receives a complete observation period');
});
reviewClosure('Private project read scope', static function () use ($job,$actor,$db) {
	resetClosure(); $db->query('UPDATE '.MAIN_DB_PREFIX.'projet SET public=0');
	$actor->denied=array('projet.all.lire');
	assertClosure($job->closeProject(1,$actor),0,'Write-all does not replace access to read the private project');
});
reviewClosure('Scheduled private project scope', static function () use ($job,$db,$langs) {
	resetClosure(); $db->query('UPDATE '.MAIN_DB_PREFIX.'projet SET public=0');
	$db->query('DELETE FROM '.MAIN_DB_PREFIX.'user_rights WHERE fk_id=104');
	try {
		assertClosure($job->run(),0,'A job without accessible projects succeeds without a mutation');
		assertClosure((int)fixtureValue('fk_statut','projet',1),1,'Private project outside the native read scope stays open');
		assertClosure($job->output,$langs->trans('ProjectAutoCloseResult',0,0),'Counters do not reveal inaccessible projects');
	} finally { fixtureInsert('user_rights',array('entity'=>1,'fk_user'=>1,'fk_id'=>104)); }
});
reviewClosure('Unbounded recurrence', static function () use ($job,$actor,$db,$old) {
	resetClosure(); addActivity($old,$old,$old);
	$db->query("UPDATE ".MAIN_DB_PREFIX."actioncomm SET percent=-1,recurrule='FREQ=WEEKLY',recurdateend=NULL");
	assertClosure($job->closeProject(1,$actor), 0, 'An ongoing recurrence is future activity');
});
reviewClosure('Task Agenda', static function () use ($job,$actor,$db,$recent) {
	resetClosure();
	$db->query('CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX.'projet_task (rowid INTEGER PRIMARY KEY, fk_projet INTEGER)');
	fixtureInsert('projet_task',array('rowid'=>10,'fk_projet'=>1));
	addActivity($recent,$recent,$recent);
	$db->query("UPDATE ".MAIN_DB_PREFIX."actioncomm SET fk_project=NULL,elementtype='project_task',fk_element=10,percent=100");
	assertClosure($job->closeProject(1,$actor), 0, 'Activity linked through a project task is not omitted');
});
reviewClosure('Historical invoice allocation while split UI is disabled', static function () use ($job,$actor) {
	resetClosure('invoices');
	fixtureInsert('facture',array('rowid'=>2,'entity'=>1,'fk_projet'=>2,'fk_soc'=>1,'fk_statut'=>1,'paye'=>0));
	fixtureInsert('facturedet',array('rowid'=>2,'fk_facture'=>2));
	fixtureInsert('lmdbadvancedproject_customer_invoice_parts',array('rowid'=>1,'entity'=>1,'fk_projet'=>1,'fk_facture'=>2,'fk_facture_det'=>2,'qty'=>1));
	assertClosure($job->closeProject(1,$actor), 0, 'Disabling the editor must not hide a linked outstanding invoice');
});
reviewClosure('Dangling invoice allocation', static function () use ($job,$actor,$conf) {
	resetClosure('invoices'); $conf->global->LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT=1;
	fixtureInsert('lmdbadvancedproject_customer_invoice_parts',array('rowid'=>1,'entity'=>1,'fk_projet'=>1,'fk_facture'=>999,'fk_facture_det'=>999,'qty'=>1));
	assertClosure($job->closeProject(1,$actor), 0, 'A broken nonzero invoice allocation makes the rule unknown');
});
foreach (array('UTC','Europe/Paris','America/New_York') as $timezone) {
	reviewClosure('Exact cutoff in '.$timezone, static function () use ($job,$actor,$db,$conf,$timezone) {
		$savedZone = date_default_timezone_get(); $savedServerZone = $conf->global->MAIN_SERVER_TZ;
		try {
			date_default_timezone_set($timezone); $conf->global->MAIN_SERVER_TZ = $timezone;
			$reference = dol_mktime(12,30,0,11,15,2026,'gmt');
			$cutoff = $reference-30*86400; // Expected elapsed duration, independent of the helper's calendar mode.
			foreach (array(-1=>1,0=>1,1=>0) as $offset=>$expected) {
				resetClosure(); $date = $db->idate($cutoff+$offset); addActivity($date,$date,$date);
				assertClosure($job->closeProject(1,$actor,$reference), $expected, 'Full 30-day delay, offset '.$offset.' seconds in '.$timezone);
			}
		} finally { date_default_timezone_set($savedZone); $conf->global->MAIN_SERVER_TZ = $savedServerZone; }
	});
}
reviewClosure('Full elapsed day across daylight saving', static function () use ($job,$actor,$db,$conf) {
	$savedZone=date_default_timezone_get(); $savedServerZone=$conf->global->MAIN_SERVER_TZ;
	$savedMemoryMode=getDolGlobalInt('MAIN_DATE_IN_MEMORY_ARE_NOT_GMT');
	try {
		date_default_timezone_set('Europe/Paris'); $conf->global->MAIN_SERVER_TZ='Europe/Paris';
		$conf->global->MAIN_DATE_IN_MEMORY_ARE_NOT_GMT=1;
		$reference=gmmktime(10,0,0,3,29,2026);
		resetClosure(); $conf->global->LMDBADVANCEDPROJECT_AUTO_CLOSE_DAYS=1;
		$created=$db->idate($reference-60*86400);
		$db->query("UPDATE ".MAIN_DB_PREFIX."projet SET datec='".$created."',dateo='".$created."'");
		$date=$db->idate($reference-86400+1800); addActivity($date,$date,$date);
		assertClosure($job->closeProject(1,$actor,$reference),0,'23.5 elapsed hours are not a full inactivity day');
	} finally {
		date_default_timezone_set($savedZone); $conf->global->MAIN_SERVER_TZ=$savedServerZone;
		$conf->global->MAIN_DATE_IN_MEMORY_ARE_NOT_GMT=$savedMemoryMode;
	}
});
reviewClosure('Native completed event', static function () use ($job,$actor,$db,$old) {
	resetClosure(); addActivity($old,$old,$old); $db->query('UPDATE '.MAIN_DB_PREFIX.'actioncomm SET percent=100');
	assertClosure($job->closeProject(1,$actor),1,'An old completed event permits closure');
});
reviewClosure('Malformed event end and full-day event', static function () use ($job,$actor,$db,$old,$recent) {
	resetClosure(); addActivity($recent,$old,$old);
	assertClosure($job->closeProject(1,$actor),0,'An end before the start is not a completed inactivity period');
	resetClosure(); addActivity($old,null,$old); $db->query('UPDATE '.MAIN_DB_PREFIX.'actioncomm SET fulldayevent=1');
	assertClosure($job->closeProject(1,$actor),0,'A full-day event without an end date is unknown');
	resetClosure(); addActivity($old,$recent,$old); $db->query('UPDATE '.MAIN_DB_PREFIX.'actioncomm SET fulldayevent=1');
	assertClosure($job->closeProject(1,$actor),0,'A full-day event uses its end, not its old start');
});
reviewClosure('Recurrence end', static function () use ($job,$actor,$db,$old,$recent,$future) {
	foreach (array($future=>0,$recent=>0,$old=>1) as $end=>$expected) {
		resetClosure(); addActivity($old,$old,$old);
		$db->query("UPDATE ".MAIN_DB_PREFIX."actioncomm SET recurrule='FREQ=WEEKLY',recurdateend='".$end."'");
		assertClosure($job->closeProject(1,$actor),$expected,'Inactivity includes the recurrence bound '.$end);
	}
});
reviewClosure('Newest event regardless of row order', static function () use ($job,$actor,$old,$recent) {
	foreach (array(array($old,$recent),array($recent,$old)) as $dates) {
		resetClosure(); addActivity($dates[0]);
		fixtureInsert('actioncomm',array('id'=>2,'entity'=>1,'fk_project'=>1,'datep'=>$dates[1],'percent'=>100));
		assertClosure($job->closeProject(1,$actor),0,'The most recent activity always wins');
	}
});
if (version_compare(DOL_VERSION,'21.0.0','>=')) {
	reviewClosure('Native fk_task from v21', static function () use ($job,$actor,$db,$recent) {
		resetClosure(); fixtureInsert('projet_task',array('rowid'=>10,'fk_projet'=>1)); addActivity($recent);
		$db->query('UPDATE '.MAIN_DB_PREFIX.'actioncomm SET fk_project=NULL,fk_task=10');
		assertClosure($job->closeProject(1,$actor),0,'Native task-only linkage counts as project activity');
	});
}
reviewClosure('Unrelated task does not block', static function () use ($job,$actor,$db,$recent) {
	resetClosure(); fixtureInsert('projet_task',array('rowid'=>10,'fk_projet'=>2)); addActivity($recent);
	$db->query("UPDATE ".MAIN_DB_PREFIX."actioncomm SET fk_project=NULL,elementtype='project_task',fk_element=10");
	assertClosure($job->closeProject(1,$actor),1,'A different project task is not activity on this project');
});
reviewClosure('Allocated paid invoice without header link', static function () use ($job,$actor,$db) {
	resetClosure('invoices'); $db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET fk_projet=2');
	fixtureInsert('facturedet',array('rowid'=>1,'fk_facture'=>1));
	fixtureInsert('lmdbadvancedproject_customer_invoice_parts',array('rowid'=>1,'entity'=>1,'fk_projet'=>1,'fk_facture'=>1,'fk_facture_det'=>1,'qty'=>1));
	assertClosure($job->closeProject(1,$actor),1,'A paid historical allocation is a valid nonempty invoice set');
});
reviewClosure('Allocation with a missing line', static function () use ($job,$actor) {
	resetClosure('invoices');
	fixtureInsert('lmdbadvancedproject_customer_invoice_parts',array('rowid'=>1,'entity'=>1,'fk_projet'=>1,'fk_facture'=>1,'fk_facture_det'=>999,'qty'=>1));
	assertClosure($job->closeProject(1,$actor),0,'A missing allocated line prevents closure even when its invoice is paid');
});
reviewClosure('Historical allocation permission', static function () use ($job,$actor) {
	resetClosure('invoices'); $actor->denied=array('lmdbadvancedproject.split.read');
	fixtureInsert('facturedet',array('rowid'=>1,'fk_facture'=>1));
	fixtureInsert('lmdbadvancedproject_customer_invoice_parts',array('rowid'=>1,'entity'=>1,'fk_projet'=>1,'fk_facture'=>1,'fk_facture_det'=>1,'qty'=>1));
	assertClosure($job->closeProject(1,$actor),0,'Historical allocation access is required while its editor is disabled');
});
reviewClosure('Activity while waiting for documents', static function () use ($job,$actor,$db,$recent) {
	resetClosure(); $db->afterDocumentRead=static function () use ($recent) { addActivity($recent); };
	assertClosure($job->closeProject(1,$actor),0,'Agenda is checked after document reads finish');
});
reviewClosure('Scheduled isolation failure', static function () use ($job,$db) {
	resetClosure(); $db->failSql='SET TRANSACTION ISOLATION LEVEL SERIALIZABLE';
	assertClosure($job->run(),-1,'Failure to protect the transaction refuses the job');
	assertClosure((int)fixtureValue('fk_statut','projet',1),1,'Isolation setup failure does not close a project');
});
reviewClosure('Scheduled outer commit failure', static function () use ($job,$db) {
	resetClosure(); $db->failSql='COMMIT';
	assertClosure($job->run(),-1,'The job checks the final commit, not nested success');
	assertClosure((int)fixtureValue('fk_statut','projet',1),1,'Final commit failure rolls back the native closure');
	assertClosure($db->transaction_opened,0,'Scheduled transaction balance restored');
});
if ($reviewFailures) {
	fwrite(STDERR, implode(PHP_EOL,$reviewFailures).PHP_EOL);
	exit(1);
}
echo $checks.' assertions passed including PR #10 review regressions.'.PHP_EOL;
