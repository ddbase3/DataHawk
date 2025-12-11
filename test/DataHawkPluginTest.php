<?php declare(strict_types=1);

namespace DataHawk\Test;

use PHPUnit\Framework\TestCase;
use DataHawk\DataHawkPlugin;
use Base3\Api\IContainer;
use Base3\Api\IClassMap;
use Base3\Configuration\Api\IConfiguration;
use DataHawk\Api\IReportExporterFactory;
use ResourceFoundation\Api\IQueryCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Api\IQueryService;

class DataHawkPluginTest extends TestCase {

        public function testGetNameReturnsExpectedValue(): void {
                $this->assertSame('datahawkplugin', DataHawkPlugin::getName());
        }

        public function testInitRegistersPluginInContainerAsShared(): void {
                $container = new FakeContainer();
                $plugin = new DataHawkPlugin($container);

                $plugin->init();

                $this->assertTrue($container->has(DataHawkPlugin::getName()));
                $this->assertSame(IContainer::SHARED, $container->getFlags(DataHawkPlugin::getName()));
                $this->assertSame($plugin, $container->get(DataHawkPlugin::getName()));
        }

        public function testInitRegistersServicesAsSharedAndNoOverwrite(): void {
                $container = new FakeContainer();
                $plugin = new DataHawkPlugin($container);

                $plugin->init();

                $expected = [
                        IQuerySchemaProvider::class,
                        IQueryCompiler::class,
                        IQueryService::class,
                        IReportExporterFactory::class,
                ];

                foreach ($expected as $id) {
                        $this->assertTrue($container->has($id), "Container should have service: {$id}");

                        $flags = $container->getFlags($id);
                        $this->assertSame(
                                IContainer::SHARED | IContainer::NOOVERWRITE,
                                $flags,
                                "Service {$id} should be registered as SHARED|NOOVERWRITE"
                        );

                        $definition = $container->getRawDefinition($id);
                        $this->assertIsCallable($definition, "Service {$id} should be registered as a factory (callable)");
                }
        }

        public function testCheckDependenciesReturnsOkIfResourceFoundationPluginIsInstalled(): void {
                $container = new FakeContainer();
                $container->set('resourcefoundationplugin', new \stdClass(), 0);

                $plugin = new DataHawkPlugin($container);

                $result = $plugin->checkDependencies();

                $this->assertIsArray($result);
                $this->assertSame('Ok', $result['resourcefoundationplugin_installed'] ?? null);
        }

        public function testCheckDependenciesReturnsErrorIfResourceFoundationPluginIsMissing(): void {
                $container = new FakeContainer();

                $plugin = new DataHawkPlugin($container);

                $result = $plugin->checkDependencies();

                $this->assertIsArray($result);
                $this->assertSame(
                        'resourcefoundationplugin not installed',
                        $result['resourcefoundationplugin_installed'] ?? null
                );
        }
}

class FakeContainer implements IContainer {

        private array $items = [];
        private array $flags = [];

        public function getServiceList(): array {
                return array_keys($this->items);
        }

        public function set(string $name, $classDefinition, $flags = 0): IContainer {
                $this->items[$name] = $classDefinition;
                $this->flags[$name] = (int)$flags;
                return $this;
        }

        public function remove(string $name) {
                unset($this->items[$name], $this->flags[$name]);
        }

        public function has(string $name): bool {
                return array_key_exists($name, $this->items);
        }

        public function get(string $name) {
                return $this->items[$name] ?? null;
        }

        /**
         * Test helper: returns the flags used during ->set()
         */
        public function getFlags(string $name): ?int {
                return $this->flags[$name] ?? null;
        }

        /**
         * Test helper: returns the raw definition passed into ->set()
         * (in this plugin these are closures for service factories).
         */
        public function getRawDefinition(string $name): mixed {
                return $this->items[$name] ?? null;
        }
}
