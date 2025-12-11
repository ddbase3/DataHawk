<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\DeleteQueryCompiler;
use DataHawk\Compiler\ElementCompiler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

class DeleteQueryCompilerTest extends TestCase {

	/**
	 * Creates a DeleteQueryCompiler and injects a stubbed ElementCompiler.
	 *
	 * @return array{0:DeleteQueryCompiler,1:ElementCompiler&MockObject}
	 */
	private function makeCompilerWithElementStub(): array {
		$schemaProvider = $this->createStub(IQuerySchemaProvider::class);

		$compiler = new DeleteQueryCompiler($schemaProvider);

		/** @var ElementCompiler&MockObject $elementStub */
		$elementStub = $this->createStub(ElementCompiler::class);

		$ref = new \ReflectionClass($compiler);
		$prop = $ref->getProperty('elementCompiler');
		$prop->setAccessible(true);
		$prop->setValue($compiler, $elementStub);

		return [$compiler, $elementStub];
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

	public function testCompileSimpleDeleteWithoutWhereOrderLimit(): void {
		[$compiler, $elementStub] = $this->makeCompilerWithElementStub();

		$elementStub->method('quoteIdentifier')
			->willReturnCallback(static fn(string $name): string => '`' . $name . '`');

		$query = [
			'table' => 'users',
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$this->assertSame('DELETE FROM `users`', $sql);
	}

	public function testCompileWithWhereClause(): void {
		[$compiler, $elementStub] = $this->makeCompilerWithElementStub();

		$elementStub->method('quoteIdentifier')
			->willReturnCallback(static fn(string $name): string => '`' . $name . '`');

		$elementStub->method('compileElement')
			->willReturnCallback(function ($arg): string {
				return '1 = 1';
			});

		$query = [
			'table' => 'users',
			'where' => ['type' => 'const', 'value' => 1],
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$this->assertSame('DELETE FROM `users` WHERE 1 = 1', $sql);
	}

	public function testCompileWithOrderByAndLimit(): void {
		[$compiler, $elementStub] = $this->makeCompilerWithElementStub();

		$elementStub->method('quoteIdentifier')
			->willReturnCallback(static fn(string $name): string => '`' . $name . '`');

		$elementStub->method('compileElement')
			->willReturnCallback(function ($arg): string {
				if (is_array($arg) && isset($arg['field'])) {
					return '`' . $arg['field'] . '`';
				}
				return 'EXPR';
			});

		$query = [
			'table' => 'items',
			'order_by' => [
				[
					'element'   => ['field' => 'a'],
					'direction' => 'ASC',
				],
				[
					'element'   => ['field' => 'b'],
					'direction' => 'desc',
				],
				[
					'element'   => ['field' => 'c'],
					// direction omitted -> default ASC
				],
			],
			'limit' => 5,
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$expected = 'DELETE FROM `items`'
			. ' ORDER BY `a` ASC, `b` DESC, `c` ASC'
			. ' LIMIT 5';

		$this->assertSame($expected, $sql);
	}

	public function testCompileWithWhereOrderByAndLimitCombined(): void {
		[$compiler, $elementStub] = $this->makeCompilerWithElementStub();

		$elementStub->method('quoteIdentifier')
			->willReturnCallback(static fn(string $name): string => '`' . $name . '`');

		$elementStub->method('compileElement')
			->willReturnCallback(function ($arg): string {
				if (is_array($arg) && isset($arg['type'], $arg['name']) && $arg['type'] === 'fld') {
					if ($arg['name'] === 'active') {
						return '`active` = 1';
					}
					if ($arg['name'] === 'id') {
						return '`id`';
					}
				}
				return 'EXPR';
			});

		$query = [
			'table' => 'users',
			'where' => ['type' => 'fld', 'name' => 'active'],
			'order_by' => [
				[
					'element'   => ['type' => 'fld', 'name' => 'id'],
					'direction' => 'DESC',
				],
			],
			'limit' => '10',
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$expected = 'DELETE FROM `users` WHERE `active` = 1 ORDER BY `id` DESC LIMIT 10';
		$this->assertSame($expected, $sql);
	}

	public function testMissingElementInOrderByThrowsException(): void {
		[$compiler, $elementStub] = $this->makeCompilerWithElementStub();

		$elementStub->method('quoteIdentifier')
			->willReturnCallback(static fn(string $name): string => '`' . $name . '`');

		$query = [
			'table' => 'users',
			'order_by' => [
				[
					// missing 'element'
					'direction' => 'ASC',
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage('Missing element in order_by clause.');

		$compiler->compile($query);
	}

	public function testInvalidOrderDirectionThrowsException(): void {
		[$compiler, $elementStub] = $this->makeCompilerWithElementStub();

		$elementStub->method('quoteIdentifier')
			->willReturnCallback(static fn(string $name): string => '`' . $name . '`');

		$elementStub->method('compileElement')
			->willReturn('`id`');

		$query = [
			'table' => 'users',
			'order_by' => [
				[
					'element'   => ['type' => 'fld', 'name' => 'id'],
					'direction' => 'UP',
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage('Invalid order direction: UP');

		$compiler->compile($query);
	}

	public function testLimitIsCastToInt(): void {
		[$compiler, $elementStub] = $this->makeCompilerWithElementStub();

		$elementStub->method('quoteIdentifier')
			->willReturnCallback(static fn(string $name): string => '`' . $name . '`');

		$query = [
			'table' => 'log',
			'limit' => '3',
		];

		$stmt = $compiler->compile($query);
		$sql = $this->extractSql($stmt);

		$this->assertSame('DELETE FROM `log` LIMIT 3', $sql);
	}
}
