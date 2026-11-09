<?php
/* Behavioral diagnostics with native objects and an isolated SQLite fixture. */
require __DIR__.'/projectautoclose_review_test.php';
// Native Translate searches module roots, not a direct langs directory.
$langs->dir[] = dirname(__DIR__, 2);
$langs->load('lmdbadvancedproject@lmdbadvancedproject', 0, 0, '', 1, 1);
assertClosure($langs->transnoentities('ProjectAutoCloseDiagnosticOrdersEmpty') !== 'ProjectAutoCloseDiagnosticOrdersEmpty', true, 'Real module translations loaded');

resetClosure();
$db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET paye=0');
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_statut=1');
assertClosure($job->closeProject(1, $actor), 0, 'Incomplete documents leave project open');
assertClosure(array_keys($job->diagnosticReasons), array('invoices_incomplete', 'orders_incomplete'), 'Both document branches explain their blockers');
resetClosure('either');
$db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET paye=0');
addActivity($recent);
assertClosure($job->closeProject(1, $actor), 0, 'Satisfied OR still checks activity');
assertClosure(array_keys($job->diagnosticReasons), array('activity_recent'), 'Optional invoice failure is not reported as an OR blocker');
resetClosure();
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'facture');
assertClosure($job->closeProject(1, $actor), 0, 'Empty invoice set blocks AND');
assertClosure(array_keys($job->diagnosticReasons), array('invoices_empty'), 'Missing invoices distinguished from unpaid invoices');
resetClosure();
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'commande');
assertClosure($job->closeProject(1, $actor), 0, 'Empty order set blocks AND');
assertClosure(array_keys($job->diagnosticReasons), array('orders_empty'), 'Missing orders distinguished from undelivered orders');
resetClosure(); addActivity($old, null, $old, 2);
assertClosure($job->closeProject(1, $actor), 0, 'Hidden Agenda blocks');
assertClosure(array_keys($job->diagnosticReasons), array('agenda_access'), 'Hidden event has a generic diagnostic');
resetClosure(); addActivity($old);
$db->query('UPDATE '.MAIN_DB_PREFIX.'actioncomm SET percent=50');
assertClosure($job->closeProject(1, $actor), 0, 'Pending event blocks');
assertClosure(array_keys($job->diagnosticReasons), array('agenda_unfinished'), 'Pending event diagnostic');
resetClosure(); addActivity(null);
assertClosure($job->closeProject(1, $actor), 0, 'Unknown date blocks');
assertClosure(array_keys($job->diagnosticReasons), array('agenda_invalid_dates'), 'Unknown date diagnostic');
resetClosure(); addActivity($old);
$db->query("UPDATE ".MAIN_DB_PREFIX."actioncomm SET recurrule='FREQ=WEEKLY'");
assertClosure($job->closeProject(1, $actor), 0, 'Unbounded recurrence blocks');
assertClosure(array_keys($job->diagnosticReasons), array('agenda_recurrence'), 'Recurrence diagnostic');
resetClosure();
fixtureInsert('lmdbadvancedproject_customer_invoice_parts', array('rowid'=>1,'entity'=>1,'fk_projet'=>1,'fk_facture'=>999,'fk_facture_det'=>999,'qty'=>1));
assertClosure($job->closeProject(1, $actor), 0, 'Dangling allocation blocks');
assertClosure(array_keys($job->diagnosticReasons), array('invoice_allocation'), 'Allocation diagnostic');
resetClosure();
$db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET entity=2');
assertClosure($job->closeProject(1, $actor), 0, 'Hidden invoice blocks');
assertClosure(array_keys($job->diagnosticReasons), array('invoices_access'), 'Hidden invoice diagnostic does not identify document');
resetClosure();
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET entity=2');
assertClosure($job->closeProject(1, $actor), 0, 'Hidden order blocks');
assertClosure(array_keys($job->diagnosticReasons), array('orders_access'), 'Hidden order diagnostic');

