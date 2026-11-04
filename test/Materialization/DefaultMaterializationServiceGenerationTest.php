<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use DataHawk\Materialization\DefaultMaterializationService;
use DataHawk\Materialization\MaterializationPhysicalTableNameGenerator;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IMaterializationManifestProvider;
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\MaterializationGeneration;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\QueryResult;

final class DefaultMaterializationServiceGenerationTest extends TestCase {

	public function testDatabaseCountTakesPrecedenceOverAffectedRows(): void {
		$database = new DataHawkDatabaseFixture();
		$database->scalarValue = '14';
		$result = $this->service($this->manifest(), $database)->buildFull('orders');
		self::assertTrue($result->success);
		self::assertSame(14, $result->rowCount);
		self::assertStringContainsString('SELECT COUNT(*) FROM `base3_mat_reports_orders_', $database->queries[0]);
	}

	public function testMissingDatabaseRowCountFallsBackToInsertedRows(): void {
		$database = new DataHawkDatabaseFixture();
		$database->scalarValue = null;
		$result = $this->service($this->manifest(), $database)->buildFull('orders');
		self::assertTrue($result->success);
		self::assertSame(3, $result->rowCount);
	}

	public function testColumnTypeMappingsIncludePrimitiveAndExplicitSqlTypes(): void {
		$manifest = $this->manifest();
		$types = ['int', 'smallint', 'tinyint', 'bool', 'float', 'double', 'date', 'datetime', 'timestamp', 'text', 'longtext', 'json', 'varchar(16)', 'mystery'];
		$manifest->columns = array_map(static fn(string $type): array => ['name' => 'col_' . str_replace(['(', ')'], '_', $type), 'type' => $type], $types);
		$issued = [];
		$queries = $this->queryService($issued);
		$result = $this->service($manifest, null, null, $queries)->buildFull('orders');
		self::assertTrue($result->success);
		self::assertSame([
			'INT', 'SMALLINT', 'TINYINT', 'TINYINT(1)', 'FLOAT', 'DOUBLE', 'DATE',
			'DATETIME', 'TIMESTAMP', 'TEXT', 'LONGTEXT', 'JSON', 'VARCHAR(16)', 'VARCHAR(255)'
		], array_column($issued[0]['columns'], 'type'));
	}

	public function testExplicitSourceQuerySchemaIsNotOverwritten(): void {
		$manifest = $this->manifest();
		$manifest->sourceSchema = 'default_source';
		$manifest->query = ['table' => 'original', 'schema' => 'explicit_source'];
		$issued = [];
		$result = $this->service($manifest, null, null, $this->queryService($issued))->buildFull('orders');
		self::assertTrue($result->success);
		self::assertSame('explicit_source', $issued[1]['from']['schema']);
	}

	public function testNamedUniqueIndexMayUseFieldsAliasAndCustomName(): void {
		$manifest = $this->manifest();
		$manifest->indexes = ['by_id' => ['name' => 'custom_unique_id', 'fields' => ['id'], 'unique' => true]];
		$issued = [];
		$result = $this->service($manifest, null, null, $this->queryService($issued))->buildFull('orders');
		self::assertTrue($result->success);
		self::assertSame('ADD_UNIQUE_INDEX', $issued[2]['actions'][0]['action']);
		self::assertSame('custom_unique_id', $issued[2]['actions'][0]['name']);
	}

	public function testGeneratedIndexNameIsSanitizedAndLimited(): void {
		$manifest = $this->manifest();
		$manifest->indexes = ['An Index/Name With (Special) Characters And Very Very Very Very Long Text' => ['columns' => ['id']]];
		$issued = [];
		$result = $this->service($manifest, null, null, $this->queryService($issued))->buildFull('orders');
		self::assertTrue($result->success);
		$name = $issued[2]['actions'][0]['name'];
		self::assertSame(64, strlen($name));
		self::assertMatchesRegularExpression('/^[a-z0-9_]+$/', $name);
		self::assertStringStartsWith('idx_orders_an_index_name', $name);
	}

	public function testUnsupportedIndexDefinitionsAreSkippedWithoutAlterQuery(): void {
		$manifest = $this->manifest();
		$manifest->indexes = ['empty' => ['columns' => []], 'bad' => ['columns' => 'id'], 'null' => null];
		$issued = [];
		$result = $this->service($manifest, null, null, $this->queryService($issued))->buildFull('orders');
		self::assertTrue($result->success);
		self::assertSame(['create', 'insert'], array_column($issued, 'type'));
	}

