<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

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
