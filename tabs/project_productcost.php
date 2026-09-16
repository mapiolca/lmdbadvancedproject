<?php
/* Copyright (C) 2026 Pierre Ardoin <developpeur@lesmetiersdubatiment.fr>
 * SPDX-License-Identifier: GPL-3.0-or-later
 */
// Load Dolibarr environment
$res = 0;
if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php')) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))).'/main.inc.php';
}
foreach (array('../main.inc.php', '../../main.inc.php', '../../../main.inc.php', '../../../../main.inc.php') as $mainFile) {
	$resolvedMainFile = realpath(__DIR__.'/'.$mainFile);
	if (!$res && $resolvedMainFile !== false) {
		$res = @include $resolvedMainFile;
	}
}
if (!$res) {
	die('Include of main fails');
}


/** @var DoliDB $db */
/** @var User $user */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var HookManager $hookmanager */
global $db, $user, $conf, $langs, $hookmanager;

require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/ajax.lib.php';
require_once __DIR__.'/../lib/budgetreport.lib.php';

$langs->loadLangs(array('main', 'projects', 'products', 'orders', 'bills', 'sendings', 'lmdbadvancedproject@lmdbadvancedproject'));
if (!LmdbAdvancedProjectCompatibility::isShipmentCostEnabled() || !$user->hasRight('projet', 'lire')
	|| !$user->hasRight('lmdbadvancedproject', 'budgetreport', 'read')) {
	accessforbidden();
}
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$object = new Project($db);
if ($object->fetch($id, $ref) <= 0 || $object->restrictedProjectArea($user, 'read') <= 0) {
	accessforbidden();
}
$id = (int) $object->id;
restrictedArea($user, 'projet', $id, 'projet&project');
$thirdpartyResult = $object->fetch_thirdparty();
if ($thirdpartyResult < 0) {
	setEventMessages($object->error, $object->errors, 'errors');
}
$action = GETPOST('action', 'aZ09');
$contextpage = 'lmdbadvancedproject_productcost';
$search = array('ref' => GETPOST('search_ref', 'alphanohtml'), 'label' => GETPOST('search_label', 'alphanohtml'),
	'type' => GETPOST('search_type', 'alpha'), 'entities' => array_values(array_map('intval', GETPOST('search_entities', 'array:int'))));
