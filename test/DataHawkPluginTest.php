<?php declare(strict_types=1);

namespace DataHawk\Test;

use PHPUnit\Framework\TestCase;
use DataHawk\DataHawkPlugin;
use Base3\Api\IContainer;
use DataHawk\Api\IReportExporterFactory;
use ResourceFoundation\Api\IQueryCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Api\IQueryService;

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
			IReportExporterFactory::class,
		];

		foreach ($expected as $id) {
			$found = false;

			foreach ($calls as $call) {
				if ($call['name'] !== $id) {
					continue;
				}

				$this->assertSame(IContainer::SHARED | IContainer::NOOVERWRITE, $call['flags']);
				$this->assertIsCallable($call['definition'], "Service {$id} should be registered as a factory (callable)");
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
