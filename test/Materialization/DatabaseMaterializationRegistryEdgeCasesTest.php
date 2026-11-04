<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use DataHawk\Materialization\DatabaseMaterializationRegistry;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Dto\MaterializationGeneration;
use ResourceFoundation\Dto\MaterializationManifest;

final class DatabaseMaterializationRegistryEdgeCasesTest extends TestCase {

	public function testCurrentGenerationQueryEscapesScopeAndLogicalTable(): void {
		$database = new DataHawkDatabaseFixture();
		(new DatabaseMaterializationRegistry($database))->getCurrentGeneration("o'brien", "table'name");
		self::assertStringContainsString("schema_name = 'o\\'brien'", $database->queries[0]);
		self::assertStringContainsString("logical_table = 'table\\'name'", $database->queries[0]);
		self::assertStringContainsString('is_current = 1', $database->queries[0]);
	}

	public function testCurrentGenerationWithoutSchemaFiltersOnlyLogicalTable(): void {
		$database = new DataHawkDatabaseFixture();
		(new DatabaseMaterializationRegistry($database))->getCurrentGeneration('', 'orders');
		self::assertStringContainsString("logical_table = 'orders'", $database->queries[0]);
		self::assertStringNotContainsString('AND schema_name =', $database->queries[0]);
	}

	public function testGenerationRowMissingMetadataUsesDefaultStatusAndNullCounts(): void {
		$database = new DataHawkDatabaseFixture();
		$database->singleRow = ['schema_name' => 'reports', 'logical_table' => 'orders', 'meta_json' => '17'];
		$generation = (new DatabaseMaterializationRegistry($database))->getCurrentGeneration('reports', 'orders');
		self::assertSame('published', $generation->status);
		self::assertSame([], $generation->meta);
		self::assertNull($generation->rowCount);
		self::assertNull($generation->publishedAt);
	}

	public function testPublishingGenerationWithoutStatusUsesPublishedDefault(): void {
		$database = new DataHawkDatabaseFixture();
		$generation = new MaterializationGeneration('reports', 'orders', 'base3_mat_reports_orders_1', '1', rowCount: null, status: '', publishedAt: 12);
		(new DatabaseMaterializationRegistry($database))->publishGeneration($generation);
		self::assertStringContainsString("'published', 1, 12", $database->nonQueries[3]);
		self::assertStringContainsString('NULL', $database->nonQueries[3]);
	}

	public function testInvalidUtf8MetadataDoesNotInjectUnserializableContent(): void {
		$database = new DataHawkDatabaseFixture();
		$generation = new MaterializationGeneration('reports', 'orders', 'base3_mat_reports_orders_1', '1', meta: ['invalid' => "\xB1"]);
		(new DatabaseMaterializationRegistry($database))->publishGeneration($generation);
		self::assertStringContainsString("'{}'", $database->nonQueries[3]);
	}

	public function testSuccessfulRunStoresRowCountAndEscapesErrorMessage(): void {
		$database = new DataHawkDatabaseFixture();
		(new DatabaseMaterializationRegistry($database))->finishRun(21, true, "it's complete", 17, ['source' => 'test']);
		self::assertStringContainsString("status = 'success'", $database->nonQueries[2]);
		self::assertStringContainsString("message = 'it\\'s complete'", $database->nonQueries[2]);
		self::assertStringContainsString('row_count = 17', $database->nonQueries[2]);
		self::assertStringContainsString('WHERE id = 21', $database->nonQueries[2]);
	}

	public function testNumericStringInsertIdIsAcceptedWithoutFollowupQuery(): void {
		$database = new DataHawkDatabaseFixture();
		$database->insertIdValue = '87';
		$manifest = new MaterializationManifest('orders', targetSchema: 'reports', logicalTable: 'orders');
		self::assertSame(87, (new DatabaseMaterializationRegistry($database))->startRun($manifest, 'base3_mat_orders_1', '1', 'full'));
		self::assertSame([], $database->queries);
	}

	public function testMissingInsertedRunIdReturnsZeroWhenLookupFindsNothing(): void {
		$database = new DataHawkDatabaseFixture();
		$database->insertIdValue = 'invalid';
		$manifest = new MaterializationManifest('orders', targetSchema: 'reports', logicalTable: 'orders');
		self::assertSame(0, (new DatabaseMaterializationRegistry($database))->startRun($manifest, 'base3_mat_orders_1', '1', 'full'));
		self::assertStringContainsString('ORDER BY id DESC LIMIT 1', $database->queries[0]);
	}

