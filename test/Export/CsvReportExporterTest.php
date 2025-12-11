<?php declare(strict_types=1);

namespace DataHawk\Test\Export;

use PHPUnit\Framework\TestCase;
use DataHawk\Export\CsvReportExporter;
use ResourceFoundation\Api\IQueryService;

class CsvReportExporterTest extends TestCase {
        use ExportTestHelperTrait;

        public function testGetNameReturnsExpectedValue(): void {
                $this->assertSame('csvreportexporter', CsvReportExporter::getName());
        }

        public function testSetExportQueryCallsQueryServiceAndStoresResult(): void {
                $result = $this->makeQueryResult(
                        columns: [['name' => 'a']],
                        rows: [[1]],
                        debugSql: 'SELECT 1'
                );

                // <-- HIER: Mock, weil expects()
                $svc = $this->createMock(IQueryService::class);
                $svc->expects($this->once())
                        ->method('executeQuery')
                        ->with(['q' => 1])
                        ->willReturn($result);

                $exp = new CsvReportExporter($svc);
                $exp->setExportQuery(['q' => 1]);

                $this->assertSame($result, $exp->getResult());
        }

        public function testToStringThrowsIfNoResultSet(): void {
                // <-- Stub reicht, Service wird nicht verwendet
                $svc = $this->createStub(IQueryService::class);
                $exp = new CsvReportExporter($svc);

                $this->expectException(\RuntimeException::class);
                $this->expectExceptionMessage('No query result set for export.');
                $exp->toString();
        }

        public function testToStringRendersHeadersAndRowsAndSupportsAssociativeRows(): void {
                $svc = $this->createStub(IQueryService::class);

                $result = $this->makeQueryResult(
                        columns: [['name' => 'name'], ['name' => 'count']],
                        rows: [
                                ['name' => 'Alice', 'count' => 2],
                                ['Bob', 5],
                        ]
                );

                $exp = new CsvReportExporter($svc);
                $exp->setResult($result);

                $csv = $exp->toString();

                $this->assertStringContainsString("name,count", $csv);
                $this->assertStringContainsString("Alice,2", $csv);
                $this->assertStringContainsString("Bob,5", $csv);
        }

        public function testToFileWritesCsv(): void {
                $svc = $this->createStub(IQueryService::class);

                $result = $this->makeQueryResult(
                        columns: [['name' => 'a']],
                        rows: [[1]]
                );

                $exp = new CsvReportExporter($svc);
                $exp->setResult($result);

                $file = $this->tempFilePath('csv_') . '.csv';
                $exp->toFile($file);

                $this->assertFileExists($file);
                $this->assertNotSame('', (string)file_get_contents($file));
                @unlink($file);
        }

        public function testMimeTypeAndExtensionAndToSql(): void {
                $svc = $this->createStub(IQueryService::class);
                $exp = new CsvReportExporter($svc);

                $this->assertSame('text/csv', $exp->getMimeType());
                $this->assertSame('csv', $exp->getFileExtension());

                $exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: 'SELECT X'));
                $this->assertSame('SELECT X', $exp->toSql());

                $exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: null));
                $this->assertSame('', $exp->toSql());
        }
}
