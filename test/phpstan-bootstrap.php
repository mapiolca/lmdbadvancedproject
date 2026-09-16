<?php
// Analysis loads native declarations, never main.inc.php or a live database.
$nativeRoot = getenv('DOLIBARR_ROOT') ?: dirname(__DIR__, 2).'/dolibarr/htdocs';
if (!is_dir($nativeRoot)) { throw new RuntimeException('Set DOLIBARR_ROOT to a Dolibarr htdocs directory'); }
define('DOL_DOCUMENT_ROOT', realpath($nativeRoot));
define('DOL_VERSION', '20.0.0');
define('DOL_URL_ROOT', '');
define('MAIN_DB_PREFIX', 'llx_');
define('PHPEXCELNEW_PATH', DOL_DOCUMENT_ROOT.'/includes/phpoffice/phpspreadsheet/src/PhpSpreadsheet/');