	public function testCleanupRetainsCurrentGenerationsEvenIfTablesAreMissing(): void {
		$database = new DataHawkDatabaseFixture();
		$database->onMultiQuery = static function(string $sql): array {
			if(str_starts_with($sql, 'SHOW TABLES')) return [];
			if(str_contains($sql, 'FROM `base3_mat_registry`')) return [
				['id' => 1, 'physical_table' => 'base3_mat_missing_current', 'is_current' => 1],
				['id' => 2, 'physical_table' => 'base3_mat_missing_old', 'is_current' => 0],
				['id' => 0, 'physical_table' => 'base3_mat_zero', 'is_current' => 0]
			];
			return [];
		};
		$report = (new DatabaseMaterializationRegistry($database))->cleanupHistory();
		self::assertSame(1, $report['registry']['deleted']);
		self::assertStringContainsString('WHERE id IN (2)', $database->nonQueries[2]);
		self::assertStringNotContainsString('1', substr($database->nonQueries[2], strrpos($database->nonQueries[2], 'WHERE id IN')));
	}

	public function testCleanupDoesNotDeleteExistingSafePhysicalTables(): void {
		$database = new DataHawkDatabaseFixture();
		$database->onMultiQuery = static function(string $sql): array {
			if(str_starts_with($sql, 'SHOW TABLES')) return [
				['table' => 'base3_mat_existing'], ['table' => 'base3_mat_existing'],
				['table' => 'unrelated_table'], ['table' => 'base3_mat_unsafe.dot']
			];
			if(str_contains($sql, 'FROM `base3_mat_registry`')) return [
				['id' => 3, 'physical_table' => 'base3_mat_existing', 'is_current' => 0],
				['id' => 4, 'physical_table' => 'base3_mat_unsafe.dot', 'is_current' => 0],
				['id' => 5, 'physical_table' => '', 'is_current' => 0]
			];
			return [];
		};
		$report = (new DatabaseMaterializationRegistry($database))->cleanupHistory();
		self::assertSame(2, $report['registry']['deleted']);
		self::assertStringContainsString('WHERE id IN (4,5)', $database->nonQueries[2]);
	}

	public function testCleanupClampsRetentionToOneAndSeparatesSchemas(): void {
		$database = new DataHawkDatabaseFixture();
		$database->onMultiQuery = static function(string $sql): array {
			if(str_starts_with($sql, 'SHOW TABLES')) return [];
			if(str_contains($sql, 'FROM `base3_mat_registry`')) return [];
			return [
				['id' => 10, 'schema_name' => 'alpha', 'manifest_id' => 'orders', 'status' => 'success'],
				['id' => 11, 'schema_name' => 'alpha', 'manifest_id' => 'orders', 'status' => 'failed'],
				['id' => 12, 'schema_name' => 'beta', 'manifest_id' => 'orders', 'status' => 'success'],
				['id' => 13, 'schema_name' => 'beta', 'manifest_id' => 'orders', 'status' => 'running']
			];
		};
		$report = (new DatabaseMaterializationRegistry($database))->cleanupHistory(0);
		self::assertSame(1, $report['runs']['keepRunsPerManifest']);
		self::assertSame(1, $report['runs']['deleted']);
		self::assertStringContainsString('WHERE id IN (11)', $database->nonQueries[2]);
	}

	public function testCleanupRetainsOneRunForEachUnknownManifestScope(): void {
		$database = new DataHawkDatabaseFixture();
		$database->onMultiQuery = static function(string $sql): array {
			if(str_starts_with($sql, 'SHOW TABLES')) return [];
			if(str_contains($sql, 'FROM `base3_mat_registry`')) return [];
			return [
				['id' => 2, 'schema_name' => 'alpha', 'manifest_id' => '', 'status' => 'failed'],
				['id' => 3, 'schema_name' => 'alpha', 'manifest_id' => '', 'status' => 'failed'],
				['id' => 4, 'schema_name' => 'beta', 'manifest_id' => '', 'status' => 'failed']
			];
		};
		$report = (new DatabaseMaterializationRegistry($database))->cleanupHistory(1);
		self::assertSame(1, $report['runs']['deleted']);
		self::assertStringContainsString('WHERE id IN (3)', $database->nonQueries[2]);
	}

	public function testCleanupBatchesLargeRegistryDeletions(): void {
		$database = new DataHawkDatabaseFixture();
		$database->onMultiQuery = static function(string $sql): array {
			if(str_starts_with($sql, 'SHOW TABLES')) return [];
			if(str_contains($sql, 'FROM `base3_mat_registry`')) {
				return array_map(static fn(int $id): array => ['id' => $id, 'physical_table' => 'base3_mat_missing_' . $id, 'is_current' => 0], range(1, 251));
			}
			return [];
		};
		$report = (new DatabaseMaterializationRegistry($database))->cleanupHistory();
		self::assertSame(251, $report['registry']['deleted']);
		$deleteQueries = array_values(array_filter($database->nonQueries, static fn(string $sql): bool => str_starts_with($sql, 'DELETE FROM `base3_mat_registry`')));
		self::assertCount(2, $deleteQueries);
		self::assertStringContainsString('WHERE id IN (1,2,3,', $deleteQueries[0]);
		self::assertStringContainsString('WHERE id IN (251)', $deleteQueries[1]);
	}
}
