<?php

use PHPUnit\Framework\TestCase;

final class KontrolAutoloadTest extends TestCase {
	private const ROOT = __DIR__ . '/../src';

	public function test_dashboard_bootstrap_registers_base_autoloader(): void {
		$guiconfig = file_get_contents(self::ROOT . '/usr/local/www/guiconfig.inc');

		$this->assertStringContainsString(
			'require_once("/usr/local/Kontrol/include/autoload.inc.php");',
			$guiconfig
		);
	}

	public function test_upgrade_with_stale_composer_map_loads_filesystems(): void {
		/* Simulate the 2.7.2 Composer loader left active during package upgrade:
		 * it knows pfSense, but has no Kontrol PSR-4 entry. */
		$legacyLoader = static function ($class): void {
			if (str_starts_with($class, 'pfSense\\')) {
				return;
			}
		};
		spl_autoload_register($legacyLoader);
		require_once(self::ROOT . '/usr/local/Kontrol/include/autoload.inc.php');

		$this->assertTrue(class_exists(Kontrol\Services\Filesystem\Filesystems::class));
		$this->assertTrue(class_exists(Kontrol\Services\Filesystem\Provider\SystemProvider::class));

		spl_autoload_unregister($legacyLoader);
	}

	public function test_disks_widget_uses_packaged_filesystem_service(): void {
		$widget = file_get_contents(self::ROOT . '/usr/local/www/widgets/include/disks.inc');

		$this->assertStringContainsString('Kontrol\\Services\\Filesystem', $widget);
		$this->assertStringContainsString('$filesystems = new Filesystems();', $widget);
		$this->assertFileExists(self::ROOT . '/usr/local/Kontrol/include/Services/Filesystem/Filesystems.php');
		$this->assertFileExists(self::ROOT . '/usr/local/Kontrol/include/Services/Filesystem/Filesystem.php');
		$this->assertFileExists(self::ROOT . '/usr/local/Kontrol/include/Services/Filesystem/Provider/SystemProvider.php');
	}

	public function test_disks_widget_renders_with_dashboard_dependencies(): void {
		if (!function_exists('gettext')) {
			eval('function gettext($message) { return $message; }');
		}

		$widget = file_get_contents(self::ROOT . '/usr/local/www/widgets/include/disks.inc');
		$widget = preg_replace('/^<\?php\s*/', '', $widget);
		$widget = str_replace("require_once('vendor/autoload.php');", '', $widget);
		/* Rendering helpers do not need to query the host running the test. */
		$widget = str_replace('$filesystems = new Filesystems();', '$filesystems = null;', $widget);
		eval($widget);

		$html = disks_compose_progressbar(76)->toHtml();
		$this->assertStringContainsString('progress-bar-danger', $html);
		$this->assertStringContainsString('aria-valuenow="76"', $html);
	}

	public function test_composer_and_base_package_maps_agree(): void {
		$composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true, flags: JSON_THROW_ON_ERROR);

		$this->assertSame('//usr/local/Kontrol/include/', $composer['autoload']['psr-4']['Kontrol\\']);
		$this->assertFileExists(self::ROOT . '/usr/local/Kontrol/include/autoload.inc.php');
	}

	public function test_core_package_default_plist_includes_autoloader(): void {
		$packager = file_get_contents(__DIR__ . '/../build/scripts/create_core_pkg.sh');

		$this->assertStringContainsString('find ${froot} ${filter} -type f -or -type l', $packager);
		$this->assertFileExists(self::ROOT . '/usr/local/Kontrol/include/autoload.inc.php');
	}
}
