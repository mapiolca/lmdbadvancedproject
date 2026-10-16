<?php
/* Native Project/User/Interfaces/transaction regression tests with SQLite in memory.
 * FOR UPDATE is removed by the test adapter; this is not a MySQL concurrency test.
 */
if (PHP_SAPI !== 'cli') { exit(1); }
error_reporting(E_ALL & ~E_DEPRECATED);
define('DOL_DOCUMENT_ROOT', realpath(getenv('DOLIBARR_ROOT') ?: __DIR__.'/.cache/core20/htdocs'));
define('DOL_VERSION', getenv('DOLIBARR_VERSION') ?: '20.0.0');
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'test_project_closure_');
define('DOL_DATA_ROOT', __DIR__.'/.cache');
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
if (is_file(DOL_DOCUMENT_ROOT.'/core/lib/html.lib.php')) { require_once DOL_DOCUMENT_ROOT.'/core/lib/html.lib.php'; }
if (!defined('MODULE_MAPPING')) { define('MODULE_MAPPING', array()); }
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/db/sqlite3.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/conf.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../class/lmdbadvancedprojectautoclose.class.php';

class AutocloseDB extends DoliDBSqlite3
{
	public $failSql = '';
	public $afterDocumentRead = null;
	public function __construct($type, $host, $user, $pass, $name = '', $port = 0) {
		$this->db = new SQLite3(':memory:'); $this->transaction_opened = 0;
		$this->db->createFunction('GREATEST', static function (...$values) { return in_array(null, $values, true) ? null : max($values); });
	}
	public function query($sql, $usesavepoint = 0, $type = 'auto', $result_mode = 0) {
		if ($this->failSql !== '' && strpos($sql, $this->failSql) !== false) { $this->failSql = ''; $this->error = 'injected failure'; return false; }
		// SQLite has one serializable writer. MySQL's per-transaction SET is
		// acknowledged here; its locking semantics are not exercised by this fixture.
		if ($sql === 'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE') { $sql = 'SELECT 1'; }
		$result = parent::query(str_replace(' FOR UPDATE', '', $sql), $usesavepoint, $type, $result_mode);
		if (!$result) { throw new RuntimeException($this->error.' SQL: '.$sql); }
		if (is_object($result)) { $result->queryString = str_replace(' FOR UPDATE', '', $sql); }
		if ($this->afterDocumentRead !== null && strpos($sql, 'FROM '.MAIN_DB_PREFIX.'commande d ') !== false && strpos($sql, 'FOR UPDATE') !== false) {
			$callback = $this->afterDocumentRead; $this->afterDocumentRead = null; $callback();
		}
		return $result;
	}
}
class AutocloseActor extends User
{
	public $denied = array();
	public function hasRight($module, $level, $sublevel = '') { return !in_array(implode('.', array_filter(array($module, $level, $sublevel))), $this->denied, true); }
}
$conf = (object) array('entity' => 1, 'currency' => 'EUR', 'global' => (object) array(
	'LMDBADVANCEDPROJECT_AUTO_CLOSE_RULE' => 'both', 'LMDBADVANCEDPROJECT_AUTO_CLOSE_DAYS' => 30,
	'LMDBADVANCEDPROJECT_AUTO_CLOSE_USER' => 1, 'MAIN_SERVER_TZ' => 'UTC',
), 'modules' => array_fill_keys(array('lmdbadvancedproject', 'projet', 'project', 'facture', 'invoice', 'commande', 'order', 'agenda', 'cron', 'testclosure'), 1),
	'modules_parts' => array('triggers' => array('/lmdbadvancedproject/test/.cache/autoclose'), 'hooks' => array()),
	'file' => (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT, 'alt0' => dirname(__DIR__, 2))));
