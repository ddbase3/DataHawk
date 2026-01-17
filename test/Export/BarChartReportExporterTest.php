<?php declare(strict_types=1);

/**
 * Filename: plugin/DataHawk/test/Export/BarChartReportExporterTest.php
 */

namespace DataHawk\Test\Export;

use PHPUnit\Framework\TestCase;
use DataHawk\Export\BarChartReportExporter;
use ResourceFoundation\Api\IQueryService;
use Base3\Api\IAssetResolver;

class BarChartReportExporterTest extends TestCase {
	use ExportTestHelperTrait;

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('barchartreportexporter', BarChartReportExporter::getName());
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
		$assets->method('resolve')->willReturn('/dummy/chart.js');

		$exp = new BarChartReportExporter($svc, $assets);
		$exp->setExportQuery(['q' => 1]);

		$this->assertSame($result, $exp->getResult());
	}

	public function testToStringThrowsIfNoResultSet(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturn('/dummy/chart.js');

		$exp = new BarChartReportExporter($svc, $assets);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('No query result set for export.');
		$exp->toString();
	}

	public function testToStringOutputsBarChartAndHexEncodesRowJson(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturn('/dummy/chart.js');

		$result = $this->makeQueryResult(
			columns: [['name' => 'label'], ['name' => 'value']],
			rows: [['label' => '<b>X</b>', 'value' => 7]],
			debugSql: null
		);

		$exp = new BarChartReportExporter($svc, $assets);
		$exp->setResult($result);

		$html = $exp->toString();

		$this->assertMatchesRegularExpression('/<canvas id="bar[a-z0-9]+">/i', $html);
		$this->assertStringContainsString('type: "bar"', $html);
		$this->assertStringContainsString('new Chart', $html);

		// AssetResolver usage is present
		$this->assertStringContainsString('AssetLoader.loadScriptAsync("/dummy/chart.js")', $html);

		// JSON_HEX_TAG encodes '<' and '>' as \u003C / \u003E
		$this->assertStringContainsString('\u003Cb\u003EX\u003C\/b\u003E', $html);
	}

	public function testToFileWritesHtml(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturn('/dummy/chart.js');

		$result = $this->makeQueryResult(
			columns: [['name' => 'label'], ['name' => 'value']],
			rows: [['label' => 'A', 'value' => 1]],
			debugSql: null
		);

		$exp = new BarChartReportExporter($svc, $assets);
		$exp->setResult($result);

		$file = $this->tempFilePath('barchart_') . '.html';
		$exp->toFile($file);

		$this->assertFileExists($file);
		$this->assertNotSame('', (string)file_get_contents($file));
		@unlink($file);
	}

	public function testMimeTypeAndExtensionAndToSql(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturn('/dummy/chart.js');

		$exp = new BarChartReportExporter($svc, $assets);

		$this->assertSame('text/html', $exp->getMimeType());
		$this->assertSame('html', $exp->getFileExtension());

		$exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: 'SELECT X'));
		$this->assertSame('SELECT X', $exp->toSql());

		$exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: null));
		$this->assertSame('', $exp->toSql());
	}
}
