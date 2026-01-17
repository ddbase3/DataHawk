<?php declare(strict_types=1);

/**
 * Filename: plugin/DataHawk/test/Export/PieChartReportExporterTest.php
 */

namespace DataHawk\Test\Export;

use PHPUnit\Framework\TestCase;
use DataHawk\Export\PieChartReportExporter;
use ResourceFoundation\Api\IQueryService;
use Base3\Api\IAssetResolver;

class PieChartReportExporterTest extends TestCase {
	use ExportTestHelperTrait;

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('piechartreportexporter', PieChartReportExporter::getName());
	}

	public function testSetExportQueryCallsQueryServiceAndStoresResult(): void {
		$result = $this->makeQueryResult(
			columns: [['name' => 'label'], ['name' => 'value']],
			rows: [['label' => 'A', 'value' => 1]],
			debugSql: 'SELECT 1'
		);

		$svc = $this->createMock(IQueryService::class);
		$svc->expects($this->once())
			->method('executeQuery')
			->with(['q' => 1])
			->willReturn($result);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static function (string $path): string {
			return $path;
		});

		$exp = new PieChartReportExporter($svc, $assets);
		$exp->setExportQuery(['q' => 1]);

		$this->assertSame($result, $exp->getResult());
	}

	public function testToStringThrowsIfNoResultSet(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $path): string => $path);

		$exp = new PieChartReportExporter($svc, $assets);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('No query result set for export.');
		$exp->toString();
	}

	public function testToStringOutputsDoughnutChart(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $path): string => $path);

		$result = $this->makeQueryResult(
			columns: [['name' => 'label'], ['name' => 'value']],
			rows: [
				['label' => 'A', 'value' => 2],
				['label' => 'B', 'value' => 3],
			],
			debugSql: null
		);

		$exp = new PieChartReportExporter($svc, $assets);
		$exp->setResult($result);

		$html = $exp->toString();

		$this->assertMatchesRegularExpression('/<canvas id="dt[a-z0-9]+">/i', $html);
		$this->assertStringContainsString('type: "doughnut"', $html);
		$this->assertStringContainsString('new Chart', $html);
	}

	public function testToFileWritesHtml(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $path): string => $path);

		$result = $this->makeQueryResult(
			columns: [['name' => 'label'], ['name' => 'value']],
			rows: [['label' => 'A', 'value' => 1]],
			debugSql: null
		);

		$exp = new PieChartReportExporter($svc, $assets);
		$exp->setResult($result);

		$file = $this->tempFilePath('pie_') . '.html';
		$exp->toFile($file);

		$this->assertFileExists($file);
		$this->assertNotSame('', (string)file_get_contents($file));
		@unlink($file);
	}

	public function testMimeTypeAndExtensionAndToSql(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $path): string => $path);

		$exp = new PieChartReportExporter($svc, $assets);

		$this->assertSame('text/html', $exp->getMimeType());
		$this->assertSame('html', $exp->getFileExtension());

		$exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: 'SELECT X'));
		$this->assertSame('SELECT X', $exp->toSql());

		$exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: null));
		$this->assertSame('', $exp->toSql());
	}
}
