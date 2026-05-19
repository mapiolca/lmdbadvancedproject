<?php
/* Copyright (C) 2022 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lmdbadvancedproject/css/budgetreport.css.php
 * \ingroup lmdbadvancedproject
 * \brief   CSS file for the Budget Report feature.
 */

//if (! defined('NOREQUIREUSER')) define('NOREQUIREUSER','1');	// Not disabled because need to load personalized language
//if (! defined('NOREQUIREDB'))   define('NOREQUIREDB','1');	// Not disabled. Language code is found on url.
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
//if (! defined('NOREQUIRETRAN')) define('NOREQUIRETRAN','1');	// Not disabled because need to do translations
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1); // File must be accessed by logon page so without login
}
//if (! defined('NOREQUIREMENU'))   define('NOREQUIREMENU',1);  // We need top menu content
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

session_cache_limiter('public');
// false or '' = keep cache instruction added by server
// 'public'  = remove cache instruction added by server
// and if no cache-control added later, a default cache delay (10800) will be added by PHP.

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/../main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/../main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

// Load user to have $user->conf loaded (not done by default here because of NOLOGIN constant defined) and load permission if we need to use them in CSS
/*if (empty($user->id) && ! empty($_SESSION['dol_login'])) {
	$user->fetch('',$_SESSION['dol_login']);
	$user->getrights();
}*/


// Define css type
header('Content-type: text/css');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=10800, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}

?>

div.mainmenu.budgetreport::before {
	content: "\f249";
}
div.mainmenu.budgetreport {
	background-image: none;
}

.budgetreport-summary-fullwidth,
.budgetreport-report,
.budgetreport-month-section,
.budgetreport-table-section {
	clear: both;
	width: 100%;
}

.budgetreport-summary-fullwidth {
	margin-bottom: 30px;
}

.dashboard_budget {
	table-layout: fixed;
	width: 100%;
	margin: 0;
}

.dashboard_budget .budgetreport-summary-cell {
	box-sizing: border-box;
	padding: 10px 8px 12px;
	border-right: solid 2px rgba(0,0,0,0.2);
	border-bottom: solid 2px rgba(0,0,0,0.2);
}

.dashboard_budget .budgetreport-summary-cell:last-child {
	border-right: 0;
}

.budgetreport-summary-label {
	font-size: 110%;
	line-height: 1.2;
}

.budgetreport-summary-amount {
	display: block;
	font-size: 2.1em;
	line-height: 1.1;
	color: #333;
}

.budgetreport-charts-row {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 32px;
	width: 100%;
	align-items: start;
}

.budgetreport-chart-panel {
	min-width: 0;
}

.budgettitle {
	color:#888; 
	font-size: 120%;
	padding-top: 10px;
}

.budgettbl, 
.budgetchart, 
.budgetbarchart {
	margin-top:20px; 
	width:100%;
	margin-bottom: 30px;
}

.budgetchart,
.budgetbarchart {
	height: 350px;
	max-height: 350px;
}

.budgetchart canvas,
.budgetbarchart canvas {
	max-width: 100%;
	max-height: 350px;
}

.budgetreport-table-section .budgettbl {
	width: 100%;
}

.budgettbl th{
	background: rgba(0,0,0,0.1);
	padding: 5px 8px;
}

.budgettbl tr{
}

.budgettbl td{
	padding: 5px 8px;
	border-bottom: 1px solid rgba(0,0,0,0.2);
}

.budgetreport-forecast-table td {
	vertical-align: top;
}

.budgetreport-modal-open {
	margin: 0 8px 8px 0;
}

.budgetreport-forecast-subtable {
	width: 100%;
	border-collapse: collapse;
}

.budgetreport-forecast-subtable th,
.budgetreport-forecast-subtable td {
	padding: 4px 6px;
	border-bottom: 1px solid rgba(0,0,0,0.12);
}

.budgetreport-forecast-extra {
	margin-top: 20px;
}

.budgetreport-forecast-subtitle {
	margin-top: 16px;
}

.budgetreport-modal {
	display: none;
	position: fixed;
	inset: 0;
	z-index: 2000;
}

.budgetreport-modal.budgetreport-modal-is-open {
	display: block;
}

.budgetreport-modal-backdrop {
	position: absolute;
	inset: 0;
	background: rgba(0, 0, 0, 0.35);
}

.budgetreport-modal-dialog {
	position: relative;
	max-width: min(1000px, calc(100vw - 32px));
	max-height: calc(100vh - 32px);
	margin: 16px auto;
	background: #fff;
	border-radius: 4px;
	box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
	overflow: hidden;
}

.budgetreport-modal-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	padding: 12px 16px;
	border-bottom: 1px solid rgba(0, 0, 0, 0.12);
}

.budgetreport-modal-title {
	font-weight: 600;
}

.budgetreport-modal-body {
	max-height: calc(100vh - 120px);
	overflow: auto;
	padding: 16px;
}

@media only screen and (max-width: 980px) {
	.budgetreport-charts-row {
		grid-template-columns: 1fr !important;
	}

	.dashboard_budget,
	.dashboard_budget tbody,
	.dashboard_budget tr,
	.dashboard_budget .budgetreport-summary-cell {
		display: block;
		width: 100%;
	}

	.dashboard_budget .budgetreport-summary-cell {
		border-right: 0;
	}
}
