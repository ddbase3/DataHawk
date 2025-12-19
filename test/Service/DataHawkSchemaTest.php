<?php declare(strict_types=1);

namespace DataHawk\Test\Service;

use PHPUnit\Framework\TestCase;
use DataHawk\Service\DataHawkSchema;
use Base3\Api\IRequest;
use DataHawk\Api\IReportExporterFactory;
use ResourceFoundation\Api\IQueryService;

class DataHawkSchemaTest extends TestCase {

	public function testGetNameReturnsExpectedValue(): void {
		$req = $this->createStub(IRequest::class);
		$svc = $this->createStub(IQueryService::class);
		$factory = $this->createStub(IReportExporterFactory::class);

		new DataHawkSchema($req, $svc, $factory);

		$this->assertSame('datahawkschema', DataHawkSchema::getName());
	}

	public function testGetHelpReturnsString(): void {
		$req = $this->createStub(IRequest::class);
		$svc = $this->createStub(IQueryService::class);
		$factory = $this->createStub(IReportExporterFactory::class);

		$out = new DataHawkSchema($req, $svc, $factory);

		$this->assertSame("Help of DataHawkSchema\n", $out->getHelp());
	}

	public function testGetOutputJsonDefaultsToListTables(): void {
		$req = $this->createStub(IRequest::class);
		$req->method('get')->willReturnMap([
			['q', null, null],
		]);
		$req->method('post')->willReturnMap([
			['q', null, null],
		]);

		$svc = $this->createMock(IQueryService::class);
		$svc->expects($this->once())
			->method('listTables')
			->willReturn(['t1', 't2']);

		$factory = $this->createStub(IReportExporterFactory::class);

		$out = new DataHawkSchema($req, $svc, $factory);

		$json = $out->getOutput('json');
		$this->assertJson($json);
		$this->assertSame(["t1","t2"], json_decode($json, true));
	}

	public function testGetOutputJsonDomainsUsesGetParamOverPostParam(): void {
		$req = $this->createStub(IRequest::class);
		$req->method('get')->willReturnMap([
			['q', null, 'domains'],
		]);
		$req->method('post')->willReturnMap([
			['q', null, 'categories'],
		]);

		$svc = $this->createMock(IQueryService::class);
		$svc->expects($this->once())
			->method('listDomains')
			->willReturn(['d1', 'd2']);

		$factory = $this->createStub(IReportExporterFactory::class);

		$out = new DataHawkSchema($req, $svc, $factory);

		$json = $out->getOutput('json');
		$this->assertSame(["d1","d2"], json_decode($json, true));
	}

	public function testGetOutputJsonCategoriesUsesPostWhenGetMissing(): void {
		$req = $this->createStub(IRequest::class);
		$req->method('get')->willReturnMap([
			['q', null, null],
		]);
		$req->method('post')->willReturnMap([
			['q', null, 'categories'],
		]);

		$svc = $this->createMock(IQueryService::class);
		$svc->expects($this->once())
			->method('listCategories')
			->willReturn(['c1']);

		$factory = $this->createStub(IReportExporterFactory::class);

		$out = new DataHawkSchema($req, $svc, $factory);

		$json = $out->getOutput('json');
		$this->assertSame(["c1"], json_decode($json, true));
	}

	public function testGetOutputJsonTags(): void {
		$req = $this->createStub(IRequest::class);
		$req->method('get')->willReturnMap([
			['q', null, 'tags'],
		]);
		$req->method('post')->willReturnMap([
			['q', null, null],
		]);

		$svc = $this->createMock(IQueryService::class);
		$svc->expects($this->once())
			->method('listTags')
			->willReturn(['x', 'y']);

		$factory = $this->createStub(IReportExporterFactory::class);

		$out = new DataHawkSchema($req, $svc, $factory);

		$json = $out->getOutput('json');
		$this->assertSame(["x","y"], json_decode($json, true));
	}
}
