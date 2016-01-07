<?php

/** @var $modx modX */
if (!$modx = $object->xpdo AND !$object->xpdo instanceof modX) {
	return true;
}

/** @var $options */
switch ($options[xPDOTransport::PACKAGE_ACTION]) {
	case xPDOTransport::ACTION_INSTALL:
	case xPDOTransport::ACTION_UPGRADE:
		$indexFile = MODX_BASE_PATH . 'index.php';
		$indexHandle = fopen($indexFile, "r+");
		$indexContent = str_replace("<?php", "", fread($indexHandle, 5000));
		if (strpos($indexContent, 'modmodxindex') === false) {
			fseek($indexHandle, 0);
			$success = fwrite($indexHandle, preg_replace("#[\r\t]+#is", '', '<?php
					if (@file_exists($_SERVER["DOCUMENT_ROOT"] . "/modmodxindex.php")) {
						@include_once($_SERVER["DOCUMENT_ROOT"] . "/modmodxindex.php");
						run();
					}
					') . $indexContent);
			if ($success === false) {
				$modx->log(modX::LOG_LEVEL_INFO, '[modModX] Error write index.php');
			}
		}
		fclose($indexHandle);

		break;
	case xPDOTransport::ACTION_UNINSTALL:
		break;
}

return true;