// Exercise multiple native pages and the actual zero-closure result that prompted this change.
resetClosure('invoices');
$db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET paye=0');
$db->query("UPDATE ".MAIN_DB_PREFIX."projet SET ref='<script>alert(1)</script>' WHERE rowid=1");
for ($id=2; $id<=270; $id++) {
	fixtureInsert('projet', array('rowid'=>$id,'entity'=>1,'fk_statut'=>1,'public'=>1,'datec'=>$old,'dateo'=>$old,'ref'=>'TEST'.$id));
	fixtureInsert('facture', array('rowid'=>$id,'entity'=>1,'fk_projet'=>$id,'fk_soc'=>1,'fk_statut'=>1,'paye'=>0));
}
fixtureInsert('projet', array('rowid'=>271,'entity'=>2,'fk_statut'=>1,'public'=>1,'ref'=>'OTHER-ENTITY'));
assertClosure($job->run(), 0, '270 exclusions are successful decisions, not a technical failure');
assertClosure(strpos($job->output, $langs->trans('ProjectAutoCloseResult',270,0)) === 0, true, 'Native counters preserved at start');
assertClosure(substr_count($job->output, "\n"), 271, 'Context plus one diagnostic for each of 270 projects');
assertClosure(strpos($job->output, 'TEST270') !== false, true, 'Last project retained after pagination');
assertClosure(strpos($job->output, 'OTHER-ENTITY') === false, true, 'Other entity never disclosed');
assertClosure(strpos($job->output, '<script>') === false, true, 'References escaped for native cron HTML output');
assertClosure(strpos($job->output, '&lt;script&gt;') !== false, true, 'Escaped reference still identifiable');
assertClosure(strlen($job->output) < 65535, true, '270 concise diagnostics fit native output storage');
assertClosure((int) fixtureValue('fk_statut','projet',270), 1, 'Diagnostic never overrides eligibility');
$englishLangs = $langs;
$langs = new Translate(DOL_DOCUMENT_ROOT, $conf);
$langs->dir[] = dirname(__DIR__, 2);
$langs->setDefaultLang('fr_FR');
assertClosure($job->run(), 0, 'French diagnostic runs with native translation loading');
assertClosure(strpos(html_entity_decode($job->output, ENT_QUOTES, 'UTF-8'), 'écarté') !== false, true, 'French explanation translated with accents');
assertClosure(strpos($job->output, '&amp;eacute;') === false, true, 'French text is not double escaped');
assertClosure(strlen($job->output) < 65535, true, '270 French diagnostics fit native output storage');
$langs = $englishLangs;

resetClosure();
assertClosure($job->run(), 0, 'Eligible project still closes');
assertClosure($job->diagnosticReasons, array(), 'Previous project reasons cleared');
assertClosure($job->output, $langs->trans('ProjectAutoCloseResult',1,1), 'Previous run diagnostic output cleared');

