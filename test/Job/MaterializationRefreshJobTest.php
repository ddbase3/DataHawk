<?php declare(strict_types=1);

namespace DataHawk\Test\Job;

use Base3\Api\IContainer;
use Base3\Configuration\Api\IConfiguration;
use Base3\State\Api\IStateStore;
use DataHawk\Job\MaterializationRefreshJob;
use DataHawk\Materialization\DatabaseMaterializationRegistry;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IMaterializationManifestProvider;
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\IMaterializationService;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\MaterializationRunResult;

final class MaterializationRefreshJobTest extends TestCase {

	public function testHasStableDiscoverableNameAndDefaultPriority(): void {
		$job = $this->job([]);
		self::assertSame('datahawkmaterializationrefreshjob', $job::getName());
		self::assertFalse($job->isActive());
		self::assertSame(1, $job->getPriority());
	}

	public function testConfiguredActivationAndPriorityAreReadFromJobConfiguration(): void {
		$job = $this->job([], ['datahawkmaterializationrefreshjob.active' => '1', 'datahawkmaterializationrefreshjob.priority' => 7]);
		self::assertTrue($job->isActive());
		self::assertSame(7, $job->getPriority());
	}

	public function testMissingManifestProviderSkipsWithoutSideEffects(): void {
		self::assertSame('Skip (materialization manifest provider is not wired)', $this->job([])->go());
	}

	public function testMissingMaterializationServiceSkips(): void {
		self::assertSame('Skip (materialization service is not wired)', $this->job([
			IMaterializationManifestProvider::class => $this->provider([])
		])->go());
	}

	public function testMissingRegistrySkips(): void {
		self::assertSame('Skip (materialization registry is not wired)', $this->job([
			IMaterializationManifestProvider::class => $this->provider([]),
			IMaterializationService::class => $this->createStub(IMaterializationService::class)
		])->go());
	}

	public function testMissingStateStoreSkipsInsteadOfRunningWithoutSchedule(): void {
		self::assertSame('Skip (state store is not wired; manifest scheduling requires IStateStore)', $this->job([
			IMaterializationManifestProvider::class => $this->provider([]),
			IMaterializationService::class => $this->createStub(IMaterializationService::class),
			IMaterializationRegistry::class => $this->createStub(IMaterializationRegistry::class)
		])->go());
	}

	public function testNoMaterializationsDueSkips(): void {
		$job = $this->wiredJob([]);
		self::assertSame('Skip (no materializations due)', $job->go());
	}