$resetFilters = GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha');
if ($resetFilters) {
	$search = array('ref' => '', 'label' => '', 'type' => '', 'entities' => array());
}
$filters = lmdbadvancedproject_normalize_budget_report_filters(array(
	'date_start' => $resetFilters ? '' : lmdbadvancedproject_get_budget_report_request_date('date_start'),
	'date_end' => $resetFilters ? '' : lmdbadvancedproject_get_budget_report_request_date('date_end'),
	'exclude_content_outside_period' => '1',
));
$hookmanager->initHooks(array('projectproductcost', 'projectcard', 'globalcard'));
$parameters = array('id' => $id, 'filters' => $filters);
if ($hookmanager->executeHooks('doActions', $parameters, $object, $action) < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
$columns = lmdbadvancedproject_product_cost_columns();
$entityLabels = array();
if (isModEnabled('multicompany')) {
	// Only entities already authorized by a native document-sharing scope.
	$entityIds = array();
	foreach (array('commande', 'supplier_order', 'supplier_invoice', 'expedition') as $element) {
		$entityIds = array_merge($entityIds, array_map('intval', explode(',', getEntity($element))));
	}
	$result = $db->query('SELECT rowid, label FROM '.MAIN_DB_PREFIX.'entity WHERE rowid IN ('.implode(',', array_unique($entityIds)).') ORDER BY label');
	if (!$result) {
		accessforbidden($langs->trans('BudgetCostReadFailed'));
	}
	while (is_object($entity = $db->fetch_object($result))) {
		$entityLabels[(int) $entity->rowid] = (string) $entity->label;
	}
	$db->free($result);
	if (count($entityLabels) > 1) {
		$columns['entities'] = 'BudgetCostEnvironment';
	}
}
$search['entities'] = array_values(array_intersect($search['entities'], array_keys($entityLabels)));
$arrayfields = array();
foreach ($columns as $field => $label) {
	$arrayfields[$field] = array('label' => $label, 'checked' => '1', 'enabled' => '1', 'position' => count($arrayfields) + 1);
}
require DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';
$form = new Form($db);
$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $contextpage);
$sortfield = GETPOST('sortfield', 'aZ09');
if (!isset($columns[$sortfield]) || in_array($sortfield, array('issues', 'entities'), true)) {
	$sortfield = 'ref';
}
$sortorder = GETPOST('sortorder', 'aZ09') === 'DESC' ? 'DESC' : 'ASC';
$limit = GETPOSTINT('limit') ?: (int) $conf->liste_limit;
$limit = max(1, $limit);
$page = max(0, GETPOSTINT('page'));
try {
	$service = new LmdbAdvancedProjectProductCost($db);
	$report = $service->load(array($id), $filters, $search);
} catch (RuntimeException $exception) {
	llxHeader('', $langs->trans('BudgetCostProductList'));
	print '<div class="error">'.$langs->trans($exception->getMessage()).'</div>';
	llxFooter();
	$db->close();
	exit;
}
$rows = array_values($report['products']);
// Derived monetary columns require reconciliation before ordering/pagination.
// Text, type and environment filters above are applied to sources in SQL.
usort($rows, static function (array $left, array $right) use ($sortfield, $sortorder): int {
	$comparison = is_string($left[$sortfield]) ? strnatcasecmp($left[$sortfield], $right[$sortfield]) : $left[$sortfield] <=> $right[$sortfield];
	return ($comparison ?: $left['product'] <=> $right['product']) * ($sortorder === 'DESC' ? -1 : 1);
});
$total = count($rows);
if ($page * $limit >= $total) {
	$page = 0;
}
$visibleRows = array_slice($rows, $page * $limit, $limit);
$param = '&id='.$id.'&'.http_build_query(array('search_ref' => $search['ref'], 'search_label' => $search['label'],
	'search_type' => $search['type'], 'search_entities' => $search['entities'], 'date_start' => $filters['date_start'], 'date_end' => $filters['date_end']));
