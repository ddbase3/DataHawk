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

class DefaultReportQueryServiceTest extends TestCase {

        public function testListTablesDelegatesToSchemaProvider(): void {
                $schema = $this->createMock(IQuerySchemaProvider::class);
                $schema->expects($this->once())->method('getSchema')->willReturn([$this->makeTable('t')]);

                $compiler = $this->createStub(IQueryCompiler::class);
                $db = $this->createStub(IDatabase::class);
                $container = new FakeContainer(['database' => $db]);

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
                $container = new FakeContainer(['database' => $db]);

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
                        ->with($stmt->sql, $stmt->params)
                        ->willReturn([['A' => 123, 'B' => 'x']]);

                $container = new FakeContainer(['database' => $db]);

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
                // mismatch: row key is "X" but expected "A"
                $db->expects($this->once())->method('multiQuery')->willReturn([['X' => 1]]);

                $container = new FakeContainer(['database' => $db]);

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
                // "unexpected" column names should not throw in wildcard mode
                $db->expects($this->once())->method('multiQuery')->willReturn([['X' => 1]]);

                $container = new FakeContainer(['database' => $db]);

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

                $container = new FakeContainer(['database' => $db]);

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
                $container = new FakeContainer(['database' => $db]);

                $svc = new DefaultReportQueryService($schema, $compiler, $container);

                $this->assertSame(['crm'], $svc->listDomains());
                $this->assertSame(['core', 'sales'], $svc->listCategories());

                $tags = $svc->listTags();
                sort($tags);
                $this->assertSame(['a', 'b', 'x', 'y'], $tags);
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

/**
 * Minimal container for tests: only get('database') is used.
 */
class FakeContainer implements IContainer {

        public function __construct(private array $items = []) {}

        public function getServiceList(): array {
                return array_keys($this->items);
        }

        public function set(string $name, $classDefinition, $flags = 0): IContainer {
                $this->items[$name] = $classDefinition;
                return $this;
        }

        public function remove(string $name) {
                unset($this->items[$name]);
        }

        public function has(string $name): bool {
                return array_key_exists($name, $this->items);
        }

        public function get(string $name) {
                return $this->items[$name] ?? null;
        }
}