resetClosure(); $db->failSql = 'SAVEPOINT lmdbap_close_';
assertClosure($job->run(), -1, 'Technical failure remains an error');
assertClosure(strpos($job->output, dol_escape_htmltag($langs->transnoentities('ProjectAutoCloseDiagnosticFailure',1))) !== false, true, 'Technical failure identifies affected project');
assertClosure((int) fixtureValue('fk_statut','projet',1), 1, 'Technical failure does not announce a closure');
// Truly document-free projects use inactivity in all four active modes.
foreach (array('invoices','orders','both','either') as $mode) {
	foreach (array('recent','pending','unknown','old') as $activity) {
		resetClosure($mode);
		$db->query('DELETE FROM '.MAIN_DB_PREFIX.'facture');
		$db->query('DELETE FROM '.MAIN_DB_PREFIX.'commande');
		if ($activity === 'recent') { addActivity($recent); }
		if ($activity === 'pending') { addActivity($old); $db->query('UPDATE '.MAIN_DB_PREFIX.'actioncomm SET percent=50'); }
		if ($activity === 'unknown') { addActivity(null); }
		assertClosure($job->closeProject(1,$actor), $activity === 'old' ? 1 : 0, 'Document-free '.$mode.' with '.$activity.' activity');
		assertClosure(isset($job->diagnosticReasons['invoices_empty']) || isset($job->diagnosticReasons['orders_empty']), false, 'Document absence is not a blocker for empty projects');
	}
}
foreach (array('invoices','orders') as $mode) {
	resetClosure($mode);
	$db->query('DELETE FROM '.MAIN_DB_PREFIX.($mode === 'invoices' ? 'facture' : 'commande'));
	assertClosure($job->closeProject(1,$actor),0,'Missing selected family does not bypass the rule when another family exists: '.$mode);
	$db->query('UPDATE '.MAIN_DB_PREFIX.($mode === 'invoices' ? 'commande' : 'facture').' SET entity=2');
	assertClosure($job->closeProject(1,$actor),0,'Hidden opposite family is not absence: '.$mode);
}
resetClosure('invoices');
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'facture');
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_statut=0');
assertClosure($job->closeProject(1,$actor),0,'Opposite draft is not absence');
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_statut=-1');
assertClosure($job->closeProject(1,$actor),0,'Opposite canceled order is not absence');
resetClosure('orders');
$db->query('DELETE FROM '.MAIN_DB_PREFIX.'commande');
$db->query("UPDATE ".MAIN_DB_PREFIX."facture SET fk_statut=3,close_code='replaced'");
assertClosure($job->closeProject(1,$actor),0,'Opposite replaced invoice is not absence');
foreach (array(0,1) as $quantity) {
	resetClosure('orders');
	$db->query('DELETE FROM '.MAIN_DB_PREFIX.'facture'); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'commande');
	fixtureInsert('lmdbadvancedproject_customer_invoice_parts',array('rowid'=>1,'entity'=>1,'fk_projet'=>1,'fk_facture'=>999,'fk_facture_det'=>999,'qty'=>$quantity));
	assertClosure($job->closeProject(1,$actor),$quantity ? 0 : 1,'Only nonzero orphan allocations prevent document-free closure');
}
resetClosure(); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'facture'); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'commande');
$actor->denied=array('projet.creer');
assertClosure($job->closeProject(1,$actor),0,'Empty project still requires native write permission');
resetClosure(); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'facture'); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'commande');
$db->query('UPDATE '.MAIN_DB_PREFIX.'projet SET entity=2');
assertClosure($job->closeProject(1,$actor),0,'Empty project in another entity remains inaccessible');
resetClosure(); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'facture'); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'commande');
$db->failSql='SELECT rowid FROM '.MAIN_DB_PREFIX.'commande WHERE fk_projet';
assertClosure($job->run(),-1,'Failure to prove absence is a technical error');
assertClosure((int)fixtureValue('fk_statut','projet',1),1,'Presence-check SQL failure leaves project open');
resetClosure(); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'facture'); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'commande');
addActivity($recent);
assertClosure($job->run(),0,'Empty recent project is skipped successfully');
assertClosure(strpos($job->output,dol_escape_htmltag($langs->transnoentities('ProjectAutoCloseDiagnosticEmptyProject',1,'TEST1',implode('; ',$job->diagnosticReasons)))) !== false,true,'Empty project diagnostic identifies inactivity as the blocker');
resetClosure(); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'facture'); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'commande');
assertClosure($job->run(),0,'Empty old project closes through native job');
assertClosure(strpos($job->output,dol_escape_htmltag($langs->transnoentities('ProjectAutoCloseDiagnosticEmptyClosed',1,'TEST1'))) !== false,true,'Empty closure has an explicit diagnostic');
assertClosure((int)fixtureValue('fk_user_close','projet',1),1,'Empty closure records execution actor');
assertClosure($job->run(),0,'Empty closure replay succeeds without duplicate effect');
assertClosure((int)$db->fetch_object($db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.'closure_events'))->n,1,'Empty closure emits exactly one native trigger');
// Mixed shrinking candidate set: pagination must also close document-free projects.
resetClosure(); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'facture'); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'commande');
for ($id=2;$id<=270;$id++) {
	fixtureInsert('projet',array('rowid'=>$id,'entity'=>1,'fk_statut'=>1,'public'=>1,'datec'=>$old,'dateo'=>$old,'ref'=>'EMPTY'.$id));
}
assertClosure($job->run(),0,'270 empty projects close across native keyset pages');
assertClosure(strpos($job->output,$langs->trans('ProjectAutoCloseResult',270,270))===0,true,'270 empty closures counted only after commit');
assertClosure((int)fixtureValue('fk_statut','projet',270),2,'Last empty project closed');

echo $checks.' assertions passed including per-project diagnostics ('.DOL_VERSION.').'.PHP_EOL;
