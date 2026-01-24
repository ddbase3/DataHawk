<?php declare(strict_types=1);

namespace DataHawk\Test\Service;

use PHPUnit\Framework\TestCase;
use Base3\Test\Core\ClassMapStub;
use DataHawk\Service\ReportExporterFactory;
use DataHawk\Api\IReportExporter;

class ReportExporterFactoryTest extends TestCase {

	public function testCreateExporterReturnsExporterFromClassMap(): void {
		$exporter = $this->createStub(IReportExporter::class);

		// The factory resolves via getInstanceByInterfaceName(interface, name),
		// so we must register BOTH: name -> class and interface -> class.
		$class = get_class($exporter);

		$classmap = new ClassMapStub();
		$classmap->registerName('csvreportexporter', $class);
		$classmap->registerInterface(IReportExporter::class, $class);

		$factory = new ReportExporterFactory($classmap);

		$created = $factory->createExporter('csvreportexporter');
		$this->assertInstanceOf(IReportExporter::class, $created);

		// Note: DI-free ClassMapStub instantiates a NEW instance of the registered class.
		// So we cannot assertSame($exporter, $created).
	}

	public function testCreateExporterThrowsWhenClassMapReturnsInvalidInstance(): void {
		$classmap = new ClassMapStub();
		$classmap->registerName('nope', \stdClass::class);
		$classmap->registerInterface(IReportExporter::class, \stdClass::class);

		$factory = new ReportExporterFactory($classmap);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage("Report exporter 'nope' could not be instantiated or is invalid");
		$factory->createExporter('nope');
	}
}
