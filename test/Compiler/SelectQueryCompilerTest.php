<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

class SelectQueryCompilerTest extends TestCase {

	private IQuerySchemaProvider $schemaProvider;
	private SelectQueryCompiler $compiler;

	protected function setUp(): void {
		$this->schemaProvider = $this->createStub(IQuerySchemaProvider::class);

		// Einfaches Schema mit einer Tabelle "users" und Feld-Metadaten
		$table = new \stdClass();
		$table->name = 'users';
		$table->joins = []; // keine Joins, damit JoinPlanner keine Pfade braucht
		$table->sensitive = false;

		$idField = new \stdClass();
		$idField->name = 'id';
		$idField->sensitive = false;

		$emailField = new \stdClass();
		$emailField->name = 'email';
		$emailField->sensitive = true;

		$table->fields = [$idField, $emailField];

		$this->schemaProvider
			->method('getSchema')
			->willReturn([$table]);

		$this->compiler = new SelectQueryCompiler($this->schemaProvider);
	}

	private function makeField(string $field, ?string $alias = null, ?string $table = 'users', bool $distinct = false): array {
		$entry = [
			'element' => [
				'type' => 'fld',
				'table' => $table,
				'field' => $field,
			],
		];

		if ($alias !== null) {
			$entry['alias'] = $alias;
		}

		if ($distinct) {
			$entry['distinct'] = true;
		}

		return $entry;
	}

	public function testCompileSimpleSelectGeneratesExpectedSql(): void {
		$query = [
			'table' => 'users',
			'fields' => [
				$this->makeField('id', 'id'),
			],
		];

		$result = $this->compiler->compile($query);

		$this->assertInstanceOf(QueryStatement::class, $result);
		$this->assertSame(
			"SELECT `users`.`id` AS `id` FROM `users`",
			$result->sql
		);
	}

	public function testCompileUsesFromWhenTableIsMissing(): void {
		$query = [
			'from' => 'users',
			'fields' => [
				$this->makeField('id', 'id'),
			],
		];

		$result = $this->compiler->compile($query);

		$this->assertSame(
			"SELECT `users`.`id` AS `id` FROM `users`",
			$result->sql
		);
	}

