<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use Base3\State\Api\IStateStore;
use DataHawk\Materialization\MaterializationRefreshPlanner;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IMaterializationManifestProvider;
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\IScopedMaterializationManifestProvider;
use ResourceFoundation\Dto\MaterializationGeneration;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\MaterializationRunResult;

final class MaterializationRefreshPlannerTest extends TestCase {

	public function testOrdersEnabledManifestsByPriorityAndIdentity(): void {
		$planner = $this->planner([
			$this->manifest('z', priority: 30),
			$this->manifest('b', priority: 10),
			$this->manifest('a', priority: 10),
			$this->manifest('disabled', enabled: false)
		]);

		self::assertSame(['reports:a', 'reports:b', 'reports:z'], $planner->getManifestIdsToRefresh());
	}

	public function testManualSchedulesRequireForce(): void {
		$manual = $this->manifest('manual', schedule: ['policy' => 'manual']);
		$planner = $this->planner([$manual]);

		self::assertSame([], $planner->getManifestIdsToRefresh());
		self::assertSame(['reports:manual'], $planner->getManifestIdsToRefresh([], true));
	}

	public function testIntervalNotDueAfterRecentSuccess(): void {
		$manifest = $this->manifest('orders', schedule: ['policy' => 'interval', 'seconds' => 3600]);
		$planner = $this->planner([$manifest], ['datahawk.materialization.reports:orders.last_success_at' => time()]);
		self::assertSame([], $planner->getManifestIdsToRefresh());
	}

	public function testIntervalIsDueWithoutPreviousSuccess(): void {
		$manifest = $this->manifest('orders', schedule: ['policy' => 'interval', 'seconds' => 3600]);
		self::assertSame(['reports:orders'], $this->planner([$manifest])->getManifestIdsToRefresh());
	}

	public function testDailyScheduleDoesNotRunTwiceOnSameDate(): void {
		$manifest = $this->manifest('orders', schedule: ['policy' => 'daily_after', 'time' => '00:00']);
		$planner = $this->planner([$manifest], ['datahawk.materialization.reports:orders.last_success_date' => date('Y-m-d')]);
		self::assertSame([], $planner->getManifestIdsToRefresh());
	}

	public function testConfiguredIdsAcceptQualifiedAndUnqualifiedNamesWithoutDuplicates(): void {
		$planner = $this->planner([$this->manifest('orders'), $this->manifest('people')]);
		self::assertSame(['reports:orders'], $planner->getManifestIdsToRefresh(['orders', 'reports:orders', 'missing', ' ']));
	}

	public function testConfiguredUnqualifiedIdIsRejectedWhenAmbiguous(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Configured materialization id is ambiguous');
		$this->planner([$this->manifest('orders', targetSchema: 'a'), $this->manifest('orders', targetSchema: 'b')])
			->getManifestIdsToRefresh(['orders']);
	}

	public function testRejectsDuplicateManifestIdentity(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Duplicate materialization identity');
		$this->planner([$this->manifest('orders'), $this->manifest('orders')])->getManifestIdsToRefresh();
	}

	public function testMissingDependencyDoesNotBlockIndependentManifest(): void {
		$manifest = $this->manifest('orders', dependsOn: ['missing'], dependencyRefresh: 'cascade');
		self::assertSame(['reports:orders'], $this->planner([$manifest])->getManifestIdsToRefresh());
	}

	public function testCascadeRefreshesDependencyBeforeParent(): void {
		$parent = $this->manifest('parent', dependsOn: ['child'], dependencyRefresh: 'cascade', priority: 1);
		$child = $this->manifest('child', schedule: ['policy' => 'manual'], priority: 100);
		self::assertSame(['reports:child', 'reports:parent'], $this->planner([$parent, $child])->getManifestIdsToRefresh());
	}

	public function testCurrentDependencyModeDoesNotRefreshDependency(): void {
		$parent = $this->manifest('parent', dependsOn: ['child'], dependencyRefresh: 'current');
		$child = $this->manifest('child', schedule: ['policy' => 'manual']);
		self::assertSame(['reports:parent'], $this->planner([$parent, $child])->getManifestIdsToRefresh());
	}

	public function testForceRefreshesDependencyEvenForCurrentPolicy(): void {
		$parent = $this->manifest('parent', dependsOn: ['child'], dependencyRefresh: 'current');
		$child = $this->manifest('child', schedule: ['policy' => 'manual']);
		self::assertSame(['reports:child', 'reports:parent'], $this->planner([$parent, $child])->getManifestIdsToRefresh(['parent'], true));
	}