foreach ($conf->modules as $module => $enabled) { $conf->$module = (object) array('enabled' => 1); }
$nativeConf = new Conf();
foreach ((array) $conf as $key => $value) { $nativeConf->$key = $value; }
$conf = $nativeConf;
$conf->user = (object) array('dir_output' => DOL_DATA_ROOT);
$langs = new Translate(DOL_DOCUMENT_ROOT.'/langs', $conf);
$langs->setDefaultLang('en_US');
$db = new AutocloseDB('sqlite3', '', '', '');
$checks = 0;
function assertClosure($actual, $expected, string $message): void {
	global $checks;
	$checks++;
	if ($actual !== $expected) { throw new RuntimeException($message.': '.var_export($actual, true).' expected '.var_export($expected, true)); }
}
function fixtureInsert(string $table, array $values): void {
	global $db;
	$quoted = array_map(static function ($value) use ($db) { return $value === null ? 'NULL' : "'".$db->escape((string) $value)."'"; }, $values);
	$db->query('INSERT INTO '.MAIN_DB_PREFIX.$table.' ('.implode(',', array_keys($values)).') VALUES ('.implode(',', $quoted).')');
}
function fixtureValue(string $column, string $table, int $id) {
	global $db;
	$row = $db->fetch_object($db->query('SELECT '.$column.' AS value FROM '.MAIN_DB_PREFIX.$table.' WHERE rowid = '.$id));
	return $row->value;
}
// Use column names from the exact native schemas, replacing only fixture DDL types.
foreach (array('projet','projet_task','user','user_extrafields','c_country','c_departements','establishment','user_param','rights_def','user_rights','usergroup_rights','usergroup_user','societe','socpeople','societe_commerciaux','facture','facturedet','commande','actioncomm','actioncomm_resources','c_actioncomm','element_contact','c_type_contact','extrafields','ecm_files') as $table) {
	$schema = file_get_contents(DOL_DOCUMENT_ROOT.'/install/mysql/tables/llx_'.$table.'.sql');
	preg_match_all('/^\s*`?([a-z][a-z0-9_]*)`?\s+(integer|int|smallint|tinyint|bigint|varchar|char|text|mediumtext|longtext|datetime|date|timestamp|double|real|float|decimal|boolean)\b/im', $schema, $matches, PREG_SET_ORDER);
	$columns = array();
	foreach ($matches as $match) { $columns[] = $match[1].(in_array($match[1], array('rowid','id')) ? ' INTEGER PRIMARY KEY' : ' TEXT'); }
	$db->query('CREATE TABLE '.MAIN_DB_PREFIX.$table.' ('.implode(',', $columns).')');
}
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'lmdbadvancedproject_customer_invoice_parts (rowid INTEGER PRIMARY KEY, entity INTEGER, fk_projet INTEGER, fk_facture INTEGER, fk_facture_det INTEGER, total_ht REAL, total_ttc REAL, qty REAL)');
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'closure_events (rowid INTEGER PRIMARY KEY, project_id INTEGER, actor_id INTEGER, rule TEXT)');
$extrafields = (object) array('attributes' => array('projet' => array('loaded' => true), 'user' => array('loaded' => true), 'actioncomm' => array('loaded' => true), 'ecm_files' => array('loaded' => true)));
$user = $actor = new AutocloseActor($db);
$actor->id = 1; $actor->entity = 1; $actor->statut = 1; $actor->socid = 0;
fixtureInsert('user', array('rowid' => 1, 'entity' => 1, 'statut' => 1, 'admin' => 0, 'login' => 'closure-runner', 'api_key' => '', 'socialnetworks' => '{}'));
fixtureInsert('societe', array('rowid' => 1, 'entity' => 1));
fixtureInsert('user', array('rowid'=>2,'entity'=>1,'statut'=>1,'login'=>'external','fk_soc'=>1));
fixtureInsert('user', array('rowid'=>3,'entity'=>1,'statut'=>0,'login'=>'inactive'));
$mc = new class { public $scopes = array(); public function getEntity($element, ...$args) { return $this->scopes[$element] ?? '1'; } };
$form = new Form($db);
$options = $form->select_dolusers(-1, 'project_autoclose_user', 1, null, 0, '', '', '', 0, 0, LmdbAdvancedProjectCompatibility::projectAutocloseUserFilter(), 0, '', '', 0, 1);
assertClosure(isset($options[1]), true, 'Native user selector accepts the compatible SQL/USF filter');
assertClosure(isset($options[2]), false, 'Native user selector excludes external accounts');
assertClosure(isset($options[3]), false, 'Native user selector excludes disabled accounts');
$job = new LmdbAdvancedProjectAutoclose($db);
$old = $db->idate(dol_time_plus_duree(dol_now(), -60, 'd'));
$recent = $db->idate(dol_time_plus_duree(dol_now(), -2, 'd'));
$future = $db->idate(dol_time_plus_duree(dol_now(), 2, 'd'));
$fixtureDir = __DIR__.'/.cache/autoclose';
if (!is_dir($fixtureDir)) { mkdir($fixtureDir, 0777, true); }
file_put_contents($fixtureDir.'/interface_99_modTestclosure_RecordClosure.class.php', <<<'PHP'
<?php
class InterfaceRecordClosure {
	public $errors = array();
	private $db;
	public function __construct($db) { $this->db = $db; }
	public function runTrigger($action, $object, $user, $langs, $conf) {
		if ($action !== 'PROJECT_CLOSE') { return 0; }
		global $closureTriggerFails;
		$this->db->query('INSERT INTO '.MAIN_DB_PREFIX.'closure_events (project_id, actor_id, rule) VALUES ('.((int) $object->id).', '.((int) $user->id).", '".$this->db->escape($object->context['auto_close_rule'])."')");
		if ($closureTriggerFails) { $this->errors[] = 'Injected trigger failure'; return -1; }
		return 0;
	}
}
PHP
);
$closureTriggerFails = false;
function resetClosure(string $rule = 'both'): void {
	global $db, $conf, $actor, $old, $closureTriggerFails, $mc;
	foreach (array('closure_events','actioncomm','lmdbadvancedproject_customer_invoice_parts','facturedet','facture','commande','projet_task','projet') as $table) { $db->query('DELETE FROM '.MAIN_DB_PREFIX.$table); }
	$conf->global->LMDBADVANCEDPROJECT_AUTO_CLOSE_RULE = $rule;
	$conf->global->LMDBADVANCEDPROJECT_AUTO_CLOSE_DAYS = 30;
	$conf->global->LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT = 0;
	$actor->denied = array(); $actor->socid = 0; $actor->statut = 1; $actor->admin = 0;
	$closureTriggerFails = false; $mc->scopes = array();
	$db->afterDocumentRead = null;
	fixtureInsert('projet', array('rowid' => 1, 'entity' => 1, 'fk_statut' => 1, 'public' => 1, 'datec' => $old, 'dateo' => $old, 'ref' => 'TEST1'));
	fixtureInsert('facture', array('rowid' => 1, 'entity' => 1, 'fk_projet' => 1, 'fk_soc' => 1, 'fk_statut' => 2, 'paye' => 1));
	fixtureInsert('commande', array('rowid' => 1, 'entity' => 1, 'fk_projet' => 1, 'fk_soc' => 1, 'fk_statut' => 3));
}
function addActivity(?string $start, ?string $end = null, ?string $recorded = null, int $entity = 1, bool $elementLink = false): void {
	fixtureInsert('actioncomm', array('id' => 1, 'entity' => $entity, 'fk_project' => $elementLink ? null : 1,
		'elementtype' => $elementLink ? 'project' : null, 'fk_element' => $elementLink ? 1 : null,
		'datep' => $start, 'datep2' => $end, 'datec' => $recorded, 'percent' => -1));
}