llxHeader('', $langs->trans('BudgetCostProductList'), '', '', 0, 0, '', '', '', 'classforhorizontalscrolloftabs');
print dol_get_fiche_head(project_prepare_head($object), 'lmdbap_productcost', $langs->trans('Project'), -1, ($object->public ? 'projectpub' : 'project'));
if (!empty($_SESSION['pageforbacktolist']['project'])) {
	$tmpurl = str_replace('__SOCID__', (string) $object->socid, $_SESSION['pageforbacktolist']['project']);
	$linkback = '<a href="'.dol_escape_htmltag($tmpurl.(strpos($tmpurl, '?') !== false ? '&' : '?').'restore_lastsearch_values=1').'">'.$langs->trans('BackToList').'</a>';
} else {
	$linkback = '<a href="'.DOL_URL_ROOT.'/projet/list.php?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
}
$morehtmlref = '<div class="refidno">'.dol_escape_htmltag($object->title);
if ($thirdpartyResult > 0 && is_object($object->thirdparty) && $object->thirdparty->id > 0) {
	$morehtmlref .= '<br>'.$object->thirdparty->getNomUrl(1, 'project');
}
$morehtmlref .= '</div>';
// Keep the native previous/next navigation within the user's project access.
if (!$user->hasRight('projet', 'all', 'lire')) {
	$objectsListId = $object->getProjectsAuthorizedForUser($user, 0, 0);
	$object->next_prev_filter = 'rowid IN ('.$db->sanitize(is_array($objectsListId) && $objectsListId ? implode(',', array_keys($objectsListId)) : '0').')';
}
dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);
lmdbadvancedproject_print_cost_notice($report);
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER['PHP_SELF']).'" name="formfilter" id="formfilter">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" value="list">';
print '<input type="hidden" name="id" value="'.$id.'">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<div class="filterother">'.$langs->trans('BudgetReportFilterDateStart').' ';
print $form->selectDate($filters['date_start'] === '' ? -1 : $db->jdate($filters['date_start']), 'date_start', 0, 0, 1, 'formfilter');
print ' '.$langs->trans('BudgetReportFilterDateEnd').' ';
print $form->selectDate($filters['date_end'] === '' ? -1 : $db->jdate($filters['date_end']), 'date_end', 0, 0, 1, 'formfilter');
print '</div>';
print_barre_liste($langs->trans('BudgetCostProductList'), $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', count($visibleRows), $total, 'product', 0, '', '', $limit);
print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
print '<tr class="liste_titre_filter">';
foreach ($arrayfields as $field => $definition) {
	if (empty($definition['checked'])) { continue; }
	print '<td class="liste_titre">';
	if (in_array($field, array('ref', 'label'), true)) {
		print '<input class="flat maxwidth100" name="search_'.$field.'" value="'.dol_escape_htmltag($search[$field]).'">';
	} elseif ($field === 'type') {
		print $form->selectarray('search_type', array('0' => $langs->trans('Product'), '1' => $langs->trans('Service')), $search['type'], 1);
		print ajax_combobox('search_type');
	} elseif ($field === 'entities') {
		print Form::multiselectarray('search_entities', $entityLabels, array_map('strval', $search['entities']), 0, 0, 'minwidth100');
	}
	print '</td>';
}
print '<td class="liste_titre center">'.$form->showFilterButtons().'</td></tr><tr class="liste_titre">';
foreach ($arrayfields as $field => $definition) {
	if (empty($definition['checked'])) { continue; }
	print_liste_field_titre($definition['label'], $_SERVER['PHP_SELF'], in_array($field, array('issues', 'entities'), true) ? '' : $field, '', $param, '', $sortfield, $sortorder);
}
print '<th class="center">'.$selectedfields.'</th></tr>';
$colspan = 1 + count(array_filter($arrayfields, static function (array $field): bool { return !empty($field['checked']); }));
foreach ($visibleRows as $row) {
	print '<tr class="oddeven">';
	foreach ($arrayfields as $field => $definition) {
		if (empty($definition['checked'])) { continue; }
		$value = $row[$field];
		print '<td class="'.(is_float($value) ? 'right' : '').'"'.($field === 'entities' ? ' align="center"' : '').'>';
		if ($field === 'ref') {
			if (($row['type'] === Product::TYPE_PRODUCT && $user->hasRight('product', 'read'))
				|| ($row['type'] === Product::TYPE_SERVICE && $user->hasRight('service', 'read'))) {
				$productLink = new Product($db);
				$productLink->id = $row['product'];
				$productLink->ref = $row['ref'];
				$productLink->label = $row['label'];
				$productLink->type = $row['type'];
				print $productLink->getNomUrl(1);
			} else {
				print dol_escape_htmltag($value);
			}
		} elseif ($field === 'type') {
			print $langs->trans($value === 1 ? 'Service' : 'Product');
		} elseif ($field === 'issues') {
			print dolGetBadge($langs->trans($value ? 'BudgetCostIncomplete' : 'BudgetCostComplete'), '', $value ? 'status1' : 'status4');
		} elseif ($field === 'entities') {
			foreach ($value as $entityId) {
				print '<div class="refidno multicompany-entity-card-container"><span class="fa fa-globe"></span><span class="multiselect-selected-title-text">'.dol_escape_htmltag($entityLabels[$entityId] ?? '').'</span></div>';
			}
		} elseif ($field === 'unit' && count($row['units']) > 1) {
			print dol_escape_htmltag(implode(' / ', $row['units']));
		} elseif (substr($field, -4) === '_qty' && count($row['units']) > 1) {
			print '—';
		} elseif ($value === null) {
			print $langs->trans('BudgetCostMissingPrice');
		} elseif (is_float($value)) {
			print price($value);
		} else {
			print dol_escape_htmltag($value);
		}
		print '</td>';
	}
	print '<td class="center">'.$form->textwithpicto('', lmdbadvancedproject_product_cost_tooltip($row), 1, 'help').'</td></tr>';
}
if (!$visibleRows) {
	print '<tr class="oddeven"><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
print '</table></div></form>';
print dol_get_fiche_end();
llxFooter();
$db->close();
