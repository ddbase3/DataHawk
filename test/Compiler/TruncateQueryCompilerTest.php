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

use Base3\DataHawk\Compiler\TruncateQueryCompiler;

use Base3\DataHawk\Api\IReportQueryTypeCompiler;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Api\IQuerySchemaProvider;
use Base3\ResourceFoundation\Dto\QueryStatement;
use Base3\ResourceFoundation\Exception\QueryValidationException;

class TruncateQueryCompilerTest extends TestCase {

	private IQuerySchemaProvider $schemaProvider;
	private TruncateQueryCompiler $compiler;

	protected function setUp(): void {
		// Only a stub is required, TruncateQueryCompiler does not really use the schema in current implementation
		$this->schemaProvider = $this->createStub(IQuerySchemaProvider::class);

		$this->compiler = new TruncateQueryCompiler($this->schemaProvider);
	}

	public function testImplementsIReportQueryTypeCompiler(): void {
		$this->assertInstanceOf(
			IReportQueryTypeCompiler::class,
			$this->compiler,
			'TruncateQueryCompiler must implement IReportQueryTypeCompiler'
		);
	}

	public function testCompileThrowsWhenTableIsMissing(): void {
		$query = [
			// no 'table' key
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("TRUNCATE query must define a valid 'table'.");

		$this->compiler->compile($query);
	}

	public function testCompileThrowsWhenTableIsNotString(): void {
		$query = [
			'table' => ['not-a-string'],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("TRUNCATE query must define a valid 'table'.");

		$this->compiler->compile($query);
	}

	public function testCompileReturnsQueryStatementForValidTable(): void {
		$query = [
			'table' => 'my_table',
		];

		$result = $this->compiler->compile($query);

		$this->assertInstanceOf(
			QueryStatement::class,
			$result,
			'compile() should return a QueryStatement for a valid TRUNCATE query'
		);
	}
}
