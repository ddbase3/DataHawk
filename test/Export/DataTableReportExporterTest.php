<?php declare(strict_types=1);

/**
 * Filename: plugin/DataHawk/test/Export/DataTableReportExporterTest.php
 */

namespace DataHawk\Test\Export;

use PHPUnit\Framework\TestCase;
use DataHawk\Export\DataTableReportExporter;
use ResourceFoundation\Api\IQueryService;
use Base3\Api\IAssetResolver;

class DataTableReportExporterTest extends TestCase {
	use ExportTestHelperTrait;

	public function testGetNameReturnsExpectedValue(): void {
		$this->assertSame('datatablereportexporter', DataTableReportExporter::getName());
	}

	public function testSetExportQueryCallsQueryServiceAndStoresResult(): void {
		$result = $this->makeQueryResult(
			columns: [['name' => 'a']],
			rows: [[1]],
			debugSql: 'SELECT 1'
		);

		$svc = $this->createMock(IQueryService::class);
		$svc->expects($this->once())
			->method('executeQuery')
			->with(['q' => 1])
			->willReturn($result);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static function (string $path): string {
			// keep it simple: exporter expects resolved string to be inlined into JS
			return $path;
		});

		$exp = new DataTableReportExporter($svc, $assets);
		$exp->setExportQuery(['q' => 1]);

		$this->assertSame($result, $exp->getResult());
	}

	public function testToStringThrowsIfNoResultSet(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $path): string => $path);

		$exp = new DataTableReportExporter($svc, $assets);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('No query result set for export.');
		$exp->toString();
	}

	public function testToStringBuildsColumnsJsonAndLoadsAssets(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $path): string => $path);

		$result = $this->makeQueryResult(
			columns: [['name' => 'name'], ['name' => 'count']],
			rows: [['name' => 'Alice', 'count' => 2]],
			debugSql: null
		);

		$exp = new DataTableReportExporter($svc, $assets);
		$exp->setResult($result);

		$html = $exp->toString();

		$this->assertMatchesRegularExpression('/<div id="dt[a-z0-9]+">/i', $html);
		$this->assertStringContainsString('jqueryDataTable', $html);

		// AssetLoader calls include resolved paths (we return same path)
		$this->assertStringContainsString('loadScriptAsync("plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.js")', $html);
		$this->assertStringContainsString('loadCssAsync("plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.css")', $html);

		// Columns mapping uses key/label from column names
		$this->assertStringContainsString('"key":"name"', $html);
		$this->assertStringContainsString('"label":"name"', $html);
		$this->assertStringContainsString('"key":"count"', $html);
		$this->assertStringContainsString('"label":"count"', $html);
	}

	public function testToFileWritesHtml(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $path): string => $path);

		$result = $this->makeQueryResult(
			columns: [['name' => 'a']],
			rows: [[1]],
			debugSql: null
		);

		$exp = new DataTableReportExporter($svc, $assets);
		$exp->setResult($result);

		$file = $this->tempFilePath('datatable_') . '.html';
		$exp->toFile($file);

		$this->assertFileExists($file);
		$this->assertNotSame('', (string)file_get_contents($file));
		@unlink($file);
	}

	public function testMimeTypeAndExtensionAndToSql(): void {
		$svc = $this->createStub(IQueryService::class);

		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturnCallback(static fn(string $path): string => $path);

		$exp = new DataTableReportExporter($svc, $assets);

		$this->assertSame('text/html', $exp->getMimeType());
		$this->assertSame('html', $exp->getFileExtension());

		$exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: 'SELECT X'));
		$this->assertSame('SELECT X', $exp->toSql());

		$exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: null));
		$this->assertSame('', $exp->toSql());
	}
}
