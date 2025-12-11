<?php declare(strict_types=1);

namespace DataHawk\Test\Export;

use PHPUnit\Framework\TestCase;
use DataHawk\Export\HtmlPageReportExporter;
use ResourceFoundation\Api\IQueryService;

class HtmlPageReportExporterTest extends TestCase {
        use ExportTestHelperTrait;

        public function testGetNameReturnsExpectedValue(): void {
                $this->assertSame('htmlpagereportexporter', HtmlPageReportExporter::getName());
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

                $exp = new HtmlPageReportExporter($svc);
                $exp->setExportQuery(['q' => 1]);

                $this->assertSame($result, $exp->getResult());
        }

        public function testToStringThrowsIfNoResultSet(): void {
                $svc = $this->createStub(IQueryService::class);
                $exp = new HtmlPageReportExporter($svc);

                $this->expectException(\RuntimeException::class);
                $this->expectExceptionMessage('No query result set for export.');
                $exp->toString();
        }

        public function testToStringBuildsHtmlDocumentAndEscapesValues(): void {
                $svc = $this->createStub(IQueryService::class);

                $result = $this->makeQueryResult(
                        columns: [['name' => 'col']],
                        rows: [['<b>bad</b>']]
                );

                $exp = new HtmlPageReportExporter($svc);
                $exp->setResult($result);

                $html = $exp->toString();

                $this->assertStringContainsString('<!DOCTYPE html>', $html);
                $this->assertStringContainsString('<table>', $html);
                $this->assertStringContainsString('&lt;b&gt;bad&lt;/b&gt;', $html);
        }

        public function testToFileWritesHtml(): void {
                $svc = $this->createStub(IQueryService::class);

                $result = $this->makeQueryResult(
                        columns: [['name' => 'a']],
                        rows: [[1]]
                );

                $exp = new HtmlPageReportExporter($svc);
                $exp->setResult($result);

                $file = $this->tempFilePath('page_') . '.html';
                $exp->toFile($file);

                $this->assertFileExists($file);
                $this->assertNotSame('', (string)file_get_contents($file));
                @unlink($file);
        }

        public function testMimeTypeAndExtensionAndToSql(): void {
                $svc = $this->createStub(IQueryService::class);
                $exp = new HtmlPageReportExporter($svc);

                $this->assertSame('text/html', $exp->getMimeType());
                $this->assertSame('html', $exp->getFileExtension());

                $exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: 'SELECT X'));
                $this->assertSame('SELECT X', $exp->toSql());

                $exp->setResult($this->makeQueryResult(columns: [], rows: [], debugSql: null));
                $this->assertSame('', $exp->toSql());
        }
}