	public function testMissingCurrentGenerationRefreshesRequiredDependency(): void {
		$parent = $this->manifest('parent', dependsOn: ['child'], dependencyRefresh: 'missing');
		$child = $this->manifest('child', schedule: ['policy' => 'manual']);
		self::assertSame(['reports:child', 'reports:parent'], $this->planner([$parent, $child])->getManifestIdsToRefresh());
	}

	public function testExistingCurrentGenerationPreventsMissingOnlyDependencyRefresh(): void {
		$parent = $this->manifest('parent', dependsOn: ['child'], dependencyRefresh: 'missing');
		$child = $this->manifest('child', schedule: ['policy' => 'manual']);
		$registry = $this->createStub(IMaterializationRegistry::class);
		$registry->method('getCurrentGeneration')->willReturn(new MaterializationGeneration('reports', 'child', 'base3_mat_child_1', '1'));
		self::assertSame(['reports:parent'], $this->planner([$parent, $child], [], $registry)->getManifestIdsToRefresh());
	}

	public function testRejectsCircularSelectedDependencies(): void {
		$a = $this->manifest('a', dependsOn: ['b'], dependencyRefresh: 'cascade');
		$b = $this->manifest('b', dependsOn: ['a'], dependencyRefresh: 'cascade');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Circular materialization dependency');
		$this->planner([$a, $b])->getManifestIdsToRefresh();
	}

	public function testRejectsAmbiguousDependencyAcrossScopes(): void {
		$parent = $this->manifest('parent', targetSchema: 'reports', dependsOn: ['shared'], dependencyRefresh: 'cascade');
		$a = $this->manifest('shared', targetSchema: 'a');
		$b = $this->manifest('shared', targetSchema: 'b');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Materialization dependency is ambiguous');
		$this->planner([$parent, $a, $b])->getManifestIdsToRefresh(['parent']);
	}

	public function testGetsManifestThroughUnscopedProvider(): void {
		$manifest = $this->manifest('orders');
		$provider = $this->createMock(IMaterializationManifestProvider::class);
		$provider->expects($this->once())->method('getManifest')->with('reports:orders')->willReturn($manifest);
		$planner = new MaterializationRefreshPlanner($provider, $this->createStub(IMaterializationRegistry::class), $this->createStub(IStateStore::class));
		self::assertSame($manifest, $planner->getManifest('reports:orders'));
	}

	public function testGetsQualifiedManifestFromScopedProvider(): void {
		$manifest = $this->manifest('orders');
		$provider = $this->createMock(IScopedMaterializationManifestProvider::class);
		$provider->expects($this->once())->method('getManifestForScope')->with('reports', 'orders')->willReturn($manifest);
		$planner = new MaterializationRefreshPlanner($provider, $this->createStub(IMaterializationRegistry::class), $this->createStub(IStateStore::class));
		self::assertSame($manifest, $planner->getManifest('reports:orders'));
	}

	public function testMarksAttemptWithNamespacedKeyAndTimestamp(): void {
		$state = $this->createMock(IStateStore::class);
		$state->expects($this->once())->method('set')->with('datahawk.materialization.reports:orders.last_attempt_at', $this->callback(fn($time): bool => is_int($time) && abs(time() - $time) <= 3));
		$this->planner([$this->manifest('orders')], [], null, $state)->markAttempt($this->manifest('orders'));
	}

	public function testMarksSuccessfulResultWithRowCountAndDate(): void {
		$written = [];
		$state = $this->createStub(IStateStore::class);
		$state->method('set')->willReturnCallback(static function(string $key, mixed $value) use (&$written): void { $written[$key] = $value; });
		$this->planner([], [], null, $state)->markResult($this->manifest('orders'), new MaterializationRunResult('orders', true, 'ok', rowCount: 42));

		self::assertSame('success', $written['datahawk.materialization.reports:orders.last_status']);
		self::assertSame('ok', $written['datahawk.materialization.reports:orders.last_message']);
		self::assertSame(42, $written['datahawk.materialization.reports:orders.last_row_count']);
		self::assertSame(date('Y-m-d'), $written['datahawk.materialization.reports:orders.last_success_date']);
	}

