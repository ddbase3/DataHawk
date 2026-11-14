<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of DataHawk for BASE3 Framework.
 *
 * DataHawk extends BASE3 with schema-driven reporting and data queries.
 * Structured query definitions compile to backend-specific statements.
 * Execution uses the shared BASE3 database and resource contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/datahawk
 * https://github.com/ddbase3/DataHawk
 **********************************************************************/

namespace Base3\DataHawk\Test\Service;

use PHPUnit\Framework\TestCase;
use Base3\DataHawk\Service\DataHawkSchema;
use Base3\CoreFoundation\Api\IRequest;
use Base3\ResourceFoundation\Api\IQueryService;

class DataHawkSchemaTest extends TestCase {

	public function testGetNameReturnsExpectedValue(): void {
		$req = $this->createStub(IRequest::class);
		$svc = $this->createStub(IQueryService::class);

		new DataHawkSchema($req, $svc);

		$this->assertSame('datahawkschema', DataHawkSchema::getName());
	}

	public function testGetHelpReturnsString(): void {
		$req = $this->createStub(IRequest::class);
		$svc = $this->createStub(IQueryService::class);

		$out = new DataHawkSchema($req, $svc);

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


		$out = new DataHawkSchema($req, $svc);

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


		$out = new DataHawkSchema($req, $svc);

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


		$out = new DataHawkSchema($req, $svc);

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


		$out = new DataHawkSchema($req, $svc);

		$json = $out->getOutput('json');
		$this->assertSame(["x","y"], json_decode($json, true));
	}
}
