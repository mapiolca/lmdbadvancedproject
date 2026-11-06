<?php
/* Validate the actual release ZIP using an unmodified native Dolibarr validator. */
if (PHP_SAPI !== 'cli') { exit(1); }
$root = realpath(getenv('DOLIBARR_ROOT') ?: __DIR__.'/.cache/core20/htdocs');
$package = isset($argv[1]) ? realpath($argv[1]) : false;
if (!$root || !$package || !class_exists('ZipArchive')) {
	throw new RuntimeException('Provide DOLIBARR_ROOT, an existing ZIP and the ZipArchive extension.');
}
define('DOL_DOCUMENT_ROOT', $root);
define('DOL_URL_ROOT', '');
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/conf.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/translate.class.php';
if (!function_exists('validateZipFile')) {
	throw new RuntimeException('This core has no validateZipFile(); use the native Dolibarr 24+ validator.');
}
$conf = new Conf();
$conf->global = new stdClass();
$conf->file->dol_document_root = array('main' => DOL_DOCUMENT_ROOT);
$langs = new Translate(DOL_DOCUMENT_ROOT, $conf);
$langs->setDefaultLang('en_US');
$langs->load('admin');
$zip = new ZipArchive();
if ($zip->open($package) !== true) { throw new RuntimeException('Cannot open package.'); }
$result = validateZipFile($zip, basename($package), basename($package).'-'.bin2hex(random_bytes(6)), $langs);
if ($result['error'] !== 0) {
	throw new RuntimeException('Native ZIP validation failed: '.strip_tags((string) $result['errormsg']));
}
print 'Native ZIP validation passed: '.basename($package).PHP_EOL;
