<?php declare(strict_types=1);

namespace DataHawk\Test\Export;

use PHPUnit\Framework\TestCase;
use DataHawk\Export\ExcelHtmlReportExporter;
use ResourceFoundation\Api\IQueryService;

class ExcelHtmlReportExporterTest extends TestCase {
        use ExportTestHelperTrait;

        public function testGetNameReturnsExpectedValue(): void {
                $this->assertSame('excelhtmlreportexporter', ExcelHtmlReportExporter::getName());
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

                $exp = new ExcelHtmlReportExporter($svc);
                $exp->setExportQuery(['q' => 1]);

                $this->assertSame($result, $exp->getResult());
        }

        public function testToStringThrowsIfNoResultSet(): void {
                $svc = $this->createStub(IQueryService::class);
                $exp = new ExcelHtmlReportExporter($svc);

                $this->expectException(\RuntimeException::class);
                $this->expectExceptionMessage('No query result set for export.');
                $exp->toString();
        }

        public function testToStringEscapesValuesAndContainsExcelHtmlShell(): void {
                $svc = $this->createStub(IQueryService::class);

                $result = $this->makeQueryResult(
                        columns: [['name' => 'col']],
                        rows: [['<b>bad</b>']]
                );

                $exp = new ExcelHtmlReportExporter($svc);
                $exp->setResult($result);

                $html = $exp->toString();

                $this->assertStringContainsString('xmlns:x="urn:schemas-microsoft-com:office:excel"', $html);
                $this->assertStringContainsString('&lt;b&gt;bad&lt;/b&gt;', $html);
                $this->assertStringContainsString('<table', $html);
        }

        public function testToFileWritesXlsHtml(): void {
                $svc = $this->createStub(IQueryService::class);

                $result = $this->makeQueryResult(
                        columns: [['name' => 'a']],
                        rows: [[1]]
                );

                $exp = new ExcelHtmlReportExporter($svc);
                $exp->setResult($result);

                $file = $this->tempFilePath('excel_') . '.xls';
                $exp->toFile($file);

                $this->assertFileExists($file);
                $this->assertNotSame('', (string)file_get_contents($file));
                @unlink($file);
        }

        public function testMimeTypeAndExtensionAndToSql(): void {
                $svc = $this->createStub(IQueryService::class);
                $exp = new ExcelHtmlReportExporter($svc);

                $this->assertSame('application/vnd.ms-excel', $exp->getMimeType());
                $this->assertSame('xls', $exp->getFileExtension());

                $exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: 'SELECT X'));
                $this->assertSame('SELECT X', $exp->toSql());

                $exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: null));
                $this->assertSame('', $exp->toSql());
        }
}
