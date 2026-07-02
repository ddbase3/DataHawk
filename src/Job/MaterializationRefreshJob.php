<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of DataHawk for BASE3 Framework.
 *
 * DataHawk extends the BASE3 framework with a schema-driven query
 * engine for reporting and data access. Queries are defined as
 * structured JSON arrays, compiled into SQL, and executed through
 * the BASE3 IDatabase abstraction.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/datahawk
 * https://github.com/ddbase3/DataHawk
 **********************************************************************/

namespace DataHawk\Job;

use Base3\Api\IContainer;
use Base3\Configuration\Api\IConfiguration;
use Base3\State\Api\IStateStore;
use Base3\Worker\Api\IJob;
use DataHawk\Materialization\DatabaseMaterializationRegistry;
use DataHawk\Materialization\MaterializationRefreshPlanner;
use ResourceFoundation\Api\IMaterializationManifestProvider;
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\IMaterializationService;
use ResourceFoundation\Dto\MaterializationRunResult;
use Throwable;

/**
 * MaterializationRefreshJob
 *
 * Lightweight always-run materialization worker.
 *
 * The job itself has no worker timing policy. It is expected to be called often
 * by the normal worker loop. Every materialization manifest decides through its
 * own refresh.schedule whether it is currently due.
 */
final class MaterializationRefreshJob implements IJob {

	private const STATE_PREFIX = 'datahawk.job.materializationrefresh.';

	private const DEFAULT_PRIORITY = 1;

	private ?array $jobConf = null;

	public function __construct(
		private readonly IContainer $container,
		private readonly IConfiguration $configuration
	) {}

	public static function getName(): string {
		return 'datahawkmaterializationrefreshjob';
	}

	public function isActive() {
		$conf = $this->getJobConf();
		return ((int)($conf['datahawkmaterializationrefreshjob.active'] ?? 0)) === 1;
	}

	public function getPriority() {
		$conf = $this->getJobConf();
		return (int)($conf['datahawkmaterializationrefreshjob.priority'] ?? self::DEFAULT_PRIORITY);
	}

	public function go() {
		$manifestProvider = $this->getManifestProvider();
		if ($manifestProvider === null) {
			return 'Skip (materialization manifest provider is not wired)';
		}

		$service = $this->getMaterializationService();
		if ($service === null) {
			return 'Skip (materialization service is not wired)';
		}

		$registry = $this->getMaterializationRegistry();
		if ($registry === null) {
			return 'Skip (materialization registry is not wired)';
		}

		$stateStore = $this->getStateStore();
		if ($stateStore === null) {
			return 'Skip (state store is not wired; manifest scheduling requires IStateStore)';
		}

		$planner = new MaterializationRefreshPlanner($manifestProvider, $registry, $stateStore);
		$mode = $this->getRefreshMode($stateStore);
		$force = $this->isForced($stateStore);
		$maintenanceMessage = $this->runMaintenanceIfDue($registry, $stateStore);

		try {
			$manifestIds = $planner->getManifestIdsToRefresh($this->getConfiguredManifestIds($stateStore), $force);
		} catch (Throwable $e) {
			return 'Materialization refresh failed before execution: ' . $e->getMessage();
		}

		if ($manifestIds === []) {
			return $maintenanceMessage !== '' ? $maintenanceMessage : 'Skip (no materializations due)';
		}

		$results = [];

		foreach ($manifestIds as $manifestId) {
			$manifest = $planner->getManifest($manifestId);
			if ($manifest === null) {
				$results[] = new MaterializationRunResult(
					manifestId: $manifestId,
					success: false,
					message: 'Unknown materialization manifest: ' . $manifestId
				);
				continue;
			}

			$planner->markAttempt($manifest);
			$result = $this->refreshManifest($service, $manifestId, $mode);
			$planner->markResult($manifest, $result);
			$results[] = $result;
		}

		$planner->flushState();
		$this->clearForceState($stateStore);

		$failed = array_values(array_filter($results, fn(MaterializationRunResult $result) => !$result->success));
		$successCount = count($results) - count($failed);
		$dueText = implode(',', $manifestIds);
		$forceText = $force ? ', force: 1' : '';

		$maintenanceText = $maintenanceMessage !== '' ? '; ' . $maintenanceMessage : '';

		if ($failed === []) {
			return 'Materialization refresh done (' . $successCount . '/' . count($results) . ' succeeded, mode: ' . $mode . ', due: ' . $dueText . $forceText . ')' . $maintenanceText;
		}

		return 'Materialization refresh finished with failures ('
			. $successCount . '/' . count($results) . ' succeeded, mode: ' . $mode . ', due: ' . $dueText . $forceText . '): '
			. $this->formatFailures($failed) . $maintenanceText;
	}

	private function getJobConf(): array {
		if ($this->jobConf === null) {
			$this->jobConf = (array)$this->configuration->get('job');
		}

		return $this->jobConf;
	}

	private function getMaterializationService(): ?IMaterializationService {
		if (!$this->container->has(IMaterializationService::class)) {
			return null;
		}

		$service = $this->container->get(IMaterializationService::class);
		return $service instanceof IMaterializationService ? $service : null;
	}

	private function getManifestProvider(): ?IMaterializationManifestProvider {
		if (!$this->container->has(IMaterializationManifestProvider::class)) {
			return null;
		}

		$provider = $this->container->get(IMaterializationManifestProvider::class);
		return $provider instanceof IMaterializationManifestProvider ? $provider : null;
	}

