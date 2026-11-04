<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use DataHawk\Materialization\DatabaseMaterializationRegistry;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Dto\MaterializationGeneration;
use ResourceFoundation\Dto\MaterializationManifest;

final class DatabaseMaterializationRegistryTest extends TestCase {

	public function testFailsClosedWhenDatabaseConnectionIsUnavailable(): void {
		$database = new DataHawkDatabaseFixture();
		$database->allowConnection = false;
		$registry = new DatabaseMaterializationRegistry($database);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('requires an active database connection');
		$registry->ensureTables();
	}

	public function testEnsuresPrivateTablesOnlyOnce(): void {
		$database = new DataHawkDatabaseFixture();
		$registry = new DatabaseMaterializationRegistry($database);
		$registry->ensureTables();
		$registry->ensureTables();

		self::assertSame(1, $database->connectCalls);
		self::assertCount(2, $database->nonQueries);
		self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `base3_mat_registry`', $database->nonQueries[0]);
		self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `base3_mat_run`', $database->nonQueries[1]);
	}

	public function testMissingCurrentGenerationReturnsNull(): void {
		$database = new DataHawkDatabaseFixture();
		$registry = new DatabaseMaterializationRegistry($database);
		self::assertNull($registry->getCurrentGeneration('reports', 'orders'));
		self::assertStringContainsString("schema_name = 'reports'", $database->queries[0]);
		self::assertStringContainsString('is_current = 1', $database->queries[0]);
	}

	public function testConvertsPublishedRegistryRowToGenerationDto(): void {
		$database = new DataHawkDatabaseFixture();
		$database->singleRow = [
			'schema_name' => 'reports', 'logical_table' => 'orders', 'physical_table' => 'base3_mat_orders_1',
			'generation' => '1', 'schema_hash' => 'schema', 'query_hash' => 'query', 'row_count' => '17',
			'status' => 'published', 'published_at' => '123', 'meta_json' => '{"mode":"full"}'
		];
		$generation = (new DatabaseMaterializationRegistry($database))->getCurrentGeneration('reports', 'orders');
		self::assertInstanceOf(MaterializationGeneration::class, $generation);
		self::assertSame('base3_mat_orders_1', $generation->physicalTable);
		self::assertSame(17, $generation->rowCount);
		self::assertSame(123, $generation->publishedAt);
		self::assertSame(['mode' => 'full'], $generation->meta);
	}

	public function testInvalidMetadataIsNotInterpretedAsValidJson(): void {
		$database = new DataHawkDatabaseFixture();
		$database->singleRow = ['logical_table' => 'orders', 'meta_json' => '{'];
		$generation = (new DatabaseMaterializationRegistry($database))->getCurrentGeneration('', 'orders');
		self::assertSame([], $generation->meta);
		self::assertNull($generation->rowCount);
	}

	public function testListsGenerationRowsAndDecodesMetadata(): void {
		$database = new DataHawkDatabaseFixture();
		$database->multiRows = [
			['schema_name' => 'reports', 'logical_table' => 'orders', 'physical_table' => 'base3_mat_orders_2', 'generation' => '2'],
			['schema_name' => 'reports', 'logical_table' => 'orders', 'physical_table' => 'base3_mat_orders_1', 'generation' => '1']
		];
		$generations = (new DatabaseMaterializationRegistry($database))->listGenerations('reports', 'orders');
		self::assertSame(['2', '1'], array_map(fn(MaterializationGeneration $generation) => $generation->generation, $generations));
		self::assertStringContainsString('ORDER BY published_at DESC', $database->queries[0]);
	}

	public function testPublishFirstWithdrawsCurrentGenerationForSameLogicalTable(): void {
		$database = new DataHawkDatabaseFixture();
		$generation = new MaterializationGeneration('reports', 'orders', 'base3_mat_orders_2', '2', rowCount: 20, meta: ['source' => 'manual']);
		(new DatabaseMaterializationRegistry($database))->publishGeneration($generation);

		self::assertCount(4, $database->nonQueries);
		self::assertStringContainsString('SET is_current = 0', $database->nonQueries[2]);
		self::assertStringContainsString("logical_table = 'orders'", $database->nonQueries[2]);
		self::assertStringContainsString('INSERT INTO `base3_mat_registry`', $database->nonQueries[3]);
		self::assertStringContainsString(addslashes('{"source":"manual"}'), $database->nonQueries[3]);
	}

