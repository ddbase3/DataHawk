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

use Base3\DataHawk\Compiler\RenameQueryCompiler;

use Base3\DataHawk\Api\IReportQueryTypeCompiler;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Api\IQuerySchemaProvider;
use Base3\ResourceFoundation\Dto\QueryStatement;
use Base3\ResourceFoundation\Exception\QueryValidationException;

class RenameQueryCompilerTest extends TestCase {

	private IQuerySchemaProvider $schemaProvider;
	private RenameQueryCompiler $compiler;

	protected function setUp(): void {
		// We only need a stub here, no behavior required
		$this->schemaProvider = $this->createStub(IQuerySchemaProvider::class);

		$this->compiler = new RenameQueryCompiler($this->schemaProvider);
	}

	public function testImplementsIReportQueryTypeCompiler(): void {
		$this->assertInstanceOf(
			IReportQueryTypeCompiler::class,
			$this->compiler,
			'RenameQueryCompiler must implement IReportQueryTypeCompiler'
		);
	}

	public function testCompileThrowsWhenFromTableIsMissing(): void {
		$query = [
			'tables' => [
				// 'from' missing
				'to' => 'new_table',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("RENAME query requires a valid 'from' table name.");

		$this->compiler->compile($query);
	}

	public function testCompileThrowsWhenFromTableIsEmptyString(): void {
		$query = [
			'tables' => [
				'from' => '   ',
				'to' => 'new_table',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("RENAME query requires a valid 'from' table name.");

		$this->compiler->compile($query);
	}

	public function testCompileThrowsWhenToTableIsMissing(): void {
		$query = [
			'tables' => [
				'from' => 'old_table',
				// 'to' missing
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("RENAME query requires a valid 'to' table name.");

		$this->compiler->compile($query);
	}

	public function testCompileThrowsWhenToTableIsEmptyString(): void {
		$query = [
			'tables' => [
				'from' => 'old_table',
				'to' => '',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("RENAME query requires a valid 'to' table name.");

		$this->compiler->compile($query);
	}

	public function testCompileReturnsQueryStatementForValidTables(): void {
		$query = [
			'tables' => [
				'from' => 'old_table',
				'to' => 'new_table',
			],
		];

		$result = $this->compiler->compile($query);

		// We do not assert internals of QueryStatement here to avoid
		// assumptions about its API, only that we get the expected type.
		$this->assertInstanceOf(
			QueryStatement::class,
			$result,
			'compile() should return a QueryStatement instance for valid input'
		);
	}
}
