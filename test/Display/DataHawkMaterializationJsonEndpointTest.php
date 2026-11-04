<?php declare(strict_types=1);

namespace DataHawk\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IContainer;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\State\Api\IStateStore;
use DataHawk\Display\DataHawkMaterializationOverviewDisplay;
use DataHawk\Test\Materialization\DataHawkDatabaseFixture;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IMaterializationManifestProvider;
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\IMaterializationService;
use ResourceFoundation\Api\IReportingScopeRegistry;
use ResourceFoundation\Api\IScopedMaterializationManifestProvider;
use ResourceFoundation\Dto\MaterializationGeneration;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\MaterializationRunResult;
use ResourceFoundation\Dto\ReportingScopeDefinition;

final class DataHawkMaterializationJsonEndpointTest extends TestCase {

	public function testPageUsesFirstReportingScopeAndListsAvailableScopes(): void {
		$scopes = [
			'first' => $this->scope('first', ['alpha']),
			'second' => $this->scope('second', ['beta'])
		];
		$provider = $this->provider([
			'alpha' => [$this->manifest('orders', 'alpha')],
			'beta' => [$this->manifest('customers', 'beta')]
		]);

		$response = $this->response(['mode' => 'page'], [IMaterializationManifestProvider::class => $provider], $scopes);
		self::assertTrue($response['ok']);
		self::assertSame('first', $response['reportingScope']);
		self::assertSame([
			['id' => 'first', 'label' => 'First'],
			['id' => 'second', 'label' => 'Second']
		], $response['reportingScopes']);
		self::assertSame(['orders'], array_column($response['manifests'], 'id'));
	}

	public function testPageOnlyLoadsManifestsFromSelectedReportingScope(): void {
		$scopes = ['first' => $this->scope('first', ['alpha']), 'second' => $this->scope('second', ['beta'])];
		$provider = $this->provider([
			'alpha' => [$this->manifest('orders', 'alpha')],
			'beta' => [$this->manifest('customers', 'beta')]
		]);

		$response = $this->response(['reportingScope' => 'second'], [IMaterializationManifestProvider::class => $provider], $scopes);
		self::assertSame('second', $response['reportingScope']);
		self::assertSame(['customers'], array_column($response['manifests'], 'id'));
	}

