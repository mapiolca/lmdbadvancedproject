<?php
// A native TCPDF render, using test source documents rather than a running ERP.
require __DIR__.'/costexports_test.php';
if (!defined('DOL_DATA_ROOT')) { define('DOL_DATA_ROOT', __DIR__.'/.cache'); }
$conf->file=(object)array('dol_document_root'=>array(DOL_DOCUMENT_ROOT),'instance_unique_id'=>'fixture');
require_once __DIR__.'/../core/modules/project/doc/pdf_budgetreport.modules.php';
$user = new class($db) extends User { public $allowed=true; public function hasRight($module,$permlevel1,$permlevel2='') { return $this->allowed; } };
$user->id=1; $user->firstname='Test'; $user->lastname='User';
foreach (array(DOL_DOCUMENT_ROOT.'/langs/fr_FR/main.lang',DOL_DOCUMENT_ROOT.'/langs/fr_FR/projects.lang',DOL_DOCUMENT_ROOT.'/langs/fr_FR/companies.lang',__DIR__.'/../langs/fr_FR/lmdbadvancedproject.lang') as $file) {
	foreach (file($file,FILE_IGNORE_NEW_LINES) as $entry) { if (strpos($entry,'=') !== false && substr($entry,0,1)!=='#') { [$key,$value]=explode('=',$entry,2); $langs->translations[trim($key)]=trim($value); } }
}
define('DOL_MAIN_URL_ROOT', 'https://example.invalid');
define('TCPDF_PATH', DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/');
define('TCPDI_PATH', DOL_DOCUMENT_ROOT.'/includes/tcpdi/');
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
$extrafields = new ExtraFields($db);
$extrafields->attributes['projet']=array('loaded'=>true,'label'=>array());
$db->query('CREATE TABLE '.MAIN_DB_PREFIX.'projet_extrafields (fk_object INTEGER,tms TEXT)');
$conf->modules_parts=array('substitutions'=>array());
$mysoc = new Societe($db); $mysoc->name='Entreprise de test'; $mysoc->logo='';
$conf->mycompany=(object)array('dir_output'=>__DIR__.'/.cache');
$conf->project=(object)array('multidir_output'=>array(1=>__DIR__.'/.cache/pdf-documents'));
$conf->file=(object)array('dol_document_root'=>array(DOL_DOCUMENT_ROOT),'instance_unique_id'=>'fixture');
$project=new Project($db); $project->id=1; $project->entity=1; $project->ref='P1'; $project->title='Régularisation des expéditions'; $project->public=1;
$project->date_start=strtotime('2026-02-01');$project->date_end=strtotime('2026-05-01');
$project->thirdparty=$mysoc; $project->context['budgetreport_filters']=$period;
$model=new pdf_budgetreport($db);
check($model->write_file($project,$langs),1,'Native PDF generation');
check(is_file($model->result['fullpath']),true,'PDF file exists in owner directory');
copy($model->result['fullpath'],__DIR__.'/.cache/cost-report.pdf');
// Make a negative correction explicit in the graphic as well as in totals.
$db->query('UPDATE '.MAIN_DB_PREFIX.'lmdbadvancedproject_supplier_invoice_parts SET total_ht=32 WHERE rowid=1');
check($model->write_file($project,$langs),1,'Negative regularization PDF generation');
copy($model->result['fullpath'],__DIR__.'/.cache/cost-report-negative.pdf');
// Native free-text sizing must protect content, including HTML and company details.
$height = new ReflectionProperty($model, 'footerHeight');
$emptyHeight = $height->getValue($model);
$mysoc->address='Adresse de test'; $mysoc->zip='75000'; $mysoc->town='Paris';
$conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS=1;
foreach (array(false, true) as $html) {
	$conf->global->PDF_ALLOW_HTML_FOR_FREE_TEXT=$html ? 1 : 0;
	$conf->global->PROJECT_FREE_TEXT=implode($html ? '<br>' : "\n",array_fill(0,12,'Texte de pied de page à conserver sans chevauchement.'));
	check($model->write_file($project,$langs),1,'Native long '.($html ? 'HTML' : 'text').' footer PDF');
	check($height->getValue($model)>$emptyHeight+20,true,'Native footer height measured before content');
	copy($model->result['fullpath'],__DIR__.'/.cache/cost-report-footer-'.($html ? 'html' : 'text').'.pdf');
}
$conf->global->PROJECT_FREE_TEXT='';
$hookmanager = new class {
	public $resPrint='';
	public function initHooks($contexts) { }
	public function executeHooks($name,...$args) { $this->resPrint=$name === 'pdf_pagefoot' ? implode('<br>',array_fill(0,12,'Pied de page fourni par un hook natif.')) : ''; return 0; }
};
check($model->write_file($project,$langs),1,'Native custom footer hook PDF');
check($height->getValue($model)>$emptyHeight+20,true,'Hook footer height reserved');
copy($model->result['fullpath'],__DIR__.'/.cache/cost-report-footer-hook.pdf');
$conf->global->PROJECT_FREE_TEXT=implode('<br>',array_fill(0,70,'Pied exceptionnellement long.'));
check($model->write_file($project,$langs),-1,'Oversized footer refused before rendering content');
$conf->global->PROJECT_FREE_TEXT='';
$user->allowed=false;
check($model->write_file($project,$langs),-1,'PDF generation refuses missing rights');
$user->allowed=true;
unset($conf->project->multidir_output[1]);
check($model->write_file($project,$langs),-1,'PDF generation refuses missing owner directory');
echo $checks." assertions passed including native PDF serialization and access guards.\n";