	public function testRunCreationMarksPreviousRunningEntriesAsFailed(): void {
		$database = new DataHawkDatabaseFixture();
		$database->insertIdValue = 73;
		$manifest = new MaterializationManifest('orders', targetSchema: 'reports', logicalTable: 'orders');
		$runId = (new DatabaseMaterializationRegistry($database))->startRun($manifest, 'base3_mat_orders_2', '2', 'full');

		self::assertSame(73, $runId);
		self::assertStringContainsString('Superseded by a newer materialization run', $database->nonQueries[2]);
		self::assertStringContainsString('INSERT INTO `base3_mat_run`', $database->nonQueries[3]);
		self::assertStringContainsString("'running'", $database->nonQueries[3]);
	}

	public function testRunCreationLooksUpInsertedIdWhenBackendCannotSupplyIt(): void {
		$database = new DataHawkDatabaseFixture();
		$database->insertIdValue = 0;
		$database->singleRow = ['id' => 91];
		$manifest = new MaterializationManifest('orders', targetSchema: 'reports', logicalTable: 'orders');
		$runId = (new DatabaseMaterializationRegistry($database))->startRun($manifest, 'base3_mat_orders_2', '2', 'full');

		self::assertSame(91, $runId);
		self::assertStringContainsString('ORDER BY id DESC LIMIT 1', $database->queries[0]);
	}

	public function testFinishingFailedRunPersistsFailureAndNullRows(): void {
		$database = new DataHawkDatabaseFixture();
		(new DatabaseMaterializationRegistry($database))->finishRun(9, false, 'failed', null, ['reason' => 'test']);

		self::assertStringContainsString("status = 'failed'", $database->nonQueries[2]);
		self::assertStringContainsString('row_count = NULL', $database->nonQueries[2]);
		self::assertStringContainsString('WHERE id = 9', $database->nonQueries[2]);
	}

	public function testCleanupPreservesCurrentRegistryRowsAndRunningJobs(): void {
		$database = new DataHawkDatabaseFixture();
		$database->onMultiQuery = static function(string $sql): array {
			if(str_starts_with($sql, 'SHOW TABLES')) return [['Tables_in_database' => 'base3_mat_orders_1']];
			if(str_contains($sql, 'FROM `base3_mat_registry`')) return [
				['id' => 1, 'physical_table' => 'base3_mat_orders_1', 'is_current' => 1],
				['id' => 2, 'physical_table' => 'base3_mat_missing_1', 'is_current' => 0]
			];
			return [
				['id' => 10, 'manifest_id' => 'orders', 'schema_name' => 'reports', 'status' => 'success'],
				['id' => 11, 'manifest_id' => 'orders', 'schema_name' => 'reports', 'status' => 'success'],
				['id' => 12, 'manifest_id' => 'orders', 'schema_name' => 'reports', 'status' => 'running']
			];
		};
		$report = (new DatabaseMaterializationRegistry($database))->cleanupHistory(1);

		self::assertSame(1, $report['registry']['deleted']);
		self::assertSame(1, $report['runs']['deleted']);
		self::assertStringContainsString('WHERE id IN (2)', $database->nonQueries[2]);
		self::assertStringContainsString('WHERE id IN (11)', $database->nonQueries[3]);
	}

	public function testCleanupWithNoRowsDoesNotExecuteDeletes(): void {
		$database = new DataHawkDatabaseFixture();
		$report = (new DatabaseMaterializationRegistry($database))->cleanupHistory();
		self::assertSame(0, $report['registry']['deleted']);
		self::assertSame(0, $report['runs']['deleted']);
		self::assertCount(2, $database->nonQueries);
	}
}

require_once __DIR__ . '/DataHawkDatabaseFixture.php';
