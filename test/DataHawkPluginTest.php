<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of DataHawk for BASE3 Framework.
 *
 * DataHawk extends BASE3 with schema-driven reporting and data queries.
 * Structured query definitions compile to backend-specific statements.
 * Execution uses the shared BASE3 database and resource contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/datahawk
 * https://github.com/ddbase3/DataHawk
 **********************************************************************/

namespace Base3\DataHawk\Test;

use PHPUnit\Framework\TestCase;
use Base3\DataHawk\DataHawkPlugin;
use Base3\CoreFoundation\Api\IContainer;
use Base3\ResourceFoundation\Api\IQueryCompiler;
use Base3\ResourceFoundation\Api\IQuerySchemaProvider;
use Base3\ResourceFoundation\Api\IQueryService;

class DataHawkPluginTest extends TestCase {

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('datahawkplugin', DataHawkPlugin::getName());
	}

	public function testInitRegistersPluginInContainerAsShared(): void {
		$calls = [];

		$container = $this->createStub(IContainer::class);
		$container->method('set')
			->willReturnCallback(function(string $name, $definition, $flags) use (&$calls, $container) {
				$calls[] = [
					'name' => $name,
					'definition' => $definition,
					'flags' => (int)$flags,
				];
				return $container;
			});

		$plugin = new DataHawkPlugin($container);
		$plugin->init();

		$found = false;

		foreach ($calls as $call) {
			if ($call['name'] !== DataHawkPlugin::getName()) {
				continue;
			}

			$this->assertSame(IContainer::SHARED, $call['flags']);
			$this->assertSame($plugin, $call['definition']);
			$found = true;
			break;
		}

		$this->assertTrue($found, 'Container::set() was not called for the plugin itself');
	}

	public function testInitRegistersServicesAsSharedAndNoOverwrite(): void {
		$calls = [];

		$container = $this->createStub(IContainer::class);
		$container->method('set')
			->willReturnCallback(function(string $name, $definition, $flags) use (&$calls, $container) {
				$calls[] = [
					'name' => $name,
					'definition' => $definition,
					'flags' => (int)$flags,
				];
				return $container;
			});

		$plugin = new DataHawkPlugin($container);
		$plugin->init();

		$expected = [
			IQuerySchemaProvider::class,
			IQueryCompiler::class,
			IQueryService::class,
		];

		foreach ($expected as $id) {
			$found = false;

			foreach ($calls as $call) {
				if ($call['name'] !== $id) {
					continue;
				}

				if ($id === IQuerySchemaProvider::class) {
					$this->assertSame(IContainer::ALIAS | IContainer::NOOVERWRITE, $call['flags']);
					$this->assertIsString($call['definition']);
				} else {
					$this->assertSame(IContainer::SHARED | IContainer::NOOVERWRITE, $call['flags']);
					$this->assertIsCallable($call['definition']);
				}
				$found = true;
				break;
			}

			$this->assertTrue($found, "Container::set() was not called for service: {$id}");
		}
	}

	public function testCheckDependenciesReturnsOkIfResourceFoundationPluginIsInstalled(): void {
		$container = $this->createStub(IContainer::class);
		$container->method('get')->with('resourcefoundationplugin')->willReturn(new \stdClass());

		$plugin = new DataHawkPlugin($container);

		$result = $plugin->checkDependencies();

		$this->assertIsArray($result);
		$this->assertSame('Ok', $result['resourcefoundationplugin_installed'] ?? null);
	}

	public function testCheckDependenciesReturnsErrorIfResourceFoundationPluginIsMissing(): void {
		$container = $this->createStub(IContainer::class);
		$container->method('get')->with('resourcefoundationplugin')->willReturn(null);

		$plugin = new DataHawkPlugin($container);

		$result = $plugin->checkDependencies();

		$this->assertIsArray($result);
		$this->assertSame(
			'resourcefoundationplugin not installed',
			$result['resourcefoundationplugin_installed'] ?? null
		);
	}
}
