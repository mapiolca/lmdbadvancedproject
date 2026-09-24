<?php
// Simulate module discovery during an update, using the native module base class.
if (PHP_SAPI !== 'cli') { exit(1); }
$root = realpath(getenv('DOLIBARR_ROOT') ?: dirname(__DIR__, 2).'/dolibarr/htdocs');
if (!$root) { throw new RuntimeException('DOLIBARR_ROOT must point to a native htdocs checkout.'); }
define('DOL_DOCUMENT_ROOT', $root);
define('DOL_URL_ROOT', '');
define('DOL_VERSION', getenv('DOLIBARR_VERSION') ?: '20.0.0');
define('MAIN_DB_PREFIX', 'test_');
require DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';

$preloaded = ($argv[1] ?? '') === 'preloaded';
$legacy = $preloaded || ($argv[1] ?? '') === 'legacy';
$fixture = __DIR__.'/.cache/descriptor-bootstrap-'.bin2hex(random_bytes(6));
if (!mkdir($fixture.'/core/modules', 0777, true) || !mkdir($fixture.'/class')) {
	throw new RuntimeException('Cannot create isolated module fixture.');
}
if (!copy(__DIR__.'/../core/modules/modLmdbAdvancedProject.class.php', $fixture.'/core/modules/modLmdbAdvancedProject.class.php')) {
	throw new RuntimeException('Cannot copy descriptor under test.');
}
if ($legacy) {
	// Contract of the older class relevant to discovery: no minimum-version constants.
	if (file_put_contents($fixture.'/class/lmdbadvancedprojectcompatibility.class.php', '<?php class LmdbAdvancedProjectCompatibility {}') === false) {
		throw new RuntimeException('Cannot create legacy compatibility fixture.');
	}
	if ($preloaded) { require $fixture.'/class/lmdbadvancedprojectcompatibility.class.php'; }
}
$conf = (object) array('global' => new stdClass(), 'entity' => 1, 'currency' => 'EUR', 'modules' => array('dynamicsprices' => 1));
$conf->dynamicsprices = (object) array('enabled' => 1);
$langs = new class { public function trans($key) { return $key; } };
$db = new stdClass(); // No database operation is needed to discover this descriptor.
require $fixture.'/core/modules/modLmdbAdvancedProject.class.php';
$module = new modLmdbAdvancedProject($db);
if ($module->phpmin !== array(8, 0, 0) || $module->need_dolibarr_version !== array(20, 0, 0)
	|| $module->numero !== 450021 || $module->version !== '1.5.0'
	|| $module->config_page_url !== array('setup.php@lmdbadvancedproject')) {
	throw new RuntimeException('Native module discovery metadata changed.');
}
if (class_exists('LmdbAdvancedProjectCompatibility', false) !== $preloaded) {
	throw new RuntimeException('Descriptor must not load the application compatibility class.');
}
echo 'Descriptor discovery passed with '.($preloaded ? 'preloaded legacy class' : ($legacy ? 'legacy compatibility file' : 'no compatibility file')).'.'.PHP_EOL;
