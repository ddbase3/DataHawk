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

namespace Base3\DataHawk\Test\Compiler;

use Base3\DataHawk\Compiler\InsertQueryCompiler;
use Base3\DataHawk\Compiler\MysqlReportQueryCompiler;
use Base3\DataHawk\Compiler\QueryCompilerFactory;
use Base3\DataHawk\Compiler\SelectQueryCompiler;

use Base3\DataHawk\Api\IReportQueryTypeCompiler;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Api\IQuerySchemaProvider;
use Base3\ResourceFoundation\Exception\QueryValidationException;

class QueryCompilerFactoryTest extends TestCase {

	private IQuerySchemaProvider $schemaProvider;
	private MysqlReportQueryCompiler $mainCompiler;
	private QueryCompilerFactory $factory;

	protected function setUp(): void {
		// We only need stubs, no expectations on these
		$this->schemaProvider = $this->createStub(IQuerySchemaProvider::class);
		$this->mainCompiler = $this->createStub(MysqlReportQueryCompiler::class);

		$this->factory = new QueryCompilerFactory(
			$this->schemaProvider,
			$this->mainCompiler
		);
	}

	public function testGetCompilerReturnsSelectCompiler(): void {
		$compiler = $this->factory->getCompiler('select');

		$this->assertInstanceOf(
			SelectQueryCompiler::class,
			$compiler,
			'Factory should return SelectQueryCompiler for type "select"'
		);

		$this->assertInstanceOf(
			IReportQueryTypeCompiler::class,
			$compiler,
			'Returned compiler should implement IReportQueryTypeCompiler'
		);
	}

	public function testGetCompilerReturnsInsertCompiler(): void {
		$compiler = $this->factory->getCompiler('insert');

		$this->assertInstanceOf(
			InsertQueryCompiler::class,
			$compiler,
			'Factory should return InsertQueryCompiler for type "insert"'
		);

		$this->assertInstanceOf(
			IReportQueryTypeCompiler::class,
			$compiler,
			'Returned compiler should implement IReportQueryTypeCompiler'
		);
	}

	public function testGetCompilerThrowsForUnknownType(): void {
		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("No compiler registered for query type: unknown");

		$this->factory->getCompiler('unknown');
	}
}
