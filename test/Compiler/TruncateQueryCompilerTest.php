<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

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