	public function testRawSelectResolvesExplicitPublishedTableReference(): void {
		$manifest = $this->manifest();
		$manifest->query = ['type' => 'raw_select', 'sql' => 'SELECT id FROM {{table:source.orders}}'];
		$database = new DataHawkDatabaseFixture();
		$database->scalarValue = '9';
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->once())->method('getCurrentGeneration')->with('source', 'orders')->willReturn(new MaterializationGeneration('source', 'orders', 'base3_mat_source_orders_1', '1'));
		$registry->expects($this->once())->method('publishGeneration');
		$issued = [];
		$result = $this->service($manifest, $database, $registry, $this->queryService($issued))->buildFull('orders');
		self::assertTrue($result->success);
		self::assertSame(9, $result->rowCount);
		self::assertSame(['create'], array_column($issued, 'type'));
		self::assertStringContainsString('SELECT id FROM `base3_mat_source_orders_1`', $database->nonQueries[0]);
	}

	public function testRawSelectUnqualifiedReferenceUsesSourceSchema(): void {
		$manifest = $this->manifest();
		$manifest->sourceSchema = 'source';
		$manifest->query = ['type' => 'raw-sql-select', 'sql' => 'SELECT id FROM {{table:orders}}'];
		$database = new DataHawkDatabaseFixture();
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->once())->method('getCurrentGeneration')->with('source', 'orders')->willReturn(new MaterializationGeneration('source', 'orders', 'base3_mat_source_orders_2', '2'));
		$issued = [];
		$result = $this->service($manifest, $database, $registry, $this->queryService($issued))->buildFull('orders');
		self::assertTrue($result->success);
		self::assertStringContainsString('FROM `base3_mat_source_orders_2`', $database->nonQueries[0]);
	}

	public function testRawSelectUnqualifiedReferenceUsesTargetSchemaWithoutSourceSchema(): void {
		$manifest = $this->manifest();
		$manifest->sourceSchema = '';
		$manifest->query = ['type' => 'raw_select', 'sql' => 'SELECT id FROM {{table:orders}}'];
		$database = new DataHawkDatabaseFixture();
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->once())->method('getCurrentGeneration')->with('reports', 'orders')->willReturn(new MaterializationGeneration('reports', 'orders', 'base3_mat_reports_orders_2', '2'));
		$result = $this->service($manifest, $database, $registry)->buildFull('orders');
		self::assertTrue($result->success);
		self::assertStringContainsString('FROM `base3_mat_reports_orders_2`', $database->nonQueries[0]);
	}

	public function testRawSelectWithoutSqlIsRejectedBeforeDatabaseInsertion(): void {
		$manifest = $this->manifest();
		$manifest->query = ['type' => 'raw_select', 'sql' => '   '];
		$database = new DataHawkDatabaseFixture();
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->never())->method('publishGeneration');
		$result = $this->service($manifest, $database, $registry)->buildFull('orders');
		self::assertFalse($result->success);
		self::assertStringContainsString('non-empty sql', $result->message);
		self::assertSame([], array_filter($database->nonQueries, static fn(string $sql): bool => str_starts_with($sql, 'INSERT INTO')));
	}

	public function testRawSelectRejectsUnpublishedPhysicalReference(): void {
		$manifest = $this->manifest();
		$manifest->query = ['type' => 'raw_select', 'sql' => 'SELECT id FROM {{table:source.unknown}}'];
		$database = new DataHawkDatabaseFixture();
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->once())->method('getCurrentGeneration')->with('source', 'unknown')->willReturn(null);
		$registry->expects($this->never())->method('publishGeneration');
		$result = $this->service($manifest, $database, $registry)->buildFull('orders');
		self::assertFalse($result->success);
		self::assertStringContainsString('unpublished table: source.unknown', $result->message);
	}

	public function testRawSelectRejectsUnsafePublishedPhysicalTableName(): void {
		$manifest = $this->manifest();
		$manifest->query = ['type' => 'raw_select', 'sql' => 'SELECT id FROM {{table:source.orders}}'];
		$database = new DataHawkDatabaseFixture();
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->once())->method('getCurrentGeneration')->willReturn(new MaterializationGeneration('source', 'orders', 'orders;DROP', '1'));
		$registry->expects($this->never())->method('publishGeneration');
		$result = $this->service($manifest, $database, $registry)->buildFull('orders');
		self::assertFalse($result->success);
		self::assertStringContainsString('Unsafe materialization physical table name', $result->message);
		self::assertSame([], array_filter($database->nonQueries, static fn(string $sql): bool => str_starts_with($sql, 'INSERT INTO')));
	}

	public function testCleanupRemovesOnlyOldNonCurrentGenerations(): void {
		$manifest = $this->manifest();
		$manifest->options['keepGenerations'] = 2;
		$database = new DataHawkDatabaseFixture();
		$prefix = 'base3_mat_reports_orders_';
		$oldest = $prefix . '20230101000000_aaaa';
		$old = $prefix . '20240101000000_aaaa';
		$newer = $prefix . '20250101000000_aaaa';
		$database->onListQuery = static function(string $sql) use ($database, $newer, $old, $oldest): array {
			foreach($database->queries as $query) {
				if(preg_match('/^SELECT COUNT\(\*\) FROM `([^`]+)`$/', $query, $matches) === 1) {
					return [$matches[1], $newer, $old, $oldest, 'another_table', 'base3_mat_reports_orders_unsafe.name'];
				}
			}
			return [];
		};
		$result = $this->service($manifest, $database)->buildFull('orders');
		self::assertTrue($result->success);
		self::assertSame(2, $result->meta['cleanup']['keepGenerations']);
		self::assertSame([$old, $oldest], $result->meta['cleanup']['dropped']);
		self::assertContains($result->generation->physicalTable, $result->meta['cleanup']['kept']);
		self::assertContains($newer, $result->meta['cleanup']['kept']);
		self::assertStringContainsString('DROP TABLE IF EXISTS `' . $oldest . '`', implode(' ', $database->nonQueries));
	}

	public function testCleanupKeepsCurrentGenerationEvenIfMoreRecentTablesExist(): void {
		$manifest = $this->manifest();
		$manifest->options['keep_generations'] = 1;
		$database = new DataHawkDatabaseFixture();
		$prefix = 'base3_mat_reports_orders_';
		$database->listRows = [$prefix . '20990101000000_aaaa', $prefix . '20230101000000_aaaa'];
		$result = $this->service($manifest, $database)->buildFull('orders');
		self::assertTrue($result->success);
		self::assertSame(1, $result->meta['cleanup']['keepGenerations']);
		self::assertContains($result->generation->physicalTable, $result->meta['cleanup']['kept']);
		self::assertSame([$prefix . '20230101000000_aaaa'], $result->meta['cleanup']['dropped']);
	}

	public function testCleanupExceptionAfterPublicationIsReportedButBuildRemainsSuccessful(): void {
		$database = new DataHawkDatabaseFixture();
		$database->onListQuery = static function(): array { throw new \RuntimeException('table listing unavailable'); };
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->once())->method('publishGeneration');
		$result = $this->service($this->manifest(), $database, $registry)->buildFull('orders');
		self::assertTrue($result->success);
		self::assertSame('table listing unavailable', $result->meta['cleanup']['error']);
	}

	public function testFailedBuildDropsTemporaryTableWithoutPublishing(): void {
		$database = new DataHawkDatabaseFixture();
		$queries = $this->createStub(IQueryService::class);
		$queries->method('executeQuery')->willThrowException(new \RuntimeException('create query failed'));
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->never())->method('publishGeneration');
		$result = $this->service($this->manifest(), $database, $registry, $queries)->buildFull('orders');
		self::assertFalse($result->success);
		self::assertTrue($result->meta['dropFailedTable']);
		self::assertCount(1, $database->nonQueries);
		self::assertStringStartsWith('DROP TABLE IF EXISTS `base3_mat_', $database->nonQueries[0]);
	}

	public function testFailureToDropTemporaryTableDoesNotMaskBuildError(): void {
		$database = new DataHawkDatabaseFixture();
		$database->onWrite = static function(): void { throw new \RuntimeException('cannot drop table'); };
		$queries = $this->createStub(IQueryService::class);
		$queries->method('executeQuery')->willThrowException(new \RuntimeException('create query failed'));
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->never())->method('publishGeneration');
		$result = $this->service($this->manifest(), $database, $registry, $queries)->buildFull('orders');
		self::assertFalse($result->success);
		self::assertSame('create query failed', $result->message);
		self::assertFalse($result->meta['dropFailedTable']);
	}

	private function manifest(): MaterializationManifest {
		return new MaterializationManifest(
			id: 'orders', sourceSchema: 'source', targetSchema: 'reports', logicalTable: 'orders',
			query: ['type' => 'select', 'table' => 'sales'],
			columns: [['name' => 'id', 'type' => 'integer']]
		);
	}

	private function queryService(array &$issued): IQueryService {
		$queries = $this->createStub(IQueryService::class);
		$queries->method('executeQuery')->willReturnCallback(static function(array $query) use (&$issued): QueryResult {
			$issued[] = $query;
			return new QueryResult([], [], affectedRows: 3);
		});
		return $queries;
	}

	private function service(MaterializationManifest $manifest, ?DataHawkDatabaseFixture $database = null, ?IMaterializationRegistry $registry = null, ?IQueryService $queries = null): DefaultMaterializationService {
		$provider = $this->createStub(IMaterializationManifestProvider::class);
		$provider->method('getManifest')->willReturn($manifest);
		$registry ??= $this->createStub(IMaterializationRegistry::class);
		if($queries === null) {
			$issued = [];
			$queries = $this->queryService($issued);
		}
		return new DefaultMaterializationService($provider, $queries, $registry, new MaterializationPhysicalTableNameGenerator(), null, $database);
	}
}