	public function testManifestGridOrdersByPriorityThenNaturalId(): void {
		$provider = $this->provider(['alpha' => [
			$this->manifest('item10', 'alpha', priority: 10),
			$this->manifest('item2', 'alpha', priority: 10),
			$this->manifest('later', 'alpha', priority: 20),
			$this->manifest('first', 'alpha', priority: 1)
		]]);
		$response = $this->response(['mode' => 'page'], [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertSame(['first', 'item2', 'item10', 'later'], array_column($response['manifests'], 'id'));
	}

	public function testDisabledManifestIsNotDueAndHasExplicitDisabledText(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('off', 'alpha', enabled: false, schedule: ['policy' => 'always'])]]);
		$response = $this->response([], [IMaterializationManifestProvider::class => $provider, IStateStore::class => $this->state()], $this->scopes());
		self::assertFalse($response['manifests'][0]['is_due']);
		self::assertSame('disabled', $response['manifests'][0]['due_text']);
		self::assertSame(0, $response['overview']['enabled_manifest_count']);
	}

	public function testAlwaysScheduledManifestIsDueWithStateStore(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('live', 'alpha', schedule: ['policy' => 'always'])]]);
		$response = $this->response([], [IMaterializationManifestProvider::class => $provider, IStateStore::class => $this->state()], $this->scopes());
		self::assertTrue($response['manifests'][0]['is_due']);
		self::assertSame('always due', $response['manifests'][0]['due_text']);
		self::assertSame(1, $response['overview']['due_manifest_count']);
	}

	public function testAlwaysScheduledManifestWithoutStateStoreIsNotDue(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('live', 'alpha', schedule: ['policy' => 'always'])]]);
		$response = $this->response([], [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertFalse($response['manifests'][0]['is_due']);
		self::assertSame([], $this->response(['mode' => 'grid', 'gridView' => 'overview_due'], [IMaterializationManifestProvider::class => $provider], $this->scopes())['data']);
	}

	public function testManualScheduleIsNotDueAndDisplaysManualText(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('manual', 'alpha', schedule: ['policy' => 'manual'])]]);
		$response = $this->response([], [IMaterializationManifestProvider::class => $provider, IStateStore::class => $this->state()], $this->scopes());
		self::assertFalse($response['manifests'][0]['is_due']);
		self::assertSame('manual', $response['manifests'][0]['due_text']);
		self::assertSame('manual', $response['manifests'][0]['schedule_text']);
	}

	public function testRecentIntervalSuccessDelaysNextRefresh(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha', schedule: ['policy' => 'interval', 'seconds' => 3600])]]);
		$state = $this->state(['datahawk.materialization.alpha:orders.last_success_at' => time()]);
		$response = $this->response([], [IMaterializationManifestProvider::class => $provider, IStateStore::class => $state], $this->scopes());
		self::assertFalse($response['manifests'][0]['is_due']);
		self::assertStringStartsWith('next ', $response['manifests'][0]['due_text']);
		self::assertSame('every 3600 sec', $response['manifests'][0]['schedule_text']);
	}

	public function testIntervalWithoutSuccessIsImmediatelyDue(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha', schedule: ['policy' => 'interval', 'seconds' => 100])]]);
		$response = $this->response([], [IMaterializationManifestProvider::class => $provider, IStateStore::class => $this->state()], $this->scopes());
		self::assertTrue($response['manifests'][0]['is_due']);
		self::assertSame('due', $response['manifests'][0]['due_text']);
	}

	public function testDailyScheduleAlreadySuccessfulTodayIsNotDue(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha', schedule: ['policy' => 'daily_after', 'time' => '00:00'])]]);
		$state = $this->state([
			'datahawk.materialization.alpha:orders.last_success_date' => date('Y-m-d'),
			'datahawk.materialization.alpha:orders.last_success_at' => time()
		]);
		$response = $this->response([], [IMaterializationManifestProvider::class => $provider, IStateStore::class => $state], $this->scopes());
		self::assertFalse($response['manifests'][0]['is_due']);
		self::assertSame('done today', $response['manifests'][0]['due_text']);
		self::assertSame('daily after 00:00', $response['manifests'][0]['schedule_text']);
	}

	public function testDueGridOnlyReturnsEligibleManifests(): void {
		$provider = $this->provider(['alpha' => [
			$this->manifest('always', 'alpha', schedule: ['policy' => 'always']),
			$this->manifest('manual', 'alpha', schedule: ['policy' => 'manual']),
			$this->manifest('disabled', 'alpha', enabled: false, schedule: ['policy' => 'always'])
		]]);
		$response = $this->response(['mode' => 'grid', 'gridView' => 'overview_due'], [IMaterializationManifestProvider::class => $provider, IStateStore::class => $this->state()], $this->scopes());
		self::assertSame(['always'], array_column($response['data'], 'id'));
		self::assertSame(1, $response['total']);
	}

	public function testManifestGridSearchMatchesNestedFieldsIgnoringCase(): void {
		$provider = $this->provider(['alpha' => [
			$this->manifest('a', 'alpha', dependsOn: ['NESTED_Target']),
			$this->manifest('b', 'alpha', dependsOn: ['unrelated'])
		]]);
		$response = $this->response(['mode' => 'grid', 'gridView' => 'manifests', 'search' => 'nested_target'], [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertSame(['a'], array_column($response['data'], 'id'));
	}

	public function testManifestGridFiltersBooleanAndArrayColumns(): void {
		$provider = $this->provider(['alpha' => [
			$this->manifest('enabled', 'alpha', dependsOn: ['child', 'other']),
			$this->manifest('disabled', 'alpha', enabled: false, dependsOn: ['unrelated'])
		]]);
		$response = $this->response(['mode' => 'grid', 'gridView' => 'manifests', 'filters' => ['enabled' => '1', 'depends_on' => 'CHILD']], [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertSame(['enabled'], array_column($response['data'], 'id'));
		self::assertSame(['enabled' => '1', 'depends_on' => 'CHILD'], $response['appliedFilters']);
	}

	public function testInvalidFilterValuesAreDiscardedBeforeSearch(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha')]]);
		$response = $this->response(['mode' => 'grid', 'gridView' => 'manifests', 'filters' => ['id' => ' orders ', 'bogus' => [], 'empty' => '   ', 'null' => null]], [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertSame(['id' => 'orders'], $response['appliedFilters']);
		self::assertSame(1, $response['total']);
	}

	public function testGridNumericSortDescendingIgnoresLexicalOrdering(): void {
		$provider = $this->provider(['alpha' => [
			$this->manifest('three', 'alpha', priority: 3),
			$this->manifest('twenty', 'alpha', priority: 20),
			$this->manifest('hundred', 'alpha', priority: 100)
		]]);
		$response = $this->response(['mode' => 'grid', 'gridView' => 'manifests', 'sort' => [['key' => 'priority', 'dir' => 'DESC', 'type' => 'number']]], [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertSame(['hundred', 'twenty', 'three'], array_column($response['data'], 'id'));
		self::assertSame('desc', $response['appliedSort'][0]['dir']);
		self::assertSame('number', $response['appliedSort'][0]['type']);
	}

	public function testLegacySortFieldsAreRespected(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('a2', 'alpha'), $this->manifest('a10', 'alpha')]]);
		$response = $this->response(['mode' => 'grid', 'gridView' => 'manifests', 'sortKey' => 'id', 'sortDirection' => 'desc'], [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertSame(['a10', 'a2'], array_column($response['data'], 'id'));
		self::assertSame('string', $response['appliedSort'][0]['type']);
	}

	public function testUnsupportedSortTypeAndDirectionUseSafeDefaults(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('a', 'alpha'), $this->manifest('b', 'alpha')]]);
		$response = $this->response(['mode' => 'grid', 'gridView' => 'manifests', 'sort' => [['key' => 'id', 'dir' => 'sideways', 'type' => 'unsupported']]], [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertSame(['a', 'b'], array_column($response['data'], 'id'));
		self::assertSame(['key' => 'id', 'dir' => 'asc', 'type' => 'string'], $response['appliedSort'][0]);
	}

	public function testGridPaginatesAfterSortingAndNormalizesPageBounds(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('c', 'alpha'), $this->manifest('a', 'alpha'), $this->manifest('b', 'alpha')]]);
		$services = [IMaterializationManifestProvider::class => $provider];
		$page1 = $this->response(['mode' => 'grid', 'gridView' => 'manifests', 'page' => 0, 'pageSize' => 2], $services, $this->scopes());
		$page2 = $this->response(['mode' => 'grid', 'gridView' => 'manifests', 'page' => 2, 'pageSize' => 2], $services, $this->scopes());
		self::assertSame(1, $page1['page']);
		self::assertSame(['a', 'b'], array_column($page1['data'], 'id'));
		self::assertTrue($page1['hasMore']);
		self::assertSame(2, $page1['totalPages']);
		self::assertSame(['c'], array_column($page2['data'], 'id'));
		self::assertFalse($page2['hasMore']);
	}

	public function testUnknownGridViewFallsBackToManifestRows(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha')]]);
		$response = $this->response(['mode' => 'grid', 'gridView' => 'not_a_view'], [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertSame('not_a_view', $response['gridView']);
		self::assertSame(['orders'], array_column($response['data'], 'id'));
	}

	public function testRegistryGridNormalizesStoredTypesAndMetadata(): void {
		$database = new DataHawkDatabaseFixture();
		$database->singleRow = ['table' => 'base3_mat_registry'];
		$database->onMultiQuery = static fn(string $sql): array => str_contains($sql, 'FROM `base3_mat_registry`') ? [[
			'id' => '8', 'schema_name' => 'alpha', 'logical_table' => 'orders', 'physical_table' => 'base3_mat_alpha_orders_8',
			'generation' => 'g8', 'row_count' => '12', 'is_current' => '1', 'published_at' => '1000', 'meta_json' => '{"mode":"full"}'
		]] : [];
		$response = $this->response(['mode' => 'grid', 'gridView' => 'registry'], [], $this->scopes(), $database);
		self::assertSame(1, $response['total']);
		self::assertSame(8, $response['data'][0]['id']);
		self::assertSame(12, $response['data'][0]['row_count']);
		self::assertSame('alpha', $response['data'][0]['scope']);
		self::assertSame('first', $response['data'][0]['reporting_scope']);
		self::assertSame(['mode' => 'full'], $response['data'][0]['meta']);
	}

	public function testRegistryGridRejectsInvalidStoredJson(): void {
		$database = new DataHawkDatabaseFixture();
		$database->singleRow = ['table' => 'base3_mat_registry'];
		$database->onMultiQuery = static fn(string $sql): array => str_contains($sql, 'FROM `base3_mat_registry`') ? [['id' => '4', 'meta_json' => '{bad']] : [];
		$response = $this->response(['mode' => 'grid', 'gridView' => 'registry'], [], $this->scopes(), $database);
		self::assertSame([], $response['data'][0]['meta']);
		self::assertNull($response['data'][0]['row_count']);
		self::assertSame('', $response['data'][0]['published_text']);
	}

	public function testRegistryQueriesAreLimitedToSelectedScopeAndEscapeIdentifiers(): void {
		$database = new DataHawkDatabaseFixture();
		$database->singleRow = ['table' => 'base3_mat_registry'];
		$this->response(['mode' => 'grid', 'gridView' => 'registry'], [], ['first' => $this->scope('first', ['alpha', "o'brien"])], $database);
		self::assertCount(2, $database->queries);
		self::assertStringContainsString("schema_name IN ('alpha', 'o\\'brien')", $database->queries[1]);
	}

	public function testRunGridCalculatesNonNegativeDurationAndParsesMetadata(): void {
		$database = new DataHawkDatabaseFixture();
		$database->singleRow = ['table' => 'base3_mat_run'];
		$database->onMultiQuery = static fn(string $sql): array => str_contains($sql, 'FROM `base3_mat_run`') ? [
			['id' => 3, 'manifest_id' => 'orders', 'schema_name' => 'alpha', 'status' => 'success', 'started_at' => '100', 'finished_at' => '112', 'meta_json' => '{"x":1}'],
			['id' => 2, 'manifest_id' => 'orders', 'schema_name' => 'alpha', 'status' => 'failed', 'started_at' => '112', 'finished_at' => '100', 'meta_json' => 'null']
		] : [];
		$response = $this->response(['mode' => 'grid', 'gridView' => 'runs', 'sort' => [['key' => 'id', 'type' => 'number']]], [], $this->scopes(), $database);
		self::assertSame([2, 3], array_column($response['data'], 'id'));
		self::assertSame(0, $response['data'][0]['duration']);
		self::assertSame(12, $response['data'][1]['duration']);
		self::assertSame(['x' => 1], $response['data'][1]['meta']);
		self::assertSame([], $response['data'][0]['meta']);
	}

	public function testDisconnectedDatabaseProducesNoRegistryRunsOrTables(): void {
		$database = new DataHawkDatabaseFixture();
		$database->allowConnection = false;
		$response = $this->response(['mode' => 'page'], [], $this->scopes(), $database);
		self::assertSame([], $response['registry']);
		self::assertSame([], $response['runs']);
		self::assertSame([], $response['tables']);
		self::assertSame(0, $response['overview']['materialized_table_count']);
	}

	public function testTablesGridExcludesTechnicalUnregisteredAndEmptyTableNames(): void {
		$database = new DataHawkDatabaseFixture();
		$database->singleRow = ['table' => 'base3_mat_registry'];
		$database->onMultiQuery = static function(string $sql): array {
			if(str_starts_with($sql, 'SHOW TABLES')) return [
				['table' => 'base3_mat_registry'], ['table' => 'base3_mat_run'], ['table' => ''],
				['table' => 'base3_mat_not_in_registry'], ['table' => 'base3_mat_alpha_orders_8'],
			];
			if(str_contains($sql, 'FROM `base3_mat_registry`')) return [[
				'id' => 8, 'schema_name' => 'alpha', 'logical_table' => 'orders',
				'physical_table' => 'base3_mat_alpha_orders_8', 'generation' => '8', 'row_count' => '15', 'is_current' => 1
			]];
			return [];
		};
		$response = $this->response(['mode' => 'grid', 'gridView' => 'tables'], [], $this->scopes(), $database);
		self::assertSame(['base3_mat_alpha_orders_8'], array_column($response['data'], 'table_name'));
		self::assertTrue($response['data'][0]['is_current']);
		self::assertTrue($response['data'][0]['is_registered']);
		self::assertSame(15, $response['data'][0]['row_count']);
	}

	public function testTablesGridSortsRegisteredTableNamesNaturally(): void {
		$database = new DataHawkDatabaseFixture();
		$database->singleRow = ['table' => 'base3_mat_registry'];
		$database->onMultiQuery = static function(string $sql): array {
			if(str_starts_with($sql, 'SHOW TABLES')) return [['table' => 'base3_mat_t10'], ['table' => 'base3_mat_t2']];
			if(str_contains($sql, 'FROM `base3_mat_registry`')) return [
				['physical_table' => 'base3_mat_t10', 'schema_name' => 'alpha'],
				['physical_table' => 'base3_mat_t2', 'schema_name' => 'alpha']
			];
			return [];
		};
		$response = $this->response(['mode' => 'page'], [], $this->scopes(), $database);
		self::assertSame(['base3_mat_t2', 'base3_mat_t10'], array_column($response['tables'], 'table_name'));
	}

	public function testRefreshManifestRejectsMissingIdBeforeResolvingDependencies(): void {
		$response = $this->response(['mode' => 'refresh_manifest']);
		self::assertFalse($response['ok']);
		self::assertSame('refresh_manifest', $response['mode']);
		self::assertSame('Missing materialization manifest id.', $response['error']);
	}

	public function testRefreshManifestRejectsUnknownReportingScope(): void {
		$response = $this->response(['mode' => 'refresh_manifest', 'manifestId' => 'orders', 'reportingScope' => 'missing', 'scope' => 'alpha'], [], $this->scopes());
		self::assertFalse($response['ok']);
		self::assertStringContainsString('Unknown reporting scope', $response['error']);
	}

	public function testRefreshManifestRejectsTechnicalScopeOutsideReportingScope(): void {
		$response = $this->response(['mode' => 'refresh_manifest', 'manifestId' => 'orders', 'reportingScope' => 'first', 'scope' => 'beta'], [], $this->scopes());
		self::assertFalse($response['ok']);
		self::assertStringContainsString('Unknown materialization scope', $response['error']);
	}

	public function testRefreshManifestRejectsUnscopedManifestProvider(): void {
		$provider = $this->createStub(IMaterializationManifestProvider::class);
		$response = $this->response(['mode' => 'refresh_manifest', 'manifestId' => 'orders', 'scope' => 'alpha'], [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertFalse($response['ok']);
		self::assertSame('Materialization scopes are not supported.', $response['error']);
	}

	public function testRefreshManifestRejectsUnknownManifestWithoutCallingService(): void {
		$provider = $this->provider(['alpha' => []]);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->never())->method('refresh');
		$response = $this->response(['mode' => 'refresh_manifest', 'manifestId' => 'unknown', 'scope' => 'alpha'], [IMaterializationManifestProvider::class => $provider, IMaterializationService::class => $service], $this->scopes());
		self::assertFalse($response['ok']);
		self::assertStringContainsString('Unknown materialization manifest', $response['error']);
	}

	public function testRefreshManifestRejectsMissingService(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha')]]);
		$response = $this->response(['mode' => 'refresh_manifest', 'manifestId' => 'orders', 'scope' => 'alpha'], [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertFalse($response['ok']);
		self::assertSame('Materialization service is not wired.', $response['error']);
	}

	public function testRefreshManifestFullBuildUsesQualifiedIdentityAndWritesManualState(): void {
		$manifest = $this->manifest('orders', 'alpha');
		$provider = $this->provider(['alpha' => [$manifest]]);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('buildFull')->with('alpha:orders')->willReturn(new MaterializationRunResult('alpha:orders', true, 'completed', rowCount: 37));
		$writes = [];
		$state = $this->state([], $writes);

		$response = $this->response(['mode' => 'refresh_manifest', 'manifestId' => 'orders', 'scope' => 'alpha', 'buildMode' => 'full'], [
			IMaterializationManifestProvider::class => $provider,
			IMaterializationService::class => $service,
			IStateStore::class => $state
		], $this->scopes());

		self::assertTrue($response['ok']);
		self::assertSame('full', $response['buildMode']);
		self::assertSame(37, $response['result']['rowCount']);
		self::assertSame('success', $writes['datahawk.materialization.alpha:orders.last_status']);
		self::assertSame(37, $writes['datahawk.materialization.alpha:orders.last_row_count']);
		self::assertSame(date('Y-m-d'), $writes['datahawk.materialization.alpha:orders.last_success_date']);
	}

	public function testRefreshManifestIncrementalBuildKeepsFailureState(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha')]]);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('buildIncremental')->with('alpha:orders')->willReturn(new MaterializationRunResult('alpha:orders', false, 'incremental unavailable'));
		$writes = [];
		$state = $this->state([], $writes);
		$response = $this->response(['mode' => 'refresh_manifest', 'manifestId' => 'orders', 'scope' => 'alpha', 'buildMode' => 'incremental'], [
			IMaterializationManifestProvider::class => $provider,
			IMaterializationService::class => $service,
			IStateStore::class => $state
		], $this->scopes());
		self::assertFalse($response['ok']);
		self::assertSame('failed', $writes['datahawk.materialization.alpha:orders.last_status']);
		self::assertArrayNotHasKey('datahawk.materialization.alpha:orders.last_success_at', $writes);
	}

	public function testUnknownBuildModeFallsBackToStandardRefresh(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha')]]);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('refresh')->with('alpha:orders')->willReturn(new MaterializationRunResult('alpha:orders', true));
		$response = $this->response(['mode' => 'refresh_manifest', 'manifestId' => 'orders', 'scope' => 'alpha', 'buildMode' => 'dangerous'], [
			IMaterializationManifestProvider::class => $provider, IMaterializationService::class => $service
		], $this->scopes());
		self::assertTrue($response['ok']);
		self::assertSame('refresh', $response['buildMode']);
	}

	public function testRefreshExceptionBecomesFailedResultWithoutSuccessMarker(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha')]]);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('refresh')->willThrowException(new \RuntimeException('build failed'));
		$writes = [];
		$response = $this->response(['mode' => 'refresh_manifest', 'manifestId' => 'orders', 'scope' => 'alpha'], [
			IMaterializationManifestProvider::class => $provider,
			IMaterializationService::class => $service,
			IStateStore::class => $this->state([], $writes)
		], $this->scopes());
		self::assertFalse($response['ok']);
		self::assertSame('build failed', $response['result']['message']);
		self::assertSame('failed', $writes['datahawk.materialization.alpha:orders.last_status']);
		self::assertArrayNotHasKey('datahawk.materialization.alpha:orders.last_success_at', $writes);
	}

	public function testRefreshDueReportsMissingDependenciesInOrder(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha')]]);
		$payload = ['mode' => 'refresh_due'];
		$response = $this->response($payload, [IMaterializationManifestProvider::class => $provider], $this->scopes());
		self::assertSame('Materialization registry is not wired.', $response['error']);

		$registry = $this->createStub(IMaterializationRegistry::class);
		$response = $this->response($payload, [IMaterializationManifestProvider::class => $provider, IMaterializationRegistry::class => $registry], $this->scopes());
		self::assertSame('State store is not wired.', $response['error']);

		$response = $this->response($payload, [IMaterializationManifestProvider::class => $provider, IMaterializationRegistry::class => $registry, IStateStore::class => $this->state()], $this->scopes());
		self::assertSame('Materialization service is not wired.', $response['error']);
	}

	public function testRefreshAllWithNoConfiguredManifestsReturnsEmptySuccess(): void {
		$provider = $this->provider(['alpha' => []]);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->never())->method('refresh');
		$response = $this->response(['mode' => 'refresh_all'], [
			IMaterializationManifestProvider::class => $provider,
			IMaterializationRegistry::class => $this->createStub(IMaterializationRegistry::class),
			IMaterializationService::class => $service,
			IStateStore::class => $this->state()
		], $this->scopes());
		self::assertTrue($response['ok']);
		self::assertSame('refresh_all', $response['mode']);
		self::assertSame([], $response['manifestIds']);
		self::assertSame([], $response['results']);
	}

	public function testRefreshDueDoesNotProcessManualManifest(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha', schedule: ['policy' => 'manual'])]]);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->never())->method('refresh');
		$response = $this->response(['mode' => 'refresh_due'], [
			IMaterializationManifestProvider::class => $provider,
			IMaterializationRegistry::class => $this->createStub(IMaterializationRegistry::class),
			IMaterializationService::class => $service,
			IStateStore::class => $this->state()
		], $this->scopes());
		self::assertTrue($response['ok']);
		self::assertSame([], $response['manifestIds']);
		self::assertSame([], $response['results']);
	}

	public function testRefreshAllOverridesManualScheduleAndRecordsSuccess(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha', schedule: ['policy' => 'manual'])]]);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('refresh')->with('alpha:orders')->willReturn(new MaterializationRunResult('alpha:orders', true, 'done', rowCount: 5));
		$writes = [];
		$response = $this->response(['mode' => 'refresh_all'], [
			IMaterializationManifestProvider::class => $provider,
			IMaterializationRegistry::class => $this->createStub(IMaterializationRegistry::class),
			IMaterializationService::class => $service,
			IStateStore::class => $this->state([], $writes)
		], $this->scopes());
		self::assertTrue($response['ok']);
		self::assertSame(['alpha:orders'], $response['manifestIds']);
		self::assertSame(5, $response['results'][0]['rowCount']);
		self::assertSame('success', $writes['datahawk.materialization.alpha:orders.last_status']);
	}

	public function testRefreshDueRunsOnlyConfiguredScope(): void {
		$provider = $this->provider([
			'alpha' => [$this->manifest('a', 'alpha', schedule: ['policy' => 'always'])],
			'beta' => [$this->manifest('b', 'beta', schedule: ['policy' => 'always'])]
		]);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('refresh')->with('beta:b')->willReturn(new MaterializationRunResult('beta:b', true));
		$scopes = ['first' => $this->scope('first', ['alpha']), 'second' => $this->scope('second', ['beta'])];
		$response = $this->response(['mode' => 'refresh_due', 'reportingScope' => 'second'], [
			IMaterializationManifestProvider::class => $provider,
			IMaterializationRegistry::class => $this->createStub(IMaterializationRegistry::class),
			IMaterializationService::class => $service,
			IStateStore::class => $this->state()
		], $scopes);
		self::assertTrue($response['ok']);
		self::assertSame(['beta:b'], $response['manifestIds']);
	}

	public function testRefreshDueReportsFailedRunAndDoesNotMarkSuccess(): void {
		$provider = $this->provider(['alpha' => [$this->manifest('orders', 'alpha', schedule: ['policy' => 'always'])]]);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('refresh')->willReturn(new MaterializationRunResult('alpha:orders', false, 'source unavailable'));
		$writes = [];
		$response = $this->response(['mode' => 'refresh_due'], [
			IMaterializationManifestProvider::class => $provider,
			IMaterializationRegistry::class => $this->createStub(IMaterializationRegistry::class),
			IMaterializationService::class => $service,
			IStateStore::class => $this->state([], $writes)
		], $this->scopes());
		self::assertFalse($response['ok']);
		self::assertSame('source unavailable', $response['results'][0]['message']);
		self::assertSame('failed', $writes['datahawk.materialization.alpha:orders.last_status']);
		self::assertArrayNotHasKey('datahawk.materialization.alpha:orders.last_success_at', $writes);
	}

	private function scopes(): array {
		return ['first' => $this->scope('first', ['alpha'])];
	}

	private function scope(string $id, array $materializationScopes): ReportingScopeDefinition {
		return new ReportingScopeDefinition($id, ucfirst($id), materializationScopes: $materializationScopes);
	}

	private function manifest(string $id, string $scope, bool $enabled = true, int $priority = 100, array $schedule = ['policy' => 'interval', 'seconds' => 300], array $dependsOn = []): MaterializationManifest {
		return new MaterializationManifest(id: $id, sourceSchema: 'source', targetSchema: $scope, logicalTable: $id, enabled: $enabled, priority: $priority, schedule: $schedule, dependsOn: $dependsOn);
	}

	private function provider(array $scopedManifests): IScopedMaterializationManifestProvider {
		$provider = $this->createStub(IScopedMaterializationManifestProvider::class);
		$provider->method('getScopes')->willReturn(array_keys($scopedManifests));
		$provider->method('getManifestsForScope')->willReturnCallback(static fn(string $scope): array => $scopedManifests[$scope] ?? []);
		$provider->method('getManifestForScope')->willReturnCallback(static function(string $scope, string $id) use ($scopedManifests): ?MaterializationManifest {
			foreach($scopedManifests[$scope] ?? [] as $manifest) {
				if($manifest->id === $id) return $manifest;
			}
			return null;
		});
		$provider->method('getManifests')->willReturn(array_merge(...array_values($scopedManifests)));
		return $provider;
	}

	private function state(array $values = [], ?array &$writes = null): IStateStore {
		$state = $this->createStub(IStateStore::class);
		$state->method('get')->willReturnCallback(static fn(string $key, mixed $default = null): mixed => $values[$key] ?? $default);
		$state->method('set')->willReturnCallback(static function(string $key, mixed $value) use (&$writes): void {
			$writes[$key] = $value;
		});
		return $state;
	}

	private function response(array $payload, array $services = [], array $scopes = [], ?DataHawkDatabaseFixture $database = null): array {
		$container = $this->createStub(IContainer::class);
		$container->method('has')->willReturnCallback(static fn(string $key): bool => array_key_exists($key, $services));
		$container->method('get')->willReturnCallback(static fn(string $key): mixed => $services[$key] ?? null);
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$view = $this->createStub(IMvcView::class);
		$view->method('getBricks')->willReturn([]);
		$assets = $this->createStub(IAssetResolver::class);
		$links = $this->createStub(ILinkTargetService::class);
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host');
		$authorization->method('decide')->willReturn(AuthorizationDecision::allow());
		$scopeRegistry = $this->createStub(IReportingScopeRegistry::class);
		$scopeRegistry->method('getScopes')->willReturn(array_values($scopes));
		$scopeRegistry->method('get')->willReturnCallback(static fn(string $id): ?ReportingScopeDefinition => $scopes[$id] ?? null);
		$database ??= new DataHawkDatabaseFixture();

		$display = new DataHawkMaterializationOverviewDisplay($container, $request, $view, $assets, $links, $database, $scopeRegistry, $authorization);
		return json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
	}
}
