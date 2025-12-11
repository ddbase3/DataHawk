<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Exception\QueryValidationException;

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
