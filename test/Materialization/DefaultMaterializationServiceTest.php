<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use DataHawk\Materialization\DefaultMaterializationService;
use DataHawk\Materialization\MaterializationPhysicalTableNameGenerator;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IMaterializationManifestProvider;
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\IMaterializationRunRepository;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\MaterializationGeneration;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\QueryResult;
use ResourceFoundation\Exception\MaterializationException;

final class DefaultMaterializationServiceTest extends TestCase {

	public function testUnknownManifestFailsWithoutExecutingQuery(): void {
		$provider = $this->createStub(IMaterializationManifestProvider::class);
		$provider->method('getManifest')->willReturn(null);
		$queries = $this->createMock(IQueryService::class);
		$queries->expects($this->never())->method('executeQuery');

		$this->expectException(MaterializationException::class);
		$this->expectExceptionMessage('Unknown materialization manifest');
		$this->service(null, $provider, $queries)->buildFull('missing');
	}

	public function testRejectsEmptyManifestIdBeforeWriting(): void {
		$this->expectException(MaterializationException::class);
		$this->expectExceptionMessage('id must not be empty');
		$this->service(new MaterializationManifest('', logicalTable: 'orders', query: ['table' => 'orders'], columns: [['name' => 'id']]))->buildFull('orders');
	}

	public function testRejectsMissingLogicalTableBeforeWriting(): void {
		$this->expectException(MaterializationException::class);
		$this->expectExceptionMessage('logical table');
		$this->service(new MaterializationManifest('orders', query: ['table' => 'orders'], columns: [['name' => 'id']]))->buildFull('orders');
	}

	public function testRejectsMissingSourceQuery(): void {
		$this->expectException(MaterializationException::class);
		$this->expectExceptionMessage('source query');
		$this->service(new MaterializationManifest('orders', logicalTable: 'orders', columns: [['name' => 'id']]))->buildFull('orders');
	}

	public function testRejectsMissingTargetColumns(): void {
		$this->expectException(MaterializationException::class);
		$this->expectExceptionMessage('target columns');
		$this->service(new MaterializationManifest('orders', logicalTable: 'orders', query: ['table' => 'orders']))->buildFull('orders');
	}

	public function testIncrementalBuildReturnsExplicitNonSuccess(): void {
		$result = $this->service($this->manifest())->buildIncremental('orders');
		self::assertFalse($result->success);
		self::assertNull($result->generation);
		self::assertSame('incremental', $result->meta['mode']);
		self::assertSame('orders', $result->manifestId);
	}

