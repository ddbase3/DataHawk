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
use Base3\Worker\Api\IPolicyControlledJob;
use Base3\Worker\Policy\PolicyControlledJobTrait;
use ResourceFoundation\Api\IMaterializationManifestProvider;
use ResourceFoundation\Api\IMaterializationService;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\MaterializationRunResult;
use Throwable;

/**
 * MaterializationRefreshJob
 *
 * Refreshes DataHawk materialization manifests.
 *
 * Scheduling:
 * - Controlled by DailyWindowJobPolicy.
 * - Defaults to 02:00-04:00.
 *
 * Configuration:
 * - job.datahawkmaterializationrefreshjob.active = 1 enables the job.
 * - job.datahawkmaterializationrefreshjob.priority controls worker priority.
 * - job.datahawkmaterializationrefreshjob.from / .to control the daily window.
 * - job.datahawkmaterializationrefreshjob.manifest limits the run to one manifest.
 * - job.datahawkmaterializationrefreshjob.manifests limits the run to multiple manifests.
 * - job.datahawkmaterializationrefreshjob.mode can be refresh, full, or incremental.
 *
 * Runtime state overrides:
 * - datahawk.job.materializationrefresh.manifest
 * - datahawk.job.materializationrefresh.manifests
 * - datahawk.job.materializationrefresh.mode
 *
 * Behavior:
 * - If materialization services are not wired, the job skips safely.
 * - Manifest dependencies are refreshed before dependent manifests.
 */
final class MaterializationRefreshJob implements IPolicyControlledJob {

	use PolicyControlledJobTrait;

	private const STATE_PREFIX = 'datahawk.job.materializationrefresh.';

	private const DEFAULT_PRIORITY = 1;
	private const DEFAULT_WINDOW_FROM = '02:00';
	private const DEFAULT_WINDOW_TO = '04:00';

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

