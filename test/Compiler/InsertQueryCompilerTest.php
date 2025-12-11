<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\InsertQueryCompiler;
use DataHawk\Compiler\MysqlReportQueryCompiler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

class InsertQueryCompilerTest extends TestCase {

	/**
	 * Compiler that uses a stubbed MysqlReportQueryCompiler (no expectations).
	 */
	private function makeCompilerWithStub(): InsertQueryCompiler {
		$schemaProvider = $this->createStub(IQuerySchemaProvider::class);
		/** @var MysqlReportQueryCompiler&MockObject $mainCompilerStub */
		$mainCompilerStub = $this->createStub(MysqlReportQueryCompiler::class);

		return new InsertQueryCompiler($schemaProvider, $mainCompilerStub);
	}

	/**
	 * Compiler that uses a real mock for MysqlReportQueryCompiler (with expectations).
	 *
	 * @param mixed $mainCompilerMock will be filled with the mock instance
	 */
	private function makeCompilerWithMock(&$mainCompilerMock): InsertQueryCompiler {
		$schemaProvider = $this->createStub(IQuerySchemaProvider::class);
		/** @var MysqlReportQueryCompiler&MockObject $mock */
		$mock = $this->createMock(MysqlReportQueryCompiler::class);
		$mainCompilerMock = $mock;

		return new InsertQueryCompiler($schemaProvider, $mainCompilerMock);
	}

	private function extractSql(QueryStatement $stmt): string {
		if (method_exists($stmt, '__toString')) {
			return (string)$stmt;
		}

		if (method_exists($stmt, 'getSql')) {
			return $stmt->getSql();
		}

		if (property_exists($stmt, 'sql')) {
			/** @var mixed $stmt */
			return $stmt->sql;
		}

		$this->fail('QueryStatement has no accessible SQL representation.');
	}

	public function testCompileInsertValuesWithExplicitColumns(): void {
		$compiler = $this->makeCompilerWithStub();

		$query = [
			'table' => 'users',
			'columns' => ['id', 'name', 'active'],
			'values' => [
				[
					'id' => 1,
					'name' => 'Alice',
					'active' => true,
				],
				[
					'id' => 2,
					'name' => "O'Reilly",
					'active' => false,
				],
			],
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$expected = "INSERT INTO `users` (`id`, `name`, `active`) VALUES "
			. "(1, 'Alice', TRUE), "
			. "(2, 'O''Reilly', FALSE)";

		$this->assertSame($expected, $sql);
	}

	public function testCompileInsertValuesInfersColumnsWhenNotProvided(): void {
		$compiler = $this->makeCompilerWithStub();

		$query = [
			'table' => 'users',
			// no 'columns' -> infer from first row keys
			'values' => [
				[
					'name' => 'Bob',
					'age' => 30,
				],
				[
					'name' => 'Carol',
					'age' => 25,
				],
			],
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$expected = "INSERT INTO `users` (`name`, `age`) VALUES "
			. "('Bob', 30), "
			. "('Carol', 25)";

		$this->assertSame($expected, $sql);
	}

	public function testCompileInsertValuesEmptyArrayThrows(): void {
		$compiler = $this->makeCompilerWithStub();

		$query = [
			'table' => 'users',
			'values' => [],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("'values' must be a non-empty array.");

		$compiler->compile($query);
	}

	public function testCompileInsertValuesNotArrayThrows(): void {
		$compiler = $this->makeCompilerWithStub();

		$query = [
			'table' => 'users',
			'values' => 'not-an-array',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("'values' must be a non-empty array.");

		$compiler->compile($query);
	}

	public function testMissingValueForColumnBecomesNull(): void {
		$compiler = $this->makeCompilerWithStub();

		$query = [
			'table' => 'users',
			'columns' => ['id', 'name', 'age'],
			'values' => [
				[
					'id' => 1,
					'name' => 'Alice',
					// 'age' missing -> NULL
				],
			],
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$expected = "INSERT INTO `users` (`id`, `name`, `age`) VALUES (1, 'Alice', NULL)";

		$this->assertSame($expected, $sql);
	}

	public function testInsertFromSelectWithExplicitColumns(): void {
		$compiler = $this->makeCompilerWithMock($mainCompiler);

		$fromQuery = [
			'type' => 'select',
			'table' => 'src',
		];

		$selectStmt = new QueryStatement('SELECT `id`, `name` FROM `src`', [], [], false);

		$mainCompiler
			->expects($this->once())
			->method('compile')
			->with($this->equalTo($fromQuery))
			->willReturn($selectStmt);

		$query = [
			'table' => 'dest',
			'columns' => ['id', 'name'],
			'from' => $fromQuery,
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$expected = "INSERT INTO `dest` (`id`, `name`) SELECT `id`, `name` FROM `src`";

		$this->assertSame($expected, $sql);
	}

	public function testInsertFromSelectWithoutColumns(): void {
		$compiler = $this->makeCompilerWithMock($mainCompiler);

		$fromQuery = [
			'type' => 'select',
			'table' => 'src',
		];

		$selectStmt = new QueryStatement('SELECT * FROM `src`', [], [], false);

		$mainCompiler
			->expects($this->once())
			->method('compile')
			->with($this->equalTo($fromQuery))
			->willReturn($selectStmt);

		$query = [
			'table' => 'dest',
			'from' => $fromQuery,
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$expected = "INSERT INTO `dest` SELECT * FROM `src`";

		$this->assertSame($expected, $sql);
	}

	public function testInsertFromInvalidFromTypeThrows(): void {
		$compiler = $this->makeCompilerWithStub();

		$query = [
			'table' => 'dest',
			'from' => 'not-an-array',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("'from' must be a valid SELECT query structure.");

		$compiler->compile($query);
	}

	public function testOnDuplicateKeyUpdateWithScalars(): void {
		$compiler = $this->makeCompilerWithStub();

		$query = [
			'table' => 'users',
			'columns' => ['id', 'name'],
			'values' => [
				[
					'id' => 1,
					'name' => 'Alice',
				],
			],
			'on_duplicate' => [
				'name' => 'Bob',
				'updated_at' => [
					'type' => 'fn',
					'function' => 'NOW',
					'params' => [],
				],
			],
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$this->assertStringContainsString("INSERT INTO `users` (`id`, `name`) VALUES (1, 'Alice')", $sql);
		$this->assertStringContainsString("ON DUPLICATE KEY UPDATE `name` = 'Bob', `updated_at` = NOW()", $sql);
	}

	public function testOnDuplicateKeyUpdateOnlyExpression(): void {
		$compiler = $this->makeCompilerWithStub();

		$query = [
			'table' => 'test',
			'columns' => ['id'],
			'values' => [
				['id' => 1],
			],
			'on_duplicate' => [
				'id' => [
					'type' => 'op',
					'operator' => '+',
					'params' => [
						['type' => 'fld', 'field' => 'id'],
						1,
					],
				],
			],
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$this->assertStringContainsString("INSERT INTO `test` (`id`) VALUES (1)", $sql);
		$this->assertStringContainsString("ON DUPLICATE KEY UPDATE `id` = (`id` + 1)", $sql);
	}
}