	public function testManualManifestIsNotRefreshedWithoutForce(): void {
		$manifest = $this->manifest('orders', ['policy' => 'manual']);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->never())->method('refresh');
		self::assertSame('Skip (no materializations due)', $this->wiredJob([$manifest], $service)->go());
	}

	public function testRefreshesDueManifestAndRecordsSuccess(): void {
		$manifest = $this->manifest('orders');
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('refresh')->with('reports:orders')->willReturn(new MaterializationRunResult('reports:orders', true, 'ok', rowCount: 12));
		$output = $this->wiredJob([$manifest], $service)->go();

		self::assertStringContainsString('1/1 succeeded', $output);
		self::assertStringContainsString('due: reports:orders', $output);
	}

	public function testFullModeInvokesFullBuildInsteadOfRefresh(): void {
		$manifest = $this->manifest('orders');
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('buildFull')->with('reports:orders')->willReturn(new MaterializationRunResult('reports:orders', true));
		$service->expects($this->never())->method('refresh');
		$output = $this->wiredJob([$manifest], $service, ['datahawkmaterializationrefreshjob.mode' => 'full'])->go();
		self::assertStringContainsString('mode: full', $output);
	}

	public function testIncrementalModeInvokesIncrementalBuild(): void {
		$manifest = $this->manifest('orders');
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('buildIncremental')->with('reports:orders')->willReturn(new MaterializationRunResult('reports:orders', false, 'incremental unavailable'));
		$output = $this->wiredJob([$manifest], $service, ['datahawkmaterializationrefreshjob.mode' => 'incremental'])->go();
		self::assertStringContainsString('finished with failures', $output);
		self::assertStringContainsString('incremental unavailable', $output);
	}

	public function testInvalidBuildModeFallsBackToRefresh(): void {
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('refresh')->willReturn(new MaterializationRunResult('reports:orders', true));
		$output = $this->wiredJob([$this->manifest('orders')], $service, ['datahawkmaterializationrefreshjob.mode' => 'invalid'])->go();
		self::assertStringContainsString('mode: refresh', $output);
	}

	public function testServiceExceptionBecomesFailureResult(): void {
		$service = $this->createStub(IMaterializationService::class);
		$service->method('refresh')->willThrowException(new \RuntimeException('source unavailable'));
		$output = $this->wiredJob([$this->manifest('orders')], $service)->go();
		self::assertStringContainsString('finished with failures', $output);
		self::assertStringContainsString('orders: source unavailable', $output);
	}

	public function testDuplicateManifestIdentityIsReportedBeforeExecution(): void {
		$manifest = $this->manifest('orders');
		$output = $this->wiredJob([$manifest, $manifest])->go();
		self::assertStringContainsString('failed before execution', $output);
		self::assertStringContainsString('Duplicate materialization identity', $output);
	}

	public function testForceStateActivatesManualManifestAndIsCleared(): void {
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('refresh')->willReturn(new MaterializationRunResult('reports:orders', true));
		$deleted = [];
		$state = $this->createStub(IStateStore::class);
		$state->method('get')->willReturnCallback(static fn(string $key, mixed $default = null): mixed =>
			$key === 'datahawk.job.materializationrefresh.force' ? 'yes' : $default
		);
		$state->method('has')->willReturnCallback(static fn(string $key): bool => $key === 'datahawk.job.materializationrefresh.force');
		$state->method('delete')->willReturnCallback(static function(string $key) use (&$deleted): bool {
			$deleted[] = $key;
			return true;
		});

		$output = $this->wiredJob([$this->manifest('orders', ['policy' => 'manual'])], $service, [], $state)->go();
		self::assertStringContainsString('force: 1', $output);
		self::assertSame(['datahawk.job.materializationrefresh.force'], $deleted);
	}

	public function testCanFilterSpecificManifestsViaConfiguration(): void {
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('refresh')->with('reports:second')->willReturn(new MaterializationRunResult('reports:second', true));
		$job = $this->wiredJob([$this->manifest('first'), $this->manifest('second')], $service, [
			'datahawkmaterializationrefreshjob.manifests' => 'second,missing'
		]);
		self::assertStringContainsString('due: reports:second', $job->go());
	}


	public function testCleanupRunsBeforeScheduleAndRecordsCompletion(): void {
		$registry = $this->createMock(DatabaseMaterializationRegistry::class);
		$registry->expects($this->once())->method('cleanupHistory')->with(4)->willReturn([
			'registry' => ['deleted' => 2], 'runs' => ['deleted' => 3]
		]);
		$state = $this->createMock(IStateStore::class);
		$state->expects($this->once())->method('set')->with(
			'datahawk.job.materializationrefresh.cleanup.lastrun',
			$this->callback(static fn(mixed $value): bool => is_int($value) && abs(time() - $value) <= 5)
		);
		$state->expects($this->once())->method('flush');
		self::assertSame(
			'Materialization history cleanup done (registry deleted: 2, runs deleted: 3)',
			$this->maintenanceJob($registry, $state, ['datahawkmaterializationrefreshjob.cleanup.keep_runs_per_manifest' => 4])->go()
		);
	}

	public function testDisabledMaintenanceNeverTouchesRegistry(): void {
		foreach([false, 0, 'disabled', 'OFF'] as $disabled) {
			$registry = $this->createMock(DatabaseMaterializationRegistry::class);
			$registry->expects($this->never())->method('cleanupHistory');
			$state = $this->createStub(IStateStore::class);
			self::assertSame('Skip (no materializations due)', $this->maintenanceJob($registry, $state, [
				'datahawkmaterializationrefreshjob.cleanup.enabled' => $disabled
			])->go());
		}
	}

	public function testCleanupSkipsUntilConfiguredIntervalHasElapsed(): void {
		$registry = $this->createMock(DatabaseMaterializationRegistry::class);
		$registry->expects($this->never())->method('cleanupHistory');
		$state = $this->createStub(IStateStore::class);
		$state->method('get')->willReturnCallback(static fn(string $key, mixed $default = null): mixed =>
			$key === 'datahawk.job.materializationrefresh.cleanup.lastrun' ? time() - 30 : $default
		);
		self::assertSame('Skip (no materializations due)', $this->maintenanceJob($registry, $state, [
			'datahawkmaterializationrefreshjob.cleanup.interval_seconds' => 3600
		])->go());
	}

	public function testInvalidCleanupIntervalUsesDefaultInsteadOfRunningAgain(): void {
		$registry = $this->createMock(DatabaseMaterializationRegistry::class);
		$registry->expects($this->never())->method('cleanupHistory');
		$state = $this->createStub(IStateStore::class);
		$state->method('get')->willReturnCallback(static fn(string $key, mixed $default = null): mixed =>
			$key === 'datahawk.job.materializationrefresh.cleanup.lastrun' ? time() - 30 : $default
		);
		self::assertSame('Skip (no materializations due)', $this->maintenanceJob($registry, $state, [
			'datahawkmaterializationrefreshjob.cleanup.interval_seconds' => -1
		])->go());
	}

	public function testCleanupFailureIsReportedWithoutPersistingCompletion(): void {
		$registry = $this->createMock(DatabaseMaterializationRegistry::class);
		$registry->expects($this->once())->method('cleanupHistory')->willThrowException(new \RuntimeException('database unavailable'));
		$state = $this->createMock(IStateStore::class);
		$state->expects($this->never())->method('set');
		$state->expects($this->never())->method('flush');
		self::assertSame(
			'Materialization history cleanup failed: database unavailable',
			$this->maintenanceJob($registry, $state)->go()
		);
	}

	public function testInvalidRetentionValueUsesDefaultOfOneHundredRuns(): void {
		$registry = $this->createMock(DatabaseMaterializationRegistry::class);
		$registry->expects($this->once())->method('cleanupHistory')->with(100)->willReturn([]);
		$state = $this->createStub(IStateStore::class);
		self::assertStringContainsString('history cleanup done', $this->maintenanceJob($registry, $state, [
			'datahawkmaterializationrefreshjob.cleanup.keep_runs_per_manifest' => -8
		])->go());
	}

	public function testStateRetentionSettingOverridesJobConfiguration(): void {
		$registry = $this->createMock(DatabaseMaterializationRegistry::class);
		$registry->expects($this->once())->method('cleanupHistory')->with(7)->willReturn([]);
		$state = $this->createStub(IStateStore::class);
		$state->method('get')->willReturnCallback(static fn(string $key, mixed $default = null): mixed =>
			$key === 'datahawk.job.materializationrefresh.cleanup.keep_runs_per_manifest' ? 7 : $default
		);
		self::assertStringContainsString('history cleanup done', $this->maintenanceJob($registry, $state, [
			'datahawkmaterializationrefreshjob.cleanup.keep_runs_per_manifest' => 3
		])->go());
	}

	public function testStateCanDisableMaintenanceDespiteEnabledConfiguration(): void {
		$registry = $this->createMock(DatabaseMaterializationRegistry::class);
		$registry->expects($this->never())->method('cleanupHistory');
		$state = $this->createStub(IStateStore::class);
		$state->method('get')->willReturnCallback(static fn(string $key, mixed $default = null): mixed =>
			$key === 'datahawk.job.materializationrefresh.cleanup.enabled' ? 'no' : $default
		);
		self::assertSame('Skip (no materializations due)', $this->maintenanceJob($registry, $state, [
			'datahawkmaterializationrefreshjob.cleanup.enabled' => 1
		])->go());
	}

	public function testUnknownManifestDuringExecutionIsRecordedAsFailure(): void {
		$provider = $this->createStub(IMaterializationManifestProvider::class);
		$provider->method('getManifests')->willReturn([$this->manifest('orders')]);
		$provider->method('getManifest')->willReturn(null);
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->never())->method('refresh');
		$state = $this->createMock(IStateStore::class);
		$state->expects($this->once())->method('flush');
		$output = $this->job([
			IMaterializationManifestProvider::class => $provider,
			IMaterializationRegistry::class => $this->createStub(IMaterializationRegistry::class),
			IMaterializationService::class => $service,
			IStateStore::class => $state
		])->go();
		self::assertStringContainsString('finished with failures', $output);
		self::assertStringContainsString('Unknown materialization manifest: reports:orders', $output);
	}

	public function testManifestSelectionAcceptsArrayAndLegacySingleId(): void {
		foreach([
			['datahawkmaterializationrefreshjob.manifests' => ['orders', 'orders', 'all', '', 'unknown']],
			['datahawkmaterializationrefreshjob.manifest' => 'orders']
		] as $settings) {
			$service = $this->createMock(IMaterializationService::class);
			$service->expects($this->once())->method('refresh')->with('reports:orders')->willReturn(new MaterializationRunResult('reports:orders', true));
			self::assertStringContainsString('1/1 succeeded', $this->wiredJob([$this->manifest('orders'), $this->manifest('other')], $service, $settings)->go());
		}
	}

	public function testInvalidForceValueDoesNotRefreshManualManifest(): void {
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->never())->method('refresh');
		self::assertSame('Skip (no materializations due)', $this->wiredJob([
			$this->manifest('manual', ['policy' => 'manual'])
		], $service, ['datahawkmaterializationrefreshjob.force' => 'maybe'])->go());
	}

	public function testBooleanForceRefreshesManualManifest(): void {
		$service = $this->createMock(IMaterializationService::class);
		$service->expects($this->once())->method('refresh')->willReturn(new MaterializationRunResult('reports:manual', true));
		$output = $this->wiredJob([$this->manifest('manual', ['policy' => 'manual'])], $service, [
			'datahawkmaterializationrefreshjob.force' => true
		])->go();
		self::assertStringContainsString('force: 1', $output);
	}

	public function testContainerRejectsWiredObjectsOfWrongTypes(): void {
		$provider = $this->provider([]);
		$service = $this->createStub(IMaterializationService::class);
		$registry = $this->createStub(IMaterializationRegistry::class);
		foreach([
			[IMaterializationManifestProvider::class => 'not a provider'],
			[IMaterializationManifestProvider::class => $provider, IMaterializationService::class => 'not a service'],
			[IMaterializationManifestProvider::class => $provider, IMaterializationService::class => $service, IMaterializationRegistry::class => 'not a registry'],
			[IMaterializationManifestProvider::class => $provider, IMaterializationService::class => $service, IMaterializationRegistry::class => $registry, IStateStore::class => 'not a state store']
		] as $services) {
			$container = $this->createStub(IContainer::class);
			$container->method('has')->willReturn(true);
			$container->method('get')->willReturnCallback(static fn(string $key): mixed => $services[$key] ?? null);
			$config = $this->createStub(IConfiguration::class);
			$job = new MaterializationRefreshJob($container, $config);
			self::assertStringStartsWith('Skip (', $job->go());
		}
	}

	public function testConfigurationIsLoadedOnceForRepeatedPriorityAndActivationChecks(): void {
		$config = $this->createMock(IConfiguration::class);
		$config->expects($this->once())->method('get')->with('job')->willReturn([
			'datahawkmaterializationrefreshjob.active' => true,
			'datahawkmaterializationrefreshjob.priority' => '8'
		]);
		$job = new MaterializationRefreshJob($this->createStub(IContainer::class), $config);
		self::assertTrue($job->isActive());
		self::assertSame(8, $job->getPriority());
		self::assertTrue($job->isActive());
	}

	public function testMaintenanceSummaryIsAppendedToSuccessfulRefresh(): void {
		$registry = $this->createStub(DatabaseMaterializationRegistry::class);
		$registry->method('cleanupHistory')->willReturn(['registry' => ['deleted' => 1], 'runs' => ['deleted' => 0]]);
		$service = $this->createStub(IMaterializationService::class);
		$service->method('refresh')->willReturn(new MaterializationRunResult('reports:orders', true));
		$state = $this->createStub(IStateStore::class);
		$output = $this->job([
			IMaterializationManifestProvider::class => $this->provider([$this->manifest('orders')]),
			IMaterializationService::class => $service,
			IMaterializationRegistry::class => $registry,
			IStateStore::class => $state
		])->go();
		self::assertStringContainsString('1/1 succeeded', $output);
		self::assertStringContainsString('history cleanup done', $output);
	}

	private function maintenanceJob(DatabaseMaterializationRegistry $registry, IStateStore $state, array $settings = []): MaterializationRefreshJob {
		return $this->job([
			IMaterializationManifestProvider::class => $this->provider([]),
			IMaterializationService::class => $this->createStub(IMaterializationService::class),
			IMaterializationRegistry::class => $registry,
			IStateStore::class => $state
		], $settings);
	}

	private function manifest(string $id, array $schedule = ['policy' => 'always']): MaterializationManifest {
		return new MaterializationManifest($id, targetSchema: 'reports', logicalTable: $id, schedule: $schedule);
	}

	private function provider(array $manifests): IMaterializationManifestProvider {
		$provider = $this->createStub(IMaterializationManifestProvider::class);
		$provider->method('getManifests')->willReturn($manifests);
		$provider->method('getManifest')->willReturnCallback(static function(string $id) use($manifests): ?MaterializationManifest {
			foreach($manifests as $manifest) if($id === $manifest->id || str_ends_with($id, ':' . $manifest->id)) return $manifest;
			return null;
		});
		return $provider;
	}

	private function wiredJob(array $manifests, ?IMaterializationService $service = null, array $settings = [], ?IStateStore $state = null): MaterializationRefreshJob {
		$state ??= $this->createStub(IStateStore::class);
		$service ??= $this->createStub(IMaterializationService::class);
		return $this->job([
			IMaterializationManifestProvider::class => $this->provider($manifests),
			IMaterializationRegistry::class => $this->createStub(IMaterializationRegistry::class),
			IMaterializationService::class => $service,
			IStateStore::class => $state
		], $settings);
	}

	private function job(array $services, array $settings = []): MaterializationRefreshJob {
		$container = $this->createStub(IContainer::class);
		$container->method('has')->willReturnCallback(static fn(string $name): bool => isset($services[$name]));
		$container->method('get')->willReturnCallback(static fn(string $name): mixed => $services[$name] ?? null);
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn($settings);
		return new MaterializationRefreshJob($container, $config);
	}
}
