<?php

$mtime = microtime();
$mtime = explode(' ', $mtime);
$mtime = $mtime[1] + $mtime[0];
$tstart = $mtime;
set_time_limit(0);

header('Content-Type:text/html;charset=utf-8');

require_once 'build.config.php';

/* define sources */
$root = dirname(dirname(__FILE__)) . '/';
$sources = array(
	'root'         => $root,
	'build'        => $root . '_build/',
	'data'         => $root . '_build/data/',
	'resolvers'    => $root . '_build/resolvers/',
	'lexicon'      => $root . 'core/components/' . PKG_NAME_LOWER . '/lexicon/',
	'docs'         => $root . 'core/components/' . PKG_NAME_LOWER . '/docs/',
	'source_core'  => $root . 'core/components/' . PKG_NAME_LOWER,
	'source_model' => $root . 'core/model/modx/modmodx.class.php',
	'source_index' => $root . 'modmodxindex.php',
);

unset($root);

require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
require_once $sources['build'] . '/includes/functions.php';

$modx = new modX();
$modx->initialize('mgr');
$modx->setLogLevel(modX::LOG_LEVEL_INFO);
$modx->setLogTarget('ECHO');
$modx->getService('error', 'error.modError');
$modx->loadClass('transport.modPackageBuilder', '', false, true);
if (!XPDO_CLI_MODE) {
	echo '<pre>';
}

$builder = new modPackageBuilder($modx);
$builder->createPackage(PKG_NAME, PKG_VERSION, PKG_RELEASE);
$builder->registerNamespace(PKG_NAME_LOWER, false, true, PKG_NAMESPACE_PATH);

$modx->log(modX::LOG_LEVEL_INFO, 'Created Transport Package and Namespace.');

/* create category */
$modx->log(xPDO::LOG_LEVEL_INFO, 'Created category.');
/* @var modCategory $category */
$category = $modx->newObject('modCategory');
$category->set('category', PKG_NAME);
/* create category vehicle */
$attr = array(
	xPDOTransport::UNIQUE_KEY      => 'category',
	xPDOTransport::PRESERVE_KEYS   => false,
	xPDOTransport::UPDATE_OBJECT   => true,
	xPDOTransport::RELATED_OBJECTS => true,
);

$vehicle = $builder->createVehicle($category, $attr);

/* now pack in resolvers */

$vehicle->resolve('file', array(
	'source' => $sources['source_core'],
	'target' => "return MODX_CORE_PATH . 'components/';",
));
$vehicle->resolve('file', array(
	'source' => $sources['source_model'],
	'target' => "return MODX_CORE_PATH . 'model/modx/';",
));
$vehicle->resolve('file', array(
	'source' => $sources['source_index'],
	'target' => "return MODX_BASE_PATH;",
));

foreach ($BUILD_RESOLVERS as $resolver) {
	if ($vehicle->resolve('php', array('source' => $sources['resolvers'] . 'resolve.' . $resolver . '.php'))) {
		$modx->log(modX::LOG_LEVEL_INFO, 'Added resolver "' . $resolver . '" to category.');
	} else {
		$modx->log(modX::LOG_LEVEL_INFO, 'Could not add resolver "' . $resolver . '" to category.');
	}
}

flush();
$builder->putVehicle($vehicle);

/* now pack in the license file, readme and setup options */
$builder->setPackageAttributes(array(
	'changelog' => file_get_contents($sources['docs'] . 'changelog.txt'),
	'license'   => file_get_contents($sources['docs'] . 'license.txt'),
	'readme'    => file_get_contents($sources['docs'] . 'readme.txt'),
));
$modx->log(modX::LOG_LEVEL_INFO, 'Added package attributes and setup options.');

/* zip up package */
$modx->log(modX::LOG_LEVEL_INFO, 'Packing up transport package zip...');
$builder->pack();

$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$tend = $mtime;
$totalTime = ($tend - $tstart);
$totalTime = sprintf("%2.4f s", $totalTime);

$signature = $builder->getSignature();
if (defined('PKG_AUTO_INSTALL') && PKG_AUTO_INSTALL) {
	$sig = explode('-', $signature);
	$versionSignature = explode('.', $sig[1]);

	/* @var modTransportPackage $package */
	if (!$package = $modx->getObject('transport.modTransportPackage', array('signature' => $signature))) {
		$package = $modx->newObject('transport.modTransportPackage');
		$package->set('signature', $signature);
		$package->fromArray(array(
			'created'       => date('Y-m-d h:i:s'),
			'updated'       => null,
			'state'         => 1,
			'workspace'     => 1,
			'provider'      => 0,
			'source'        => $signature . '.transport.zip',
			'package_name'  => PKG_NAME,
			'version_major' => $versionSignature[0],
			'version_minor' => !empty($versionSignature[1]) ? $versionSignature[1] : 0,
			'version_patch' => !empty($versionSignature[2]) ? $versionSignature[2] : 0,
		));
		if (!empty($sig[2])) {
			$r = preg_split('/([0-9]+)/', $sig[2], -1, PREG_SPLIT_DELIM_CAPTURE);
			if (is_array($r) && !empty($r)) {
				$package->set('release', $r[0]);
				$package->set('release_index', (isset($r[1]) ? $r[1] : '0'));
			} else {
				$package->set('release', $sig[2]);
			}
		}
		$package->save();
	}

	if ($package->install()) {
		$modx->runProcessor('system/clearcache');
	}
}
if (!empty($_GET['download'])) {
	echo '<script>document.location.href = "/core/packages/' . $signature . '.transport.zip' . '";</script>';
}

$modx->log(modX::LOG_LEVEL_INFO, "\n<br />Execution time: {$totalTime}\n");
if (!XPDO_CLI_MODE) {
	echo '</pre>';
}
