<?php declare(strict_types=1);

namespace DataHawk\Test\Service;

use PHPUnit\Framework\TestCase;
use DataHawk\Service\DefaultReportQueryService;
use Base3\Api\IContainer;
use Base3\Database\Api\IDatabase;
use ResourceFoundation\Api\IQueryCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\FieldMetadata;
use ResourceFoundation\Dto\QueryResult;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Dto\TableMetadata;
use ResourceFoundation\Exception\QueryValidationException;

class DefaultReportQueryServiceTest extends TestCase {

	public function testListTablesDelegatesToSchemaProvider(): void {
		$schema = $this->createMock(IQuerySchemaProvider::class);
		$schema->expects($this->once())->method('getSchema')->willReturn([$this->makeTable('t')]);

		$compiler = $this->createStub(IQueryCompiler::class);
		$db = $this->createStub(IDatabase::class);

		$container = $this->createStub(IContainer::class);
		$container->method('get')->with('database')->willReturn($db);

		$svc = new DefaultReportQueryService($schema, $compiler, $container);

		$tables = $svc->listTables();
		$this->assertCount(1, $tables);
		$this->assertSame('t', $tables[0]->name);
	}

	public function testGetTableDelegatesToSchemaProvider(): void {
		$schema = $this->createMock(IQuerySchemaProvider::class);
		$schema->expects($this->once())->method('getTable')->with('users')->willReturn($this->makeTable('users'));

		$compiler = $this->createStub(IQueryCompiler::class);
		$db = $this->createStub(IDatabase::class);

		$container = $this->createStub(IContainer::class);
		$container->method('get')->with('database')->willReturn($db);

		$svc = new DefaultReportQueryService($schema, $compiler, $container);

		$t = $svc->getTable('users');
		$this->assertInstanceOf(TableMetadata::class, $t);
		$this->assertSame('users', $t->name);
	}

	public function testExecuteQueryBuildsColumnsAndSensitiveFlag(): void {
		$schema = $this->createStub(IQuerySchemaProvider::class);

		$stmt = new QueryStatement(
			sql: 'SELECT a AS A, b AS B',
			params: [],
			fields: [
				['name' => 'a', 'alias' => 'A', 'table' => 't', 'sensitive' => false],
				['name' => 'b', 'alias' => 'B', 'table' => 't', 'sensitive' => true],
			],
			sensitive: false,
			isWildcardQuery: false
		);

		$compiler = $this->createMock(IQueryCompiler::class);
		$compiler->expects($this->once())->method('compile')->with(['q' => 1])->willReturn($stmt);

		$db = $this->createMock(IDatabase::class);
		$db->expects($this->once())->method('connect');
		$db->expects($this->once())
			->method('multiQuery')
			->with($stmt->sql)
			->willReturn([['A' => 123, 'B' => 'x']]);

		$container = $this->createStub(IContainer::class);
		$container->method('get')->with('database')->willReturn($db);

		$svc = new DefaultReportQueryService($schema, $compiler, $container);

		$result = $svc->executeQuery(['q' => 1]);

		$this->assertInstanceOf(QueryResult::class, $result);
		$this->assertSame('SELECT a AS A, b AS B', $result->debugSql);
		$this->assertTrue($result->sensitive);

		$this->assertCount(2, $result->columns);

		$this->assertSame('A', $result->columns[0]['name']);
		$this->assertSame('integer', $result->columns[0]['type']);
		$this->assertSame('a', $result->columns[0]['field']);
		$this->assertSame('A', $result->columns[0]['alias']);
		$this->assertSame('t', $result->columns[0]['table']);
		$this->assertFalse($result->columns[0]['sensitive']);

		$this->assertSame('B', $result->columns[1]['name']);
		$this->assertSame('string', $result->columns[1]['type']);
		$this->assertTrue($result->columns[1]['sensitive']);
	}

	public function testExecuteQueryThrowsOnColumnMismatchWhenNotWildcard(): void {
		$schema = $this->createStub(IQuerySchemaProvider::class);

		$stmt = new QueryStatement(
			sql: 'SELECT a AS A',
			params: [],
			fields: [
				['name' => 'a', 'alias' => 'A'],
			],
			sensitive: false,
			isWildcardQuery: false
		);

		$compiler = $this->createStub(IQueryCompiler::class);
		$compiler->method('compile')->willReturn($stmt);

		$db = $this->createMock(IDatabase::class);
		$db->expects($this->once())->method('connect');
		$db->expects($this->once())->method('multiQuery')->willReturn([['X' => 1]]);

		$container = $this->createStub(IContainer::class);
		$container->method('get')->with('database')->willReturn($db);

		$svc = new DefaultReportQueryService($schema, $compiler, $container);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Query result column mismatch');
		$svc->executeQuery(['q' => 1]);
	}