resetClosure();
assertClosure($job->closeProject(1, $actor), 1, 'Inactive paid/delivered project closes');
assertClosure((int) fixtureValue('fk_statut', 'projet', 1), 2, 'Native project closed');
assertClosure((int) fixtureValue('fk_user_close', 'projet', 1), 1, 'Explicit actor recorded');
assertClosure($job->closeProject(1, $actor), 0, 'Replay does not close twice');
assertClosure((int) $db->fetch_object($db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.'closure_events'))->n, 1, 'Native trigger exactly once');

foreach (array('invoices','orders','both','either','disabled') as $rule) {
	foreach (array(0,1) as $paid) { foreach (array(1,3) as $delivered) {
		resetClosure($rule);
		$db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET paye = '.$paid);
		$db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_statut = '.$delivered);
		$expected = $rule === 'disabled' ? false : ($rule === 'both' ? ($paid === 1 && $delivered === 3)
			: ($rule === 'invoices' ? $paid === 1 : ($rule === 'orders' ? $delivered === 3 : ($paid === 1 || $delivered === 3))));
		assertClosure($job->closeProject(1, $actor), (int) $expected, 'Rule '.$rule.' paid '.$paid.' order '.$delivered);
	} }
}
foreach (array('both','either','invoices','orders') as $rule) {
	resetClosure($rule); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'facture'); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'commande');
	assertClosure($job->closeProject(1, $actor), 0, 'Empty project stays open: '.$rule);
}
foreach (array(array($old,null,null,1,false,1), array($recent,null,null,1,false,0), array($old,$future,null,1,false,0), array($future,null,null,1,false,0),
	array($old,null,$recent,1,false,0), array(null,null,null,1,false,0), array($recent,null,null,1,true,0), array($old,null,null,1,true,1)) as $event) {
	resetClosure(); addActivity($event[0],$event[1],$event[2],$event[3],$event[4]);
	assertClosure($job->closeProject(1, $actor), $event[5], 'Activity '.json_encode($event));
}
resetClosure(); $db->query("UPDATE ".MAIN_DB_PREFIX."projet SET datec = '".$recent."'");
assertClosure($job->closeProject(1, $actor), 0, 'Recent creation without events stays open');
resetClosure(); $conf->global->LMDBADVANCEDPROJECT_AUTO_CLOSE_DAYS = 90;
assertClosure($job->closeProject(1, $actor), 0, 'Configured longer delay is respected');
resetClosure(); $conf->global->LMDBADVANCEDPROJECT_AUTO_CLOSE_DAYS = 0;
assertClosure($job->closeProject(1, $actor), 0, 'Zero delay is invalid');
resetClosure(); $db->query('UPDATE '.MAIN_DB_PREFIX.'projet SET datec = NULL, dateo = NULL');
assertClosure($job->closeProject(1, $actor), 0, 'Unknown activity age stays open');
resetClosure(); $db->query('UPDATE '.MAIN_DB_PREFIX.'projet SET fk_statut = 0');
assertClosure($job->closeProject(1, $actor), 0, 'Draft project stays draft');
foreach (array(0,1,3) as $status) {
	resetClosure('invoices'); $db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET fk_statut = '.$status.', paye = 0');
	assertClosure($job->closeProject(1, $actor), 0, 'Unpaid invoice status '.$status.' blocks');
}
resetClosure('invoices'); $db->query("UPDATE ".MAIN_DB_PREFIX."facture SET fk_statut=3, close_code='replaced', paye=0");
assertClosure($job->closeProject(1, $actor), 0, 'Only replaced invoice is not a complete set');
resetClosure('orders'); $db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_statut = -1');
assertClosure($job->closeProject(1, $actor), 0, 'Only cancelled order is not a complete set');
resetClosure('invoices'); $conf->global->LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT = 1;
fixtureInsert('facture', array('rowid'=>2,'entity'=>1,'fk_projet'=>2,'fk_soc'=>1,'fk_statut'=>1,'paye'=>0));
fixtureInsert('facturedet', array('rowid'=>2,'fk_facture'=>2));
fixtureInsert('lmdbadvancedproject_customer_invoice_parts', array('rowid'=>1,'entity'=>1,'fk_projet'=>1,'fk_facture'=>2,'fk_facture_det'=>2,'total_ht'=>10));
assertClosure($job->closeProject(1, $actor), 0, 'Unpaid allocated invoice blocks');
$db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET paye=1,fk_statut=2 WHERE rowid=2');
assertClosure($job->closeProject(1, $actor), 1, 'Paid allocated invoice permits closure');
foreach (array('lmdbadvancedproject.autoclose.run','projet.lire','projet.creer','facture.lire','commande.lire') as $permission) {
	resetClosure(); $actor->admin = 1; $actor->denied = array($permission);
	assertClosure($job->closeProject(1, $actor), 0, 'No admin bypass: '.$permission);
}
resetClosure(); $actor->denied = array('agenda.myactions.read','agenda.allactions.read');
assertClosure($job->closeProject(1, $actor), 0, 'Agenda read permission is required');
resetClosure(); addActivity($old); $actor->denied = array('agenda.allactions.read');
assertClosure($job->closeProject(1, $actor), 0, 'Old unassigned Agenda event is inaccessible with own-event permission');
resetClosure(); $actor->socid = 1;
assertClosure($job->closeProject(1, $actor), 0, 'External actor refused');
resetClosure(); $db->query('UPDATE '.MAIN_DB_PREFIX.'projet SET public=0');
assertClosure($job->closeProject(1, $actor), 1, 'Native read-all and write-all allow private project');
resetClosure(); $db->query('UPDATE '.MAIN_DB_PREFIX.'projet SET public=0'); $actor->denied = array('projet.all.creer');
assertClosure($job->closeProject(1, $actor), 0, 'Unassigned private project refused');
resetClosure(); $db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET fk_soc=999');
assertClosure($job->closeProject(1, $actor), 0, 'Missing third party blocks');
resetClosure(); $conf->modules['societe'] = 1; $actor->denied = array('societe.client.voir');
assertClosure($job->closeProject(1, $actor), 0, 'Commercial restriction is respected');
unset($conf->modules['societe']);
resetClosure(); $conf->modules['multicompany'] = 1; $mc->scopes['user'] = '0,1';
$db->query('UPDATE '.MAIN_DB_PREFIX.'projet SET entity=2'); $mc->scopes['project'] = '1,2';
assertClosure($job->closeProject(1, $actor), 0, 'Shared project belongs to its owner entity');
resetClosure(); $mc->scopes['user'] = '0,1'; $db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET entity=2');
assertClosure($job->closeProject(1, $actor), 0, 'Hidden unpaid or paid invoice is not omitted');
resetClosure(); $mc->scopes['user'] = '0,1'; addActivity($old,null,null,2);
assertClosure($job->closeProject(1, $actor), 0, 'Hidden Agenda event prevents closure');
unset($conf->modules['multicompany']);
resetClosure(); $conf->entity = 2; $job->entity = '2';
$mc->scopes = array('user'=>'0,1','project'=>'2','facture'=>'2','commande'=>'2','agenda'=>'2','societe'=>'1,2');
foreach (array('projet','facture','commande') as $table) { $db->query('UPDATE '.MAIN_DB_PREFIX.$table.' SET entity=2'); }
assertClosure($job->closeProject(1, $actor), 1, 'Owner entity 2 closes its project with native shared user/third party scopes');
$conf->entity = 1; $job->entity = 0;
resetClosure(); $closureTriggerFails = true;
try { $job->closeProject(1, $actor); throw new LogicException('Expected trigger failure'); } catch (RuntimeException $exception) { }
assertClosure((int) fixtureValue('fk_statut','projet',1), 1, 'Trigger failure rolls back native project');
assertClosure((int) $db->fetch_object($db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.'closure_events'))->n, 0, 'Trigger write rolled back');
foreach (array('BEGIN','COMMIT','SELECT d.rowid') as $sql) {
	resetClosure(); $db->failSql = $sql;
	try { $job->closeProject(1, $actor); throw new LogicException('Expected database failure'); } catch (RuntimeException $exception) { }
	assertClosure((int) fixtureValue('fk_statut','projet',1), 1, 'Failure rolls back: '.$sql);
}
resetClosure(); $db->begin();
assertClosure($job->closeProject(1,$actor), 1, 'Native nested transaction permits caller rollback');
$db->rollback();
assertClosure((int) fixtureValue('fk_statut','projet',1), 1, 'Outer rollback remains authoritative');
assertClosure($db->transaction_opened, 0, 'Native transaction balance preserved');
resetClosure(); addActivity($recent); $db->failSql = 'ROLLBACK';
try { $job->closeProject(1, $actor); throw new LogicException('Expected rollback failure'); } catch (RuntimeException $exception) { }
assertClosure((int) fixtureValue('fk_statut','projet',1), 1, 'Rollback failure is reported without closing');
assertClosure($db->transaction_opened, 0, 'Rollback failure retry leaves balanced transaction');
resetClosure('disabled'); assertClosure($job->run(), 0, 'Disabled job is harmless');
resetClosure(); $conf->global->LMDBADVANCEDPROJECT_AUTO_CLOSE_USER = 0;
assertClosure($job->run(), -1, 'Missing execution user is a visible job failure');
$conf->global->LMDBADVANCEDPROJECT_AUTO_CLOSE_USER = 1;
resetClosure();
$permissions = array(array('lmdbadvancedproject','autoclose','run'), array('projet','lire',null), array('projet','creer',null),
	array('projet','all','creer'), array('projet','all','lire'), array('facture','lire',null), array('commande','lire',null),
	array('agenda','allactions','read'), array('societe','client','voir'));