	private function getMaterializationRegistry(): ?IMaterializationRegistry {
		if (!$this->container->has(IMaterializationRegistry::class)) {
			return null;
		}

		$registry = $this->container->get(IMaterializationRegistry::class);
		return $registry instanceof IMaterializationRegistry ? $registry : null;
	}

	private function getStateStore(): ?IStateStore {
		if (!$this->container->has(IStateStore::class)) {
			return null;
		}

		$stateStore = $this->container->get(IStateStore::class);
		return $stateStore instanceof IStateStore ? $stateStore : null;
	}

	private function getRefreshMode(?IStateStore $stateStore): string {
		$mode = strtolower(trim((string)$this->readStateOrConfig($stateStore, 'mode', 'refresh')));
		return in_array($mode, ['refresh', 'full', 'incremental'], true) ? $mode : 'refresh';
	}

	private function isForced(?IStateStore $stateStore): bool {
		$value = $this->readStateOrConfig($stateStore, 'force', 0);
		if (is_bool($value)) {
			return $value;
		}

		if (is_numeric($value)) {
			return (int)$value === 1;
		}

		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}

	/**
	 * @return string[]
	 */
	private function getConfiguredManifestIds(?IStateStore $stateStore): array {
		$configured = $this->readStateOrConfig($stateStore, 'manifests', null);
		if ($configured === null || $configured === '') {
			$configured = $this->readStateOrConfig($stateStore, 'manifest', null);
		}

		return $this->normalizeManifestIds($configured);
	}

	private function refreshManifest(IMaterializationService $service, string $manifestId, string $mode): MaterializationRunResult {
		try {
			return match ($mode) {
				'full' => $service->buildFull($manifestId),
				'incremental' => $service->buildIncremental($manifestId),
				default => $service->refresh($manifestId)
			};
		} catch (Throwable $e) {
			return new MaterializationRunResult(
				manifestId: $manifestId,
				success: false,
				message: $e->getMessage()
			);
		}
	}


	private function runMaintenanceIfDue(IMaterializationRegistry $registry, IStateStore $stateStore): string {
		if (!$registry instanceof DatabaseMaterializationRegistry) {
			return '';
		}

		if (!$this->isCleanupEnabled($stateStore)) {
			return '';
		}

		$intervalSeconds = $this->getCleanupIntervalSeconds($stateStore);
		$lastRun = (int)$stateStore->get($this->stateKey('cleanup.lastrun'), 0);
		if ($lastRun > 0 && $lastRun + $intervalSeconds > time()) {
			return '';
		}

		try {
			$result = $registry->cleanupHistory($this->getCleanupKeepRunsPerManifest($stateStore));
			$stateStore->set($this->stateKey('cleanup.lastrun'), time());
			$stateStore->flush();

			$registryDeleted = (int)($result['registry']['deleted'] ?? 0);
			$runDeleted = (int)($result['runs']['deleted'] ?? 0);

			return 'Materialization history cleanup done (registry deleted: ' . $registryDeleted . ', runs deleted: ' . $runDeleted . ')';
		} catch (Throwable $e) {
			return 'Materialization history cleanup failed: ' . $e->getMessage();
		}
	}

	private function isCleanupEnabled(IStateStore $stateStore): bool {
		$value = $this->readStateOrConfig($stateStore, 'cleanup.enabled', 1);
		if (is_bool($value)) {
			return $value;
		}

		if (is_numeric($value)) {
			return (int)$value === 1;
		}

		return !in_array(strtolower(trim((string)$value)), ['0', 'false', 'no', 'off', 'disabled'], true);
	}

	private function getCleanupIntervalSeconds(IStateStore $stateStore): int {
		$value = (int)$this->readStateOrConfig($stateStore, 'cleanup.interval_seconds', 86400);
		return $value > 0 ? $value : 86400;
	}

	private function getCleanupKeepRunsPerManifest(IStateStore $stateStore): int {
		$value = (int)$this->readStateOrConfig($stateStore, 'cleanup.keep_runs_per_manifest', 100);
		return $value > 0 ? $value : 100;
	}

	private function readStateOrConfig(?IStateStore $stateStore, string $key, mixed $default): mixed {
		if ($stateStore !== null) {
			$value = $stateStore->get($this->stateKey($key), null);
			if ($value !== null && $value !== '') {
				return $value;
			}
		}

		$conf = $this->getJobConf();
		return $conf['datahawkmaterializationrefreshjob.' . $key] ?? $default;
	}

	/**
	 * @return string[]
	 */
	private function normalizeManifestIds(mixed $value): array {
		if ($value === null || $value === '' || $value === 'all') {
			return [];
		}

		if (is_string($value)) {
			$parts = array_map('trim', explode(',', $value));
			return array_values(array_filter($parts, fn(string $part) => $part !== '' && $part !== 'all'));
		}

		if (!is_array($value)) {
			return [];
		}

		$result = [];
		foreach ($value as $item) {
			$item = trim((string)$item);
			if ($item !== '' && $item !== 'all') {
				$result[] = $item;
			}
		}

		return array_values(array_unique($result));
	}

	/**
	 * @param MaterializationRunResult[] $failed
	 */
	private function formatFailures(array $failed): string {
		$messages = [];
		foreach ($failed as $result) {
			$messages[] = $result->manifestId . ': ' . $result->message;
		}

		return implode(' | ', $messages);
	}

	private function clearForceState(IStateStore $stateStore): void {
		if ($stateStore->has($this->stateKey('force'))) {
			$stateStore->delete($this->stateKey('force'));
			$stateStore->flush();
		}
	}

	private function stateKey(string $suffix): string {
		return self::STATE_PREFIX . $suffix;
	}
}