	public function testExecuteQuerySkipsMismatchValidationInWildcardMode(): void {
		$schema = $this->createStub(IQuerySchemaProvider::class);

		$stmt = new QueryStatement(
			sql: 'SELECT *',
			params: [],
			fields: [
				['name' => 'a', 'alias' => 'A'],
			],
			sensitive: false,
			isWildcardQuery: true
		);

		$compiler = $this->createStub(IQueryCompiler::class);
		$compiler->method('compile')->willReturn($stmt);

		$db = $this->createMock(IDatabase::class);
		$db->expects($this->once())->method('connect');
		$db->expects($this->once())->method('multiQuery')->willReturn([['X' => 1]]);

		$container = $this->createStub(IContainer::class);
		$container->method('get')->with('database')->willReturn($db);

		$svc = new DefaultReportQueryService($schema, $compiler, $container);

		$result = $svc->executeQuery(['q' => 1]);
		$this->assertInstanceOf(QueryResult::class, $result);
		$this->assertSame([['X' => 1]], $result->rows);
	}

	public function testExecuteQueryReturnsDebugSqlWithDbErrorOnException(): void {
		$schema = $this->createStub(IQuerySchemaProvider::class);

		$stmt = new QueryStatement(
			sql: 'SELECT 1',
			params: [],
			fields: [],
			sensitive: false,
			isWildcardQuery: false
		);

		$compiler = $this->createStub(IQueryCompiler::class);
		$compiler->method('compile')->willReturn($stmt);

		$db = $this->createMock(IDatabase::class);
		$db->expects($this->once())->method('connect');
		$db->expects($this->once())->method('multiQuery')->willThrowException(new \RuntimeException('boom'));

		$container = $this->createStub(IContainer::class);
		$container->method('get')->with('database')->willReturn($db);

		$svc = new DefaultReportQueryService($schema, $compiler, $container);

		$res = $svc->executeQuery(['q' => 1]);

		$this->assertSame([], $res->columns);
		$this->assertSame([], $res->rows);
		$this->assertNotNull($res->debugSql);
		$this->assertStringContainsString('SELECT 1', $res->debugSql);
		$this->assertStringContainsString('DB Error: boom', $res->debugSql);
	}

	public function testListDomainsCategoriesTagsAreUniqueAndNonEmpty(): void {
		$schema = $this->createMock(IQuerySchemaProvider::class);

		$t1 = $this->makeTable(
			name: 't1',
			domain: 'crm',
			category: 'core',
			tags: ['a', 'b'],
			fields: [
				$this->makeField('f1', ['x', '']),
				$this->makeField('f2', ['b']),
			]
		);
		$t2 = $this->makeTable(
			name: 't2',
			domain: 'crm',
			category: 'sales',
			tags: [''],
			fields: [
				$this->makeField('f3', ['y']),
			]
		);
		$t3 = $this->makeTable(
			name: 't3',
			domain: '',
			category: '',
			tags: [],
			fields: []
		);

		$schema->expects($this->exactly(3))
			->method('getSchema')
			->willReturn([$t1, $t2, $t3]);

		$compiler = $this->createStub(IQueryCompiler::class);
		$db = $this->createStub(IDatabase::class);

		$container = $this->createStub(IContainer::class);
		$container->method('get')->with('database')->willReturn($db);

		$svc = new DefaultReportQueryService($schema, $compiler, $container);

		$this->assertSame(['crm'], $svc->listDomains());
		$this->assertSame(['core', 'sales'], $svc->listCategories());

		$tags = $svc->listTags();
		sort($tags);
		$this->assertSame(['a', 'b', 'x', 'y'], $tags);
	}

	public function testInsertWritesDataAndReportsAffectedRowsAndInsertId(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects($this->once())->method('connect');
		$database->expects($this->once())->method('nonQuery')->with('INSERT INTO orders VALUES (1)');
		$database->method('isError')->willReturn(false);
		$database->method('affectedRows')->willReturn(1);
		$database->method('insertId')->willReturn(42);
		$compiler = $this->createStub(IQueryCompiler::class);
		$compiler->method('compile')->willReturn(new QueryStatement('INSERT INTO orders VALUES (1)'));
		$result = $this->queryService($database, $compiler)->executeQuery(['type' => 'insert']);

		self::assertSame([], $result->rows);
		self::assertSame('INSERT INTO orders VALUES (1)', $result->debugSql);
		self::assertSame(1, $result->affectedRows);
		self::assertSame(42, $result->insertId);
	}

