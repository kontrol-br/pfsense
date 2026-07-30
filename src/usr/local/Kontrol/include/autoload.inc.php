<?php
/*
 * Kontrol base class autoloader.
 *
 * Keep first-party classes loadable independently of the generated Composer
 * metadata.  Composer dependencies are packaged separately and may be
 * replaced before Kontrol-base during an upgrade.
 */

spl_autoload_register(function ($class) {
	$prefix = 'Kontrol\\';

	if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
		return;
	}

	$file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

	if (is_file($file)) {
		require_once($file);
	}
});
