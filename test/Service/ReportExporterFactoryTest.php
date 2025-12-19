<?php declare(strict_types=1);

namespace DataHawk\Test\Service;

require_once __DIR__ . '/FakeClassMap.php';

use PHPUnit\Framework\TestCase;
use DataHawk\Service\ReportExporterFactory;
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