	public function testAllSupportedWriteTypesUseNonQueryInsteadOfMultiQuery(): void {
		foreach(['update', 'delete', 'create', 'alter', 'drop', 'rename', 'truncate'] as $type) {
			$database = $this->createMock(IDatabase::class);
			$database->expects($this->once())->method('nonQuery')->with('WRITE QUERY');
			$database->expects($this->never())->method('multiQuery');
			$database->method('isError')->willReturn(false);
			$database->method('affectedRows')->willReturn(3);
			$compiler = $this->createStub(IQueryCompiler::class);
			$compiler->method('compile')->willReturn(new QueryStatement('WRITE QUERY'));
			$result = $this->queryService($database, $compiler)->executeQuery(['type' => $type]);
			self::assertSame(3, $result->affectedRows, $type);
			self::assertNull($result->insertId, $type);
		}
	}

	public function testWriteDatabaseErrorReturnsExplicitFailureResult(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects($this->once())->method('nonQuery');
		$database->method('isError')->willReturn(true);
		$database->method('errorMessage')->willReturn('write rejected');
		$compiler = $this->createStub(IQueryCompiler::class);
		$compiler->method('compile')->willReturn(new QueryStatement('UPDATE orders'));
		$result = $this->queryService($database, $compiler)->executeQuery(['type' => 'update']);
		self::assertSame([], $result->rows);
		self::assertStringContainsString('DB Error: write rejected', $result->debugSql);
		self::assertNull($result->affectedRows);
	}

	public function testSuccessfulTransactionCommitsAndCombinesAffectedRows(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects($this->once())->method('beginTransaction');
		$database->expects($this->once())->method('commit');
		$database->expects($this->never())->method('rollback');
		$database->expects($this->exactly(2))->method('nonQuery');
		$database->method('isError')->willReturn(false);
		$database->method('affectedRows')->willReturn(2);
		$database->method('insertId')->willReturn('remote-42');
		$compiler = $this->createStub(IQueryCompiler::class);
		$compiler->method('compile')->willReturnCallback(static fn(array $query): QueryStatement =>
			new QueryStatement(strtoupper($query['type']) . ' QUERY')
		);
		$result = $this->queryService($database, $compiler)->executeQuery([
			'type' => 'transaction',
			'queries' => [['type' => 'update'], ['type' => 'insert']]
		]);
		self::assertSame(4, $result->affectedRows);
		self::assertSame('remote-42', $result->insertId);
		self::assertSame('INSERT QUERY', $result->debugSql);
	}

	public function testTransactionRequiresNonemptyArrayOfQueries(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects($this->never())->method('beginTransaction');
		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage('non-empty');
		$this->queryService($database)->executeQuery(['type' => 'transaction', 'queries' => []]);
	}

	public function testInvalidTransactionSubqueryIsRolledBack(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects($this->once())->method('beginTransaction');
		$database->expects($this->once())->method('rollback');
		$database->expects($this->never())->method('commit');
		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage('Each transaction subquery');
		$this->queryService($database)->executeQuery([
			'type' => 'transaction', 'queries' => [['missing_type' => true]]
		]);
	}

	public function testTransactionRollsBackWhenNestedDatabaseWriteFails(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects($this->once())->method('beginTransaction');
		$database->expects($this->once())->method('rollback');
		$database->expects($this->never())->method('commit');
		$database->expects($this->once())->method('nonQuery')->willThrowException(new \RuntimeException('disk unavailable'));
		$compiler = $this->createStub(IQueryCompiler::class);
		$compiler->method('compile')->willReturn(new QueryStatement('UPDATE orders'));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Transaction subquery failed');
		$this->queryService($database, $compiler)->executeQuery([
			'type' => 'transaction', 'queries' => [['type' => 'update']]
		]);
	}

	private function queryService(IDatabase $database, ?IQueryCompiler $compiler = null): DefaultReportQueryService {
		$container = $this->createStub(IContainer::class);
		$container->method('get')->with('database')->willReturn($database);
		return new DefaultReportQueryService(
			$this->createStub(IQuerySchemaProvider::class),
			$compiler ?? $this->createStub(IQueryCompiler::class),
			$container
		);
	}

	private function makeTable(
		string $name,
		string $domain = 'd',
		string $category = 'c',
		array $tags = [],
		array $fields = []
	): TableMetadata {
		return new TableMetadata(
			name: $name,
			label: null,
			description: null,
			domain: $domain,
			category: $category,
			tags: $tags,
			fields: $fields,
			joins: [],
			defaultFilters: [],
			sensitive: false
		);
	}

	private function makeField(string $name, array $tags = []): FieldMetadata {
		return new FieldMetadata(
			name: $name,
			type: 'string',
			description: null,
			primaryKey: false,
			foreignKey: null,
			nullable: true,
			tags: $tags,
			alias: null,
			sensitive: false
		);
	}
}