	public function getPolicyDefinition(): array {
		$conf = $this->getJobConf();

		return [
			'policy' => 'dailywindowjobpolicy',
			'data' => [
				'from' => (string)($conf['datahawkmaterializationrefreshjob.from'] ?? self::DEFAULT_WINDOW_FROM),
				'to' => (string)($conf['datahawkmaterializationrefreshjob.to'] ?? self::DEFAULT_WINDOW_TO)
			]
		];
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

		$stateStore = $this->getStateStore();

		try {
			$manifestIds = $this->getOrderedManifestIds($manifestProvider, $stateStore);
		} catch (Throwable $e) {
			return 'Materialization refresh failed before execution: ' . $e->getMessage();
		}

		if ($manifestIds === []) {
			return 'Skip (no materialization manifests configured)';
		}

		$mode = $this->getRefreshMode($stateStore);
		$results = [];

		foreach ($manifestIds as $manifestId) {
			$results[] = $this->refreshManifest($service, $manifestId, $mode);
		}

		$failed = array_values(array_filter($results, fn(MaterializationRunResult $result) => !$result->success));
		$successCount = count($results) - count($failed);

		if ($failed === []) {
			$this->markRun();
			return 'Materialization refresh done (' . $successCount . '/' . count($results) . ' succeeded, mode: ' . $mode . ')';
		}

		return 'Materialization refresh finished with failures ('
			. $successCount . '/' . count($results) . ' succeeded, mode: ' . $mode . '): '
			. $this->formatFailures($failed);
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

	private function getStateStore(): ?IStateStore {
		if (!$this->container->has(IStateStore::class)) {
			return null;
		}

		$stateStore = $this->container->get(IStateStore::class);
		return $stateStore instanceof IStateStore ? $stateStore : null;
	}

	/**
	 * @return string[]
	 */
	private function getOrderedManifestIds(IMaterializationManifestProvider $manifestProvider, ?IStateStore $stateStore): array {
		$manifests = $this->getManifestMap($manifestProvider);
		$manifestIds = $this->getConfiguredManifestIds($stateStore);

		if ($manifestIds === []) {
			$manifestIds = array_keys($manifests);
		}

		$expandedIds = $this->expandManifestIdsWithDependencies($manifestIds, $manifests);

		return $this->sortManifestIdsByDependencies($expandedIds, $manifests);
	}

	/**
	 * @return array<string, MaterializationManifest>
	 */
	private function getManifestMap(IMaterializationManifestProvider $manifestProvider): array {
		$manifests = [];
		foreach ($manifestProvider->getManifests() as $manifest) {
			$manifests[$manifest->id] = $manifest;
		}

		return $manifests;
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

	/**
	 * @param string[] $manifestIds
	 * @param array<string, MaterializationManifest> $manifests
	 * @return string[]
	 */
	private function expandManifestIdsWithDependencies(array $manifestIds, array $manifests): array {
		$result = [];
		$seen = [];

		foreach ($manifestIds as $manifestId) {
			$this->addManifestWithDependencies($manifestId, $manifests, $result, $seen);
		}

		return $result;
	}

	/**
	 * @param array<string, MaterializationManifest> $manifests
	 * @param string[] $result
	 * @param array<string, bool> $seen
	 */
	private function addManifestWithDependencies(string $manifestId, array $manifests, array &$result, array &$seen): void {
		if (isset($seen[$manifestId])) {
			return;
		}

		$seen[$manifestId] = true;
		$manifest = $manifests[$manifestId] ?? null;

		if ($manifest !== null) {
			foreach ($manifest->dependsOn as $dependency) {
				$this->addManifestWithDependencies($dependency, $manifests, $result, $seen);
			}
		}

		$result[] = $manifestId;
	}

	/**
	 * @param string[] $manifestIds
	 * @param array<string, MaterializationManifest> $manifests
	 * @return string[]
	 */
	private function sortManifestIdsByDependencies(array $manifestIds, array $manifests): array {
		$wanted = array_fill_keys($manifestIds, true);
		$result = [];
		$visited = [];
		$visiting = [];

		foreach ($manifestIds as $manifestId) {
			$this->visitManifest($manifestId, $wanted, $manifests, $visited, $visiting, $result);
		}

		return $result;
	}

	/**
	 * @param array<string, bool> $wanted
	 * @param array<string, MaterializationManifest> $manifests
	 * @param array<string, bool> $visited
	 * @param array<string, bool> $visiting
	 * @param string[] $result
	 */
	private function visitManifest(
		string $manifestId,
		array $wanted,
		array $manifests,
		array &$visited,
		array &$visiting,
		array &$result
	): void {
		if (isset($visited[$manifestId])) {
			return;
		}

		if (isset($visiting[$manifestId])) {
			throw new \RuntimeException('Circular materialization dependency detected at manifest: ' . $manifestId);
		}

		$visiting[$manifestId] = true;
		$manifest = $manifests[$manifestId] ?? null;

		if ($manifest !== null) {
			foreach ($manifest->dependsOn as $dependency) {
				if (isset($wanted[$dependency])) {
					$this->visitManifest($dependency, $wanted, $manifests, $visited, $visiting, $result);
				}
			}
		}

		unset($visiting[$manifestId]);
		$visited[$manifestId] = true;
		$result[] = $manifestId;
	}

	private function getRefreshMode(?IStateStore $stateStore): string {
		$mode = strtolower(trim((string)$this->readStateOrConfig($stateStore, 'mode', 'refresh')));
		return in_array($mode, ['refresh', 'full', 'incremental'], true) ? $mode : 'refresh';
	}

	private function refreshManifest(IMaterializationService $service, string $manifestId, string $mode): MaterializationRunResult {
		return match ($mode) {
			'full' => $service->buildFull($manifestId),
			'incremental' => $service->buildIncremental($manifestId),
			default => $service->refresh($manifestId)
		};
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

	private function stateKey(string $suffix): string {
		return self::STATE_PREFIX . $suffix;
	}
}
