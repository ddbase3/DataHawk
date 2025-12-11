<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

class UpdateQueryCompilerTest extends TestCase {

	private IQuerySchemaProvider $schemaProvider;
	private UpdateQueryCompiler $compiler;

	protected function setUp(): void {
		$this->schemaProvider = $this->createStub(IQuerySchemaProvider::class);

		// Tabelle "users" mit zwei Join-Definitionen, um den Graph-Aufbau (Zeilen 32–36) abzudecken
		$usersTable = new \stdClass();
		$usersTable->name = 'users';
		$usersTable->sensitive = false;
		$usersTable->fields = [];

		// Join 1: mit default=true → Label "default"
		$join1 = new \stdClass();
		$join1->targetTable = 'profiles';
		$join1->on = ['users.id' => 'profiles.user_id'];
		$join1->type = 'LEFT';
		$join1->meta = ['default' => true];

		// Join 2: ohne default → Label via uniqid('join_', true)
		$join2 = new \stdClass();
		$join2->targetTable = 'logs';
		$join2->on = ['users.id' => 'logs.user_id'];
		$join2->type = 'INNER';
		$join2->meta = []; // kein default-Flag

		$usersTable->joins = [$join1, $join2];

		// Zieltabellen für die Joins ohne weitere Joins
		$profilesTable = new \stdClass();
		$profilesTable->name = 'profiles';
		$profilesTable->joins = [];
		$profilesTable->fields = [];
		$profilesTable->sensitive = false;

		$logsTable = new \stdClass();
		$logsTable->name = 'logs';
		$logsTable->joins = [];
		$logsTable->fields = [];
		$logsTable->sensitive = false;

		// getSchema() liefert alle drei Tabellen zurück
		$this->schemaProvider
			->method('getSchema')
			->willReturn([$usersTable, $profilesTable, $logsTable]);

		// Konstruktor läuft nun durch alle addNode/addEdge inkl. Zeile 32–36
		$this->compiler = new UpdateQueryCompiler($this->schemaProvider);
	}

	public function testImplementsIReportQueryTypeCompiler(): void {
		$this->assertInstanceOf(
			IReportQueryTypeCompiler::class,
			$this->compiler,
			'UpdateQueryCompiler must implement IReportQueryTypeCompiler'
		);
	}

	public function testCompileThrowsWhenTableMissing(): void {
		$query = [
			// 'table' fehlt
			'set' => [
				'name' => 'Alice',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UPDATE query must contain 'table'.");

		$this->compiler->compile($query);
	}

	public function testCompileThrowsWhenTableIsNotString(): void {
		$query = [
			'table' => ['not-a-string'],
			'set' => [
				'name' => 'Alice',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UPDATE query must contain 'table'.");

		$this->compiler->compile($query);
	}

	public function testCompileThrowsWhenSetIsMissingOrEmpty(): void {
		$query = [
			'table' => 'users',
			// kein 'set'
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UPDATE query must contain non-empty 'set' definition.");

		$this->compiler->compile($query);
	}

	public function testCompileWithLiteralSetWithoutWhereOrLimit(): void {
		$query = [
			'table' => 'users',
			'set' => [
				'name' => 'Alice',
				'age' => 30,
			],
		];

		$result = $this->compiler->compile($query);

		$this->assertInstanceOf(QueryStatement::class, $result);

		$this->assertSame(
			"UPDATE `users` SET `name` = 'Alice', `age` = 30",
			$result->sql
		);
	}

	public function testCompileWithMixedLiteralAndExpressionSetAndWhereAndLimit(): void {
		$query = [
			'table' => 'users',
			'set' => [
				// Literal → quoteLiteral-Zweig
				'name' => 'Alice',
				// Ausdruck mit 'type' → compileElement-Zweig
				'updated_at' => [
					'type' => 'fn',
					'function' => 'NOW',
					'params' => [],
				],
			],
			'where' => [
				'type' => 'op',
				'operator' => '=',
				'params' => [
					[
						'type' => 'fld',
						'table' => 'users',
						'field' => 'id',
					],
					123,
				],
			],
			'limit' => 5,
		];

		$result = $this->compiler->compile($query);

		$this->assertInstanceOf(QueryStatement::class, $result);

		// Erwartetes SQL:
		// UPDATE `users`
		//   [JOINs werden ggf. vom JoinPlanner eingefügt – hier: keine JoinRequests aus SET/WHERE, also vermutlich leer]
		// SET `name` = 'Alice', `updated_at` = NOW()
		// WHERE (`users`.`id` = 123)
		// LIMIT 5
		$this->assertSame(
			"UPDATE `users` SET `name` = 'Alice', `updated_at` = NOW() WHERE (`users`.`id` = 123) LIMIT 5",
			$result->sql
		);
	}
}