	public function testCompileThrowsWhenNoTableOrFieldTableAvailable(): void {
		$query = [
			// kein table, kein from, Feld ohne table
			'fields' => [
				[
					'element' => [
						'type' => 'fn',
						'function' => 'NOW',
						'params' => [],
					],
					'alias' => 'now',
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Query must contain 'table' or at least one field reference with 'table'.");

		$this->compiler->compile($query);
	}

	public function testCompileThrowsWhenFieldsArrayIsEmpty(): void {
		$query = [
			'table' => 'users',
			'fields' => [],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Query must contain at least one field in 'fields'.");

		$this->compiler->compile($query);
	}

	public function testCompileThrowsWhenFieldEntryMissingElement(): void {
		$query = [
			'table' => 'users',
			'fields' => [
				[
					'alias' => 'id',
					// missing 'element'
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Missing element in fields entry.");

		$this->compiler->compile($query);
	}

	public function testCompileThrowsWhenOrderByMissingElement(): void {
		$query = [
			'table' => 'users',
			'fields' => [
				$this->makeField('id', 'id'),
			],
			'order_by' => [
				[
					// missing 'element'
					'direction' => 'ASC',
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Missing element in order_by clause.");

		$this->compiler->compile($query);
	}

	public function testCompileThrowsWhenOrderByDirectionIsInvalid(): void {
		$query = [
			'table' => 'users',
			'fields' => [
				$this->makeField('id', 'id'),
			],
			'order_by' => [
				[
					'element' => [
						'type' => 'fld',
						'table' => 'users',
						'field' => 'id',
					],
					'direction' => 'SIDEWAYS',
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Invalid order direction: SIDEWAYS");

		$this->compiler->compile($query);
	}

	public function testCompilePerFieldDistinctWithoutGlobalDistinct(): void {
		$query = [
			'table' => 'users',
			'fields' => [
				$this->makeField('id', 'id', 'users', true),
			],
		];

		$result = $this->compiler->compile($query);

		$this->assertSame(
			"SELECT DISTINCT `users`.`id` AS `id` FROM `users`",
			$result->sql
		);
	}

	public function testCompileGlobalDistinctWithoutPerFieldDistinct(): void {
		$query = [
			'table' => 'users',
			'distinct' => true,
			'fields' => [
				$this->makeField('id', 'id'),
			],
		];

		$result = $this->compiler->compile($query);

		$this->assertSame(
			"SELECT DISTINCT `users`.`id` AS `id` FROM `users`",
			$result->sql
		);
	}

	public function testCompileWildcardAndSensitiveField(): void {
		$query = [
			'table' => 'users',
			'fields' => [
				// Wildcard
				[
					'element' => [
						'type' => 'fld',
						'table' => 'users',
						'field' => '*',
					],
				],
				// sensitive Feld
				$this->makeField('email', 'email'),
			],
		];

		$result = $this->compiler->compile($query);

		$this->assertSame(
			"SELECT `users`.*, `users`.`email` AS `email` FROM `users`",
			$result->sql
		);
		// wir verlassen uns darauf, dass isSensitiveQuery / hasWildcard intern korrekt gesetzt werden
		$this->assertInstanceOf(QueryStatement::class, $result);
	}

	public function testCompileWithWhereGroupByHavingOrderByLimitOffset(): void {
		$query = [
			'table' => 'users',
			'fields' => [
				$this->makeField('id', 'id'),
			],
			'where' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'users',
						'field' => 'active',
					],
					1,
				],
			],
			'group_by' => [
				[
					'type' => 'fld',
					'table' => 'users',
					'field' => 'active',
				],
			],
			'having' => [
				'type' => 'op',
				'operator' => '>',
				'params' => [
					[
						'type' => 'fn',
						'function' => 'COUNT',
						'params' => [1],
					],
					0,
				],
			],
			'order_by' => [
				[
					'element' => [
						'type' => 'fld',
						'table' => 'users',
						'field' => 'id',
					],
					'direction' => 'DESC',
				],
			],
			'limit' => 10,
			'offset' => 5,
		];

		$result = $this->compiler->compile($query);

		$this->assertSame(
			"SELECT `users`.`id` AS `id` FROM `users`" .
			" WHERE (`users`.`active` = 1)" .
			" GROUP BY `users`.`active`" .
			" HAVING (COUNT(1) > 0)" .
			" ORDER BY `users`.`id` DESC" .
			" LIMIT 10 OFFSET 5",
			$result->sql
		);
	}

	public function testCompileUnionBuildsDistinctUnionWithOrderLimitOffset(): void {
		$subQuery = [
			'table' => 'users',
			'fields' => [
				$this->makeField('id', 'id'),
			],
		];

		$query = [
			'union' => [
				// distinct = true → normales UNION
				'distinct' => true,
				'queries' => [
					$subQuery,
					$subQuery,
				],
			],
			'order_by' => [
				[
					'element' => [
						'type' => 'fld',
						// Feld aus dem Union-Resultset, ohne Table
						'field' => 'id',
					],
					'direction' => 'DESC',
				],
			],
			'limit' => 10,
			'offset' => 5,
		];

		$result = $this->compiler->compile($query);

		$this->assertInstanceOf(QueryStatement::class, $result);
		$this->assertSame(
			"(SELECT `users`.`id` AS `id` FROM `users`) UNION (SELECT `users`.`id` AS `id` FROM `users`)" .
			" ORDER BY `id` DESC LIMIT 10 OFFSET 5",
			$result->sql
		);
	}

	public function testCompileUnionAllWhenDistinctIsFalse(): void {
		$subQuery = [
			'table' => 'users',
			'fields' => [
				$this->makeField('id', 'id'),
			],
		];

		$query = [
			'union' => [
				'distinct' => false, // → UNION ALL
				'queries' => [
					$subQuery,
					$subQuery,
				],
			],
		];

		$result = $this->compiler->compile($query);

		$this->assertSame(
			"(SELECT `users`.`id` AS `id` FROM `users`) UNION ALL (SELECT `users`.`id` AS `id` FROM `users`)",
			$result->sql
		);
	}

	public function testCompileUnionThrowsWhenOrderByDirectionInvalid(): void {
		$subQuery = [
			'table' => 'users',
			'fields' => [
				$this->makeField('id', 'id'),
			],
		];

		$query = [
			'union' => [
				'distinct' => true,
				'queries' => [
					$subQuery,
					$subQuery,
				],
			],
			'order_by' => [
				[
					'element' => [
						'type' => 'fld',
						'field' => 'id',
					],
					'direction' => 'WRONG',
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Invalid order direction: WRONG");

		$this->compiler->compile($query);
	}
}