foreach ($permissions as $offset => $permission) {
	fixtureInsert('rights_def', array('id'=>100+$offset,'entity'=>1,'module'=>$permission[0],'perms'=>$permission[1],'subperms'=>$permission[2]));
	fixtureInsert('user_rights', array('entity'=>1,'fk_user'=>1,'fk_id'=>100+$offset));
}
assertClosure($job->run(), 0, 'Actual native User fetch/rights and job execution');
assertClosure((int) fixtureValue('fk_statut','projet',1), 2, 'Job closes eligible project');
assertClosure($job->run(), 0, 'Repeated job succeeds without duplicate effects');
assertClosure((int) $db->fetch_object($db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.'closure_events'))->n, 1, 'Repeated job emits one native closure');
resetClosure();
for ($id = 2; $id <= 121; $id++) {
	fixtureInsert('projet', array('rowid'=>$id,'entity'=>1,'fk_statut'=>1,'public'=>1,'datec'=>$old,'dateo'=>$old,'ref'=>'TEST'.$id));
	fixtureInsert('facture', array('rowid'=>$id,'entity'=>1,'fk_projet'=>$id,'fk_soc'=>1,'fk_statut'=>2,'paye'=>1));
	fixtureInsert('commande', array('rowid'=>$id,'entity'=>1,'fk_projet'=>$id,'fk_soc'=>1,'fk_statut'=>3));
}
assertClosure($job->run(), 0, 'Job processes several pages with shrinking candidate set');
assertClosure((int) $db->fetch_object($db->query('SELECT COUNT(*) AS n FROM '.MAIN_DB_PREFIX.'closure_events'))->n, 121, 'Every paginated project closed once');
resetClosure(); $db->query('DELETE FROM '.MAIN_DB_PREFIX.'user_rights WHERE fk_id=100');
assertClosure($job->run(), -1, 'Configured actor missing module permission fails explicitly');
fixtureInsert('user_rights', array('entity'=>1,'fk_user'=>1,'fk_id'=>100));
resetClosure(); unset($conf->modules['agenda']);
assertClosure($job->run(), -1, 'Disabled Agenda dependency is a visible job failure');
$conf->modules['agenda'] = 1;
resetClosure(); $job->entity = 2;
assertClosure($job->run(), -1, 'Scheduler entity mismatch rejected');
$job->entity = 0;
echo $checks.' assertions passed with native Project, Interfaces and transaction methods ('.DOL_VERSION.').'.PHP_EOL;