	public function testFailedResultDoesNotSetSuccessTimestamp(): void {
		$keys = [];
		$state = $this->createStub(IStateStore::class);
		$state->method('set')->willReturnCallback(static function(string $key) use (&$keys): void { $keys[] = $key; });
		$this->planner([], [], null, $state)->markResult($this->manifest('orders'), new MaterializationRunResult('orders', false, 'failed'));
		self::assertCount(2, $keys);
		self::assertNotContains('datahawk.materialization.reports:orders.last_success_at', $keys);
	}

	public function testFlushStateDelegatesExactlyOnce(): void {
		$state = $this->createMock(IStateStore::class);
		$state->expects($this->once())->method('flush');
		$this->planner([], [], null, $state)->flushState();
	}


	public function testScopedProvidersEnumerateQualifiedIdentitiesWithoutCrossScopeConflicts(): void {
		$a = $this->manifest('orders', targetSchema: 'alpha');
		$b = $this->manifest('orders', targetSchema: 'beta');
		$provider = $this->createStub(IScopedMaterializationManifestProvider::class);
		$provider->method('getScopes')->willReturn(['beta', 'alpha']);
		$provider->method('getManifestsForScope')->willReturnCallback(static fn(string $scope): array => $scope === 'alpha' ? [$a] : [$b]);
		$planner = new MaterializationRefreshPlanner($provider, $this->createStub(IMaterializationRegistry::class), $this->createStub(IStateStore::class));
		self::assertSame(['alpha:orders', 'beta:orders'], $planner->getManifestIdsToRefresh());
		self::assertSame(['beta:orders'], $planner->getManifestIdsToRefresh(['beta:orders']));
	}

	public function testDuplicateScopedIdentityIsRejected(): void {
		$manifest = $this->manifest('orders', targetSchema: 'reports');
		$provider = $this->createStub(IScopedMaterializationManifestProvider::class);
		$provider->method('getScopes')->willReturn(['reports']);
		$provider->method('getManifestsForScope')->willReturn([$manifest, $manifest]);
		$planner = new MaterializationRefreshPlanner($provider, $this->createStub(IMaterializationRegistry::class), $this->createStub(IStateStore::class));
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Duplicate materialization identity');
		$planner->getManifestIdsToRefresh();
	}

	public function testForceStillDoesNotSelectDisabledManifest(): void {
		self::assertSame([], $this->planner([$this->manifest('disabled', enabled: false)])->getManifestIdsToRefresh([], true));
	}

	public function testConfiguredUnknownScopeDoesNotSelectManifestWithSameLocalId(): void {
		$planner = $this->planner([$this->manifest('orders')]);
		self::assertSame([], $planner->getManifestIdsToRefresh(['elsewhere:orders']));
	}

	public function testPreviouslySuccessfulDailyManifestIsNotRepeatedEvenWithInvalidConfiguredTime(): void {
		$manifest = $this->manifest('orders', schedule: ['policy' => 'daily_after', 'time' => 'invalid']);
		$planner = $this->planner([$manifest], ['datahawk.materialization.reports:orders.last_success_date' => date('Y-m-d')]);
		self::assertSame([], $planner->getManifestIdsToRefresh());
	}

	public function testIntervalBecomesDueAgainAfterElapsedWindow(): void {
		$manifest = $this->manifest('orders', schedule: ['policy' => 'interval', 'seconds' => 30]);
		$planner = $this->planner([$manifest], ['datahawk.materialization.reports:orders.last_success_at' => time() - 60]);
		self::assertSame(['reports:orders'], $planner->getManifestIdsToRefresh());
	}

	private function manifest(string $id, string $targetSchema = 'reports', bool $enabled = true, int $priority = 100, array $schedule = ['policy' => 'always'], array $dependsOn = [], string $dependencyRefresh = 'missing'): MaterializationManifest {
		return new MaterializationManifest(id: $id, targetSchema: $targetSchema, logicalTable: $id, enabled: $enabled, priority: $priority, schedule: $schedule, dependsOn: $dependsOn, dependencyRefresh: $dependencyRefresh);
	}

	private function planner(array $manifests, array $stateValues = [], ?IMaterializationRegistry $registry = null, ?IStateStore $state = null): MaterializationRefreshPlanner {
		$provider = $this->createStub(IMaterializationManifestProvider::class);
		$provider->method('getManifests')->willReturn($manifests);
		$registry ??= $this->createStub(IMaterializationRegistry::class);
		$state ??= $this->createStub(IStateStore::class);
		$state->method('get')->willReturnCallback(static fn(string $key, mixed $default = null): mixed => $stateValues[$key] ?? $default);
		return new MaterializationRefreshPlanner($provider, $registry, $state);
	}
}
