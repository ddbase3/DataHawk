<?php declare(strict_types=1);

namespace DataHawk\Test\Export;

use PHPUnit\Framework\TestCase;
use DataHawk\Export\JsonReportExporter;
use ResourceFoundation\Api\IQueryService;

class JsonReportExporterTest extends TestCase {
        use ExportTestHelperTrait;

        public function testGetNameReturnsExpectedValue(): void {
                $this->assertSame('jsonreportexporter', JsonReportExporter::getName());
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

                $exp = new JsonReportExporter($svc);
                $exp->setExportQuery(['q' => 1]);

                $this->assertSame($result, $exp->getResult());
        }

        public function testToStringThrowsIfNoResultSet(): void {
                $svc = $this->createStub(IQueryService::class);
                $exp = new JsonReportExporter($svc);

                $this->expectException(\RuntimeException::class);
                $this->expectExceptionMessage('No query result set for export.');
                $exp->toString();
        }

        public function testToStringIncludesDebugSqlOnlyWhenPresent(): void {
                $svc = $this->createStub(IQueryService::class);
                $exp = new JsonReportExporter($svc);

                $exp->setResult($this->makeQueryResult(
                        columns: [['name' => 'a']],
                        rows: [[1]],
                        debugSql: 'SELECT 1'
                ));

                $jsonWith = $exp->toString();
                $this->assertStringContainsString('"debugSql"', $jsonWith);

                $exp->setResult($this->makeQueryResult(
                        columns: [['name' => 'a']],
                        rows: [[1]],
                        debugSql: null
                ));

                $jsonWithout = $exp->toString();
                $this->assertStringNotContainsString('"debugSql"', $jsonWithout);
        }

        public function testToFileWritesJson(): void {
                $svc = $this->createStub(IQueryService::class);

                $exp = new JsonReportExporter($svc);
                $exp->setResult($this->makeQueryResult(
                        columns: [['name' => 'a']],
                        rows: [[1]],
                        debugSql: null
                ));

                $file = $this->tempFilePath('json_') . '.json';
                $exp->toFile($file);

                $this->assertFileExists($file);
                $this->assertNotSame('', (string)file_get_contents($file));
                @unlink($file);
        }

        public function testMimeTypeAndExtensionAndToSql(): void {
                $svc = $this->createStub(IQueryService::class);
                $exp = new JsonReportExporter($svc);

                $this->assertSame('application/json', $exp->getMimeType());
                $this->assertSame('json', $exp->getFileExtension());

                $exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: 'SELECT X'));
                $this->assertSame('SELECT X', $exp->toSql());

                $exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: null));
                $this->assertSame('', $exp->toSql());
        }
}