	public function testRefreshUsesIncrementalModeWithoutPublishing(): void {
		$manifest = $this->manifest();
		$manifest->refresh = ['mode' => 'incremental'];
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->never())->method('publishGeneration');
		$result = $this->service($manifest, null, null, $registry)->refresh('orders');
		self::assertFalse($result->success);
		self::assertSame('incremental', $result->meta['mode']);
	}

	public function testFullBuildPublishesOnlyAfterTableAndInsertSucceed(): void {
		$queriesIssued = [];
		$queries = $this->createStub(IQueryService::class);
		$queries->method('executeQuery')->willReturnCallback(static function(array $query) use (&$queriesIssued): QueryResult {
			$queriesIssued[] = $query;
			return new QueryResult([], [], affectedRows: 7);
		});
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->once())->method('publishGeneration')->with($this->callback(static fn($generation): bool =>
			$generation instanceof MaterializationGeneration &&
			$generation->schema === 'reports' &&
			$generation->logicalTable === 'orders' &&
			$generation->rowCount === 7 &&
			$generation->status === 'published'
		));

		$result = $this->service($this->manifest(), null, $queries, $registry)->buildFull('orders');
		self::assertTrue($result->success);
		self::assertSame(7, $result->rowCount);
		self::assertCount(2, $queriesIssued);
		self::assertSame('create', $queriesIssued[0]['type']);
		self::assertSame('insert', $queriesIssued[1]['type']);
		self::assertSame('reports', $queriesIssued[1]['from']['schema']);
		self::assertSame(['id'], $queriesIssued[1]['columns']);
		self::assertSame(['skipped' => 'database_not_available'], $result->meta['cleanup']);
		self::assertMatchesRegularExpression('/^base3_mat_reports_orders_\d{14}_[0-9a-f]{4}$/', $result->generation->physicalTable);
	}

	public function testGeneratedColumnsPreserveTypesAndConstraints(): void {
		$manifest = $this->manifest();
		$manifest->columns = [
			['name' => 'id', 'type' => 'bigint', 'required' => true, 'primary_key' => true, 'auto_increment' => true],
			['name' => 'price', 'type' => 'decimal', 'precision' => 9, 'scale' => 2, 'default' => 0],
			['name' => 'caption', 'type' => 'string', 'length' => 80, 'nullable' => true]
		];
		$queriesIssued = [];
		$queries = $this->createStub(IQueryService::class);
		$queries->method('executeQuery')->willReturnCallback(static function(array $query) use (&$queriesIssued): QueryResult {
			$queriesIssued[] = $query;
			return new QueryResult([], []);
		});

		self::assertTrue($this->service($manifest, null, $queries)->buildFull('orders')->success);
		$columns = $queriesIssued[0]['columns'];
		self::assertSame('BIGINT', $columns[0]['type']);
		self::assertFalse($columns[0]['nullable']);
		self::assertTrue($columns[0]['primary_key']);
		self::assertTrue($columns[0]['auto_increment']);
		self::assertSame('DECIMAL(9,2)', $columns[1]['type']);
		self::assertSame(0, $columns[1]['default']);
		self::assertSame('VARCHAR(80)', $columns[2]['type']);
		self::assertTrue($columns[2]['nullable']);
	}

	public function testIndexesAreCreatedAfterInsert(): void {
		$manifest = $this->manifest();
		$manifest->indexes = [
			'key' => ['columns' => ['id'], 'unique' => true],
			'ignored' => 'invalid',
			'unnamed' => ['id']
		];
		$issued = [];
		$queries = $this->createStub(IQueryService::class);
		$queries->method('executeQuery')->willReturnCallback(static function(array $query) use (&$issued): QueryResult {
			$issued[] = $query;
			return new QueryResult([], []);
		});

		self::assertTrue($this->service($manifest, null, $queries)->buildFull('orders')->success);
		self::assertSame(['create', 'insert', 'alter'], array_column($issued, 'type'));
		self::assertCount(2, $issued[2]['actions']);
		self::assertSame('ADD_UNIQUE_INDEX', $issued[2]['actions'][0]['action']);
		self::assertSame('ADD_INDEX', $issued[2]['actions'][1]['action']);
	}

	public function testDatabaseDebugErrorPreventsPublication(): void {
		$queries = $this->createStub(IQueryService::class);
		$queries->method('executeQuery')->willReturn(new QueryResult([], [], debugSql: '❌ DB Error: table already exists'));
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->never())->method('publishGeneration');

		$result = $this->service($this->manifest(), null, $queries, $registry)->buildFull('orders');
		self::assertFalse($result->success);
		self::assertNull($result->generation);
		self::assertStringContainsString('table already exists', $result->message);
		self::assertFalse($result->meta['dropFailedTable']);
	}

	public function testThrownQueryExceptionPreventsPublication(): void {
		$queries = $this->createStub(IQueryService::class);
		$queries->method('executeQuery')->willThrowException(new \RuntimeException('query service unavailable'));
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->never())->method('publishGeneration');
		$result = $this->service($this->manifest(), null, $queries, $registry)->buildFull('orders');
		self::assertFalse($result->success);
		self::assertSame('query service unavailable', $result->message);
	}

	public function testMissingColumnNameDoesNotPublishGeneration(): void {
		$manifest = $this->manifest();
		$manifest->columns = [['type' => 'integer']];
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->never())->method('publishGeneration');
		$result = $this->service($manifest, null, null, $registry)->buildFull('orders');
		self::assertFalse($result->success);
		self::assertStringContainsString('non-empty name', $result->message);
	}

	public function testRawSelectWithoutDatabaseIsRejectedAndNotPublished(): void {
		$manifest = $this->manifest();
		$manifest->query = ['type' => 'raw_select', 'sql' => 'SELECT 1'];
		$queries = $this->createStub(IQueryService::class);
		$queries->method('executeQuery')->willReturn(new QueryResult([], []));
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->never())->method('publishGeneration');
		$result = $this->service($manifest, null, $queries, $registry)->buildFull('orders');
		self::assertFalse($result->success);
		self::assertStringContainsString('require an IDatabase', $result->message);
	}

	public function testRecordsSuccessfulAndFailedRunsWithExistingRepository(): void {
		$queries = $this->createStub(IQueryService::class);
		$queries->method('executeQuery')->willReturn(new QueryResult([], [], affectedRows: 5));
		$runs = $this->createMock(IMaterializationRunRepository::class);
		$runs->expects($this->once())->method('startRun')->willReturn(51);
		$runs->expects($this->once())->method('finishRun')->with(51, true, 'Materialization full build completed.', 5, $this->isArray());

		self::assertTrue($this->service($this->manifest(), null, $queries, null, $runs)->buildFull('orders')->success);
	}

	private function manifest(): MaterializationManifest {
		return new MaterializationManifest('orders', sourceSchema: 'reports', targetSchema: 'reports', logicalTable: 'orders', query: ['table' => 'sales'], columns: [['name' => 'id', 'type' => 'integer']]);
	}

	private function service(?MaterializationManifest $manifest, ?IMaterializationManifestProvider $provider = null, ?IQueryService $queries = null, ?IMaterializationRegistry $registry = null, ?IMaterializationRunRepository $runs = null): DefaultMaterializationService {
		$provider ??= $this->createStub(IMaterializationManifestProvider::class);
		if($manifest !== null) $provider->method('getManifest')->willReturn($manifest);
		$queries ??= $this->createStub(IQueryService::class);
		$registry ??= $this->createStub(IMaterializationRegistry::class);
		return new DefaultMaterializationService($provider, $queries, $registry, new MaterializationPhysicalTableNameGenerator(), $runs);
	}
}
