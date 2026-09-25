<?php
/* Native hooks and SQL fixtures; no live ERP or business data is changed. */
require __DIR__.'/costexports_test.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
if (is_file(DOL_DOCUMENT_ROOT.'/core/lib/html.lib.php')) { require_once DOL_DOCUMENT_ROOT.'/core/lib/html.lib.php'; }
require_once __DIR__.'/../class/actions_lmdbadvancedproject.class.php';

class SummaryFixtureUser extends User {
	public $denied = array();
	public function hasRight($module, $permlevel1, $permlevel2 = '') {
		return !in_array(implode('.', array_filter(array($module, $permlevel1, $permlevel2))), $this->denied, true);
	}
}
class SummaryFixtureDB extends FixtureDB {
	public $queries = array();
	public $fail = false;
	public function query($sql) {
		$this->queries[] = $sql;
		if ($this->fail) { return false; }
		return parent::query($sql);
	}
}
$tracked = new SummaryFixtureDB();
$tracked->connection = $db->connection;
$db = $tracked;
$user = new SummaryFixtureUser($db);
$user->id = 1;
$user->socid = 0;
$conf->file = (object) array('dol_document_root' => array('main' => DOL_DOCUMENT_ROOT, 'alt0' => dirname(__DIR__, 2)), 'dol_url_root' => array('main' => '', 'alt0' => ''));
$conf->browser = (object) array('layout'=>'classic');
$_SERVER['PHP_SELF'] = '/projet/list.php';
$langs->translations['BudgetBillingProgress'] = 'Progressions facturation';
$conf->global->LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT = 0;
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'lmdbadvancedproject_customer_invoice_parts (rowid INTEGER PRIMARY KEY, entity INTEGER, fk_projet INTEGER, fk_facture INTEGER, fk_facture_det INTEGER, total_ht REAL)');
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'societe_commerciaux (fk_soc INTEGER, fk_user INTEGER)');
foreach (array(3,4,5) as $id) { insertFixture('projet', array('rowid'=>$id, 'entity'=>1, 'ref'=>'P'.$id, 'title'=>'Project '.$id, 'public'=>1)); }
foreach (array(array(10,1,2,0,100), array(11,1,1,2,-20), array(12,1,0,0,500), array(13,2,1,0,900)) as $invoice) {
	list($id,$entity,$status,$type,$amount) = $invoice;
	insertFixture('facture', array('rowid'=>$id,'entity'=>$entity,'fk_projet'=>1,'ref'=>'F'.$id,'fk_statut'=>$status,'type'=>$type,'total_ht'=>$amount,'datef'=>'2026-06-01','fk_soc'=>0));
	insertFixture('facturedet', array('rowid'=>$id,'fk_facture'=>$id,'total_ht'=>$amount));
}
function listBillingRows(string $where = '', string $order = 'p.rowid'): array {
	global $db;
	$expr = lmdbadvancedproject_billing_expressions();
	return $db->query('SELECT p.rowid, '.$expr['orders'].' AS ordered, '.$expr['invoiced'].' AS invoiced, '.$expr['rate'].' AS rate FROM '.MAIN_DB_PREFIX.'projet p WHERE p.entity IN (1)'.$where.' ORDER BY '.$order)->rows;
}
check(listBillingRows()[0]->ordered, 200, 'Native list ordered amount');
check(listBillingRows()[0]->invoiced, 80, 'Credit note reduces invoicing; draft and inaccessible invoice excluded');
check(listBillingRows()[0]->rate, 40, 'Native list numeric percentage');
check(listBillingRows()[1]->rate, null, 'No order is undefined');
insertFixture('user',array('rowid'=>41,'thm'=>40));
insertFixture('projet_task',array('rowid'=>41,'fk_projet'=>1,'entity'=>1,'ref'=>'T41','label'=>'Work'));
insertFixture('element_time',array('rowid'=>41,'fk_element'=>41,'elementtype'=>'task','fk_user'=>41,'element_duration'=>7200,'element_date'=>'2026-04-02','thm'=>0));
insertFixture('element_time',array('rowid'=>42,'fk_element'=>41,'elementtype'=>'task','fk_user'=>41,'element_duration'=>1800,'element_date'=>'2026-05-02','thm'=>100));
insertFixture('expensereport',array('rowid'=>41,'entity'=>1,'fk_user_approve'=>1));
insertFixture('expensereport_det',array('rowid'=>41,'fk_expensereport'=>41,'fk_projet'=>1,'date'=>'2026-05-01','total_ht'=>7.25));
$db->queries=array();
$summary = lmdbadvancedproject_load_budget_report_data(1, array(), true);
$summaryQueryCount=count($db->queries);
$db->queries=array();
$full = lmdbadvancedproject_load_budget_report_data(1);
check($summaryQueryCount<count($db->queries),true,'Compact loader avoids report detail queries');
foreach (array('totalorders','totalcustomerinvoices','budget','balance','totalspent','totaltime','totalTimeHours','totalvendinv','totalexpenses','totalshipmentcost','totalsupplierordersremaining') as $key) {
	check($summary[$key], $full[$key], 'Compact/full report parity: '.$key);
}
check(isset($summary['budgetReportForecast']), false, 'Summary skips forecasts');
check($summary['totalcustomerinvoices'], 80, 'Report and native list agree');
check($summary['totaltime'],130,'Time rate and native user rate fallback retained');
check($summary['totalTimeHours'],2.5,'Time across multiple months retained');
check($summary['totalexpenses'],7.25,'Approved expenses retained');
check($summary['balance'],-113.25,'Overspent budget keeps negative balance');
$conf->global->LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST=0;
check(lmdbadvancedproject_load_budget_report_data(1,array(),true)['totalspent'],lmdbadvancedproject_load_budget_report_data(1)['totalspent'],'Legacy cost mode has identical compact/full totals');
$conf->global->LMDBADVANCEDPROJECT_ENABLE_SHIPMENT_COST=1;
$period = lmdbadvancedproject_load_budget_report_data(1, array('date_start'=>'2026-02-01','date_end'=>'2026-03-01','exclude_content_outside_period'=>'1'));
check($period['totalcustomerinvoices'], 0, 'Report period still applies');
check($summary['totalcustomerinvoices'], 80, 'Card remains all-period');
foreach (array(array(0,200,'0%',0),array(250,200,'125%',100),array(-50,200,'-25%',0)) as $case) {
	$html = lmdbadvancedproject_billing_progress($case[0],$case[1]);
	check(strpos($html, $case[2]) !== false, true, 'Signed/unbounded numeric label');
	check(strpos($html, 'aria-valuenow="'.$case[3].'"') !== false, true, 'Bounded native bar');
}
foreach (array(0,-1) as $orders) { check(strpos(lmdbadvancedproject_billing_progress(10,$orders),'progress-bar'), false, 'Undefined rate has no bar'); }

