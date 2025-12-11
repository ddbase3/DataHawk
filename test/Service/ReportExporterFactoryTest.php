<?php declare(strict_types=1);

namespace DataHawk\Test\Service;

use PHPUnit\Framework\TestCase;
use DataHawk\Service\ReportExporterFactory;
use Base3\Api\IClassMap;
use DataHawk\Api\IReportExporter;

class ReportExporterFactoryTest extends TestCase {

        public function testCreateExporterReturnsExporterFromClassMap(): void {
                $exporter = $this->createStub(IReportExporter::class);

                $classmap = new FakeClassMap();
                $classmap->returnValue = $exporter;

                $factory = new ReportExporterFactory($classmap);

                $created = $factory->createExporter('csvreportexporter');
                $this->assertSame($exporter, $created);

                $this->assertSame(IReportExporter::class, $classmap->lastInterface);
                $this->assertSame('csvreportexporter', $classmap->lastName);
        }

        public function testCreateExporterThrowsWhenClassMapReturnsInvalidInstance(): void {
                $classmap = new FakeClassMap();
                $classmap->returnValue = new \stdClass();

                $factory = new ReportExporterFactory($classmap);

                $this->expectException(\RuntimeException::class);
                $this->expectExceptionMessage("Report exporter 'nope' could not be instantiated or is invalid");
                $factory->createExporter('nope');
        }
}

/**
 * Fake that satisfies IClassMap but also provides getInstanceByInterfaceName()
 * because ReportExporterFactory calls it (even though it's not in the interface).
 */
class FakeClassMap implements IClassMap {

        public mixed $returnValue = null;
        public ?string $lastInterface = null;
        public ?string $lastName = null;

        // extra method used by ReportExporterFactory
        public function getInstanceByInterfaceName(string $interface, string $name): mixed {
                $this->lastInterface = $interface;
                $this->lastName = $name;
                return $this->returnValue;
        }

        // IClassMap methods (not used by these tests)
        public function instantiate(string $class) {
                return null;
        }

        public function &getInstances(array $criteria = []) {
                $empty = [];
                return $empty;
        }

        public function getPlugins() {
                return [];
        }
}