$conf->global->LMDBADVANCEDPROJECT_ENABLE_CUSTOMER_INVOICE_SPLIT = 1;
insertFixture('lmdbadvancedproject_customer_invoice_parts', array('rowid'=>1,'entity'=>1,'fk_projet'=>1,'fk_facture'=>10,'fk_facture_det'=>10,'total_ht'=>30));
insertFixture('lmdbadvancedproject_customer_invoice_parts', array('rowid'=>2,'entity'=>1,'fk_projet'=>3,'fk_facture'=>10,'fk_facture_det'=>10,'total_ht'=>70));
check(listBillingRows()[0]->invoiced, 10, 'Split replaces whole line, then credit note');
check(listBillingRows()[1]->invoiced, 70, 'Split reaches project without direct invoice');
check(lmdbadvancedproject_load_budget_report_data(3, array(), true)['totalcustomerinvoices'],70,'Card shows allocation without an order');
$mc->scope = '1,2';
check(listBillingRows()[0]->invoiced, 910, 'Native shared invoice entity accepted');
$mc->scope = '1';
check(listBillingRows()[0]->invoiced, 10, 'Entity restriction restored');
$db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET fk_soc=2 WHERE rowid=10');
check(listBillingRows()[0]->invoiced, -20, 'Inaccessible source third party excludes allocation');
$db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET fk_soc=1 WHERE rowid=10');
$user->denied = array('societe.client.voir');
check(listBillingRows()[0]->invoiced, -20, 'Unassigned commercial third party excluded');
insertFixture('societe_commerciaux', array('fk_soc'=>1,'fk_user'=>1));
check(listBillingRows()[0]->invoiced, 10, 'Assigned third party accepted');
$user->socid = 1;
check(listBillingRows()[0]->invoiced, 30, 'External account only sees its own source documents');
$user->socid = 0;
$user->denied = array();
$db->query('UPDATE '.MAIN_DB_PREFIX.'facture SET fk_soc=0 WHERE rowid=10');
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_soc=2 WHERE rowid=1');
$restrictedReport=lmdbadvancedproject_load_budget_report_data(1);
check($restrictedReport['totalorders'],0,'Inaccessible customer order excluded from summary');
check($restrictedReport['budgetReportForecast']['totals']['order_amount'],0,'Forecast excludes the same inaccessible customer order');
check(array_sum(array_column($restrictedReport['productCosts']['products'],'customer_qty')),0,'Product and spreadsheet quantities exclude inaccessible customer orders');
$db->query('UPDATE '.MAIN_DB_PREFIX.'commande SET fk_soc=0 WHERE rowid=1');

$hooks = new ActionsLmdbadvancedproject($db);
$hookmanager = new HookManager($db);
$hookmanager->contextarray = array('projectlist');
$hookmanager->hooksSorted = array('projectlist'=>array('lmdbadvancedproject'=>$hooks));
$hookmanager->hooks = $hookmanager->hooksSorted;
$object = new Project($db);
$action = '';
$arrayfields = array('p.ref'=>array('checked'=>1));
$sortfield = 'p.ref';
$parameters = array('arrayfields'=>&$arrayfields);
check($hookmanager->executeHooks('doActions',$parameters,$object,$action),0,'Additive native doActions');
check($arrayfields['lmdbap_billing_rate']['checked'],1,'Column registered before native selection');
$totalarray = array('nbfield'=>1);
$hookmanager->executeHooks('printFieldListTitle',array('arrayfields'=>$arrayfields,'param'=>'&limit=20','sortfield'=>'p.ref','sortorder'=>'ASC','totalarray'=>&$totalarray),$object,$action);
check($totalarray['nbfield'],2,'Column included in empty-list colspan');
check(strpos($hookmanager->resPrint,'sortfield=lmdbap_billing_rate')!==false,true,'Native sortable title');
$hookmanager->executeHooks('printFieldListSelect',array(),$object,$action);
$select = $hookmanager->resPrint;
check(strpos($select,' AS lmdbap_billing_rate') !== false,true,'Native SELECT contribution');
$row = $db->query('SELECT p.rowid'.$select.' FROM '.MAIN_DB_PREFIX.'projet p WHERE p.rowid=1')->rows[0];
$before = count($db->queries);
$totalarray = array('nbfield'=>1);
for ($i=0;$i<100;$i++) {
	$hookmanager->executeHooks('printFieldListValue',array('arrayfields'=>$arrayfields,'obj'=>$row,'i'=>$i,'totalarray'=>&$totalarray),$object,$action);
}
check($totalarray['nbfield'],2,'Totals keep native columns aligned');
check(count($db->queries),$before,'No query per rendered list row');
check(strpos($hookmanager->resPrint,'5%') !== false,true,'Native value hook renders computed ratio');
// Native total template, with the same uncounted entity cell as Multicompany 22.0.1.
$totalarray = array('nbfield'=>9, 'pos'=>array(8=>'p.opp_amount'), 'val'=>array('p.opp_amount'=>123.45));
$hookmanager->executeHooks('printFieldListValue',array('arrayfields'=>$arrayfields,'obj'=>$row,'i'=>0,'totalarray'=>&$totalarray),$object,$action);
check($totalarray['nbfield'],10,'Billing contributes its column to native totals');
$billingCell = $hookmanager->resPrint;
$totalarray['nbfield']++; // Native status column, after hook columns.
$num=1;
$limit=20;
$offset=0;
$conf->global->MAIN_GRANDTOTAL_LIST_SHOW=0;
ob_start();
include DOL_DOCUMENT_ROOT.'/core/tpl/list_print_total.tpl.php';
$nativeFooter = (string) ob_get_clean();
check(substr_count($nativeFooter,'<td'),11,'Native totals include billing; only the third-party entity cell is uncounted');
$hookmanager->executeHooks('printFieldListFooter',array('arrayfields'=>$arrayfields),$object,$action);
check(strpos($hookmanager->resPrint,'/js/projectlist.js')!==false,true,'Native footer hook loads targeted cell alignment');
if (getenv('LMDBAP_LIST_HTML')) {
	$html = '<!doctype html><html><meta charset="utf-8"><title>Native project total fixtures</title><style>table{border-collapse:collapse;margin:20px 0;width:100%}td,th{border:1px solid #ccc;padding:8px}.liste_total{background:#eee}</style>';
	foreach (array('missing', 'already-counted', 'no-entity', 'hidden-billing', 'ambiguous') as $case) {
		$header = '<tr class="liste_titre">';
		$values = '<tr class="oddeven">';
		foreach (array('Select','Ref','Title','Third party','End','Assigned','Opportunity','Amount','Probability') as $label) {
			$header .= '<th>'.$label.'</th>';
			$values .= '<td></td>';
		}
		if ($case !== 'no-entity') {
			$header .= '<th>Environment</th>';
			$values .= '<td><div class="refidno multicompany-entity-card-container">TEST</div></td>';
		}
		if ($case !== 'hidden-billing') {
			$header .= '<th class="lmdbap-billing-progress">Billing</th>';
			$values .= $billingCell;
		}
		$header .= '<th>Status</th></tr>';
		$values .= '<td>Open</td></tr>';
		$footer = $nativeFooter;
		if ($case === 'already-counted') { $footer = str_replace('</tr>', '<td></td></tr>', $footer); }
		if ($case === 'ambiguous') { $footer = preg_replace('/<td><\/td>/', '', $footer, 1); }
		$grandFooter = str_replace('liste_total', 'liste_grandtotal', $footer);
		$html .= '<h2>'.$case.'</h2><table id="'.$case.'"><thead>'.$header.'</thead><tbody>'.$values.'</tbody>'.$footer.$grandFooter.'</table>';
	}
	// Loading twice must not add a duplicate cell.
	$html .= '<script src="../../js/projectlist.js"></script><script src="../../js/projectlist.js"></script></html>';
	file_put_contents(getenv('LMDBAP_LIST_HTML'), $html);
}
$_GET['search_lmdbap_billing']='>=5';
$hookmanager->executeHooks('doActions',$parameters,$object,$action);
$hookmanager->executeHooks('printFieldListWhere',array(),$object,$action);
$where = $hookmanager->resPrint;
check(count(listBillingRows($where)),1,'Native numeric filter executes before pagination');
$countSql = 'SELECT p.rowid'.$select.' FROM '.MAIN_DB_PREFIX.'projet p WHERE p.entity=1'.$where;
$countSql = preg_replace('/^'.preg_quote('SELECT p.rowid'.$select, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $countSql);
$countSql = preg_replace('/GROUP BY .*$/', '', $countSql);
check($db->query($countSql)->rows[0]->nbtotalofrecords,1,'Native fast count remains valid with filter');
$hookmanager->executeHooks('printFieldListSearchParam',array(),$object,$action);
check($hookmanager->resPrint,'&search_lmdbap_billing=%3E%3D5','Filter retained in pagination');
$arrayfields['lmdbap_billing_rate']['checked']=0;
$hookmanager->executeHooks('printFieldListValue',array('arrayfields'=>$arrayfields,'obj'=>$row),$object,$action);
check($hookmanager->resPrint,'','Hidden column has no cell');
$hookmanager->executeHooks('printFieldListFooter',array('arrayfields'=>$arrayfields),$object,$action);
check($hookmanager->resPrint,'','Hidden billing column contributes no footer script');
$_GET['button_removefilter']='1';
$hookmanager->executeHooks('doActions',$parameters,$object,$action);
$hookmanager->executeHooks('printFieldListWhere',array(),$object,$action);
check($hookmanager->resPrint,'','Native filter reset');
$_GET = array();
$user->admin=1;
$user->denied = array('lmdbadvancedproject.budgetreport.read');
$sortfield='lmdbap_billing_rate';
$hookmanager->executeHooks('doActions',$parameters,$object,$action);
check(isset($arrayfields['lmdbap_billing_rate']),false,'Admin without report permission gets no column');
check($sortfield,'p.ref','Revoked permission clears stale sort');
$hookmanager->executeHooks('printFieldListSelect',array(),$object,$action);
check($hookmanager->resPrint,'','Denied permission does not query financial values');
$denied = false;
try { lmdbadvancedproject_load_budget_report_data(1,array(),true); } catch (RuntimeException $e) { $denied = $e->getMessage()==='BudgetCostAccessDenied'; }
check($denied,true,'Summary loader also denies direct call');
$user->denied = array();
$object->id=1;
$object->entity=1;
$object->public=1;
$hookmanager = new HookManager($db);
// Another module may register globalcard before any module registers projectcard.
// Preserve that native insertion order, then let initHooks load our real class.
$hookmanager->hooksSorted = array('globalcard'=>array());
$conf->modules_parts['hooks'] = array('lmdbadvancedproject'=>array('projectcard','globalcard'));
$hookmanager->initHooks(array('projectcard','globalcard'));
check(array_keys($hookmanager->hooksSorted),array('globalcard','projectcard'),'Native initialization retains the first context inserted by another module');
// projet/card.php executes this hook without printing HookManager::resPrint.
// Capture the actual page output, not the hook's unused return buffer.
$rendered = '';
foreach (array('classic', 'phone') as $layout) {
	$conf->browser->layout = $layout;
	ob_start();
	$hookmanager->executeHooks('mainCardTabAddMore',array(),$object,$action);
	$rendered = (string) ob_get_clean();
	check(substr_count($rendered,'id="lmdbap-project-summary"'),1,'Page emits exactly one section: '.$layout);
	check(substr_count($rendered,'class="center valignmiddle budgetreport-summary-cell"'),5,'Page emits five tiles: '.$layout);
	check($hookmanager->resPrint,'','Card output does not remain in unused hook buffer: '.$layout);
}
$conf->browser->layout = 'classic';
$projectContext = $hookmanager->contextarray;
$hookmanager->contextarray = array('globalcard');
ob_start();
$hookmanager->executeHooks('mainCardTabAddMore',array(),$object,$action);
check((string) ob_get_clean(),'','Global card alone must not expose project summary');
$hookmanager->contextarray = $projectContext;
$action='edit';
ob_start();
$hookmanager->executeHooks('mainCardTabAddMore',array(),$object,$action);
check((string) ob_get_clean(),'','Editing card has no financial section');
$action='';
$user->denied = array('lmdbadvancedproject.budgetreport.read');
$db->queries = array();
ob_start();
$hookmanager->executeHooks('mainCardTabAddMore',array(),$object,$action);
check((string) ob_get_clean(),'','Denied report permission emits no financial section');
check(count($db->queries),0,'Denied card does not query financial data');
$user->denied = array();
$conf->modules['lmdbadvancedproject']=0;
$conf->lmdbadvancedproject->enabled=0;
ob_start();
$hookmanager->executeHooks('mainCardTabAddMore',array(),$object,$action);
check((string) ob_get_clean(),'','Disabled module contributes no card content');
$conf->modules['lmdbadvancedproject']=1;
$conf->lmdbadvancedproject->enabled=1;
$db->fail=true;
ob_start();
$hookmanager->executeHooks('mainCardTabAddMore',array(),$object,$action);
check(strpos((string) ob_get_clean(),'BudgetSummaryUnavailable')!==false,true,'Query failure emits warning on page, never zero tiles');
$db->fail=false;
$hookmanager = new HookManager($db);
$hookmanager->initHooks(array('projectcard','globalcard'));
ob_start();
$hookmanager->executeHooks('mainCardTabAddMore',array(),$object,$action);
check(substr_count((string) ob_get_clean(),'id="lmdbap-project-summary"'),1,'Project-first native initialization also emits exactly one section');
if (getenv('LMDBAP_SUMMARY_HTML')) { file_put_contents(getenv('LMDBAP_SUMMARY_HTML'),$rendered); }
echo $checks." assertions passed including billing list, compact report parity and native card hooks.\n";
