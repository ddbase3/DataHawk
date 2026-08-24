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

namespace DataHawk\Materialization;

use Base3\State\Api\IStateStore;
use ResourceFoundation\Api\IMaterializationManifestProvider;
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\IScopedMaterializationManifestProvider;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\MaterializationRunResult;
use RuntimeException;

class MaterializationRefreshPlanner {

	private const STATE_PREFIX = 'datahawk.materialization.';

	public function __construct(
		private readonly IMaterializationManifestProvider $manifestProvider,
		private readonly IMaterializationRegistry $registry,
		private readonly IStateStore $stateStore
	) {}

	/**
	 * @param string[] $configuredManifestIds
	 * @return string[] Qualified manifest ids in scope:id form
	 */
	public function getManifestIdsToRefresh(array $configuredManifestIds = [], bool $force = false): array {
		$manifests = $this->getManifestMap();
		$candidates = $configuredManifestIds === []
			? array_keys($manifests)
			: $this->resolveConfiguredManifestIds($configuredManifestIds, $manifests);
		$candidates = $this->sortManifestIdsByPriority($candidates, $manifests);

		$result = [];
		$seen = [];

		foreach($candidates as $manifestId) {
			$manifest = $manifests[$manifestId] ?? null;
			if($manifest === null || !$manifest->enabled) {
				continue;
			}

			if(!$force && !$this->isManifestDue($manifest)) {
				continue;
			}

			$this->addManifestWithRequiredDependencies($manifestId, $manifests, $result, $seen, $force);
		}

		return $this->sortManifestIdsByDependencies($result, $manifests);
	}

	public function markAttempt(MaterializationManifest $manifest): void {
		$this->stateStore->set($this->stateKey($manifest, 'last_attempt_at'), time());
	}

	public function markResult(MaterializationManifest $manifest, MaterializationRunResult $result): void {
		$now = time();
		$this->stateStore->set($this->stateKey($manifest, 'last_status'), $result->success ? 'success' : 'failed');
		$this->stateStore->set($this->stateKey($manifest, 'last_message'), $result->message);

		if($result->rowCount !== null) {
			$this->stateStore->set($this->stateKey($manifest, 'last_row_count'), $result->rowCount);
		}

		if($result->success) {
			$this->stateStore->set($this->stateKey($manifest, 'last_success_at'), $now);
			$this->stateStore->set($this->stateKey($manifest, 'last_success_date'), date('Y-m-d', $now));
		}
	}

	public function flushState(): void {
		$this->stateStore->flush();
	}

	public function getManifest(string $manifestId): ?MaterializationManifest {
		[$scope, $localId] = $this->splitQualifiedManifestId($manifestId);
		if($scope !== null && $this->manifestProvider instanceof IScopedMaterializationManifestProvider) {
			return $this->manifestProvider->getManifestForScope($scope, $localId);
		}

		return $this->manifestProvider->getManifest($manifestId);
	}

	/**
	 * @return array<string, MaterializationManifest>
	 */
	private function getManifestMap(): array {
		$manifests = [];

		if($this->manifestProvider instanceof IScopedMaterializationManifestProvider) {
			foreach($this->manifestProvider->getScopes() as $scope) {
				foreach($this->manifestProvider->getManifestsForScope($scope) as $manifest) {
					$key = $this->manifestKey($scope, $manifest->id);
					if(isset($manifests[$key])) {
						throw new RuntimeException('Duplicate materialization identity: ' . $key);
					}
					$manifests[$key] = $manifest;
				}
			}

			return $manifests;
		}

		foreach($this->manifestProvider->getManifests() as $manifest) {
			$scope = $this->getManifestScope($manifest);
			$key = $this->manifestKey($scope, $manifest->id);
			if(isset($manifests[$key])) {
				throw new RuntimeException('Duplicate materialization identity: ' . $key);
			}
			$manifests[$key] = $manifest;
		}

		return $manifests;
	}

	/**
	 * @param string[] $configuredManifestIds
	 * @param array<string,MaterializationManifest> $manifests
	 * @return string[]
	 */
	private function resolveConfiguredManifestIds(array $configuredManifestIds, array $manifests): array {
		$result = [];

		foreach($configuredManifestIds as $configuredId) {
			$configuredId = trim((string)$configuredId);
			if($configuredId === '') {
				continue;
			}

			if(isset($manifests[$configuredId])) {
				$result[] = $configuredId;
				continue;
			}

			[$scope, $localId] = $this->splitQualifiedManifestId($configuredId);
			if($scope !== null) {
				$key = $this->manifestKey($scope, $localId);
				if(isset($manifests[$key])) {
					$result[] = $key;
				}
				continue;
			}

			$matches = array_keys(array_filter(
				$manifests,
				fn(MaterializationManifest $manifest) => $manifest->id === $localId
			));

			if(count($matches) > 1) {
				throw new RuntimeException('Configured materialization id is ambiguous: ' . $localId . ' (' . implode(', ', $matches) . ')');
			}

			if($matches !== []) {
				$result[] = $matches[0];
			}
		}

		return array_values(array_unique($result));
	}

	/**
	 * @param string[] $manifestIds
	 * @param array<string, MaterializationManifest> $manifests
	 * @return string[]
	 */
	private function sortManifestIdsByPriority(array $manifestIds, array $manifests): array {
		$manifestIds = array_values(array_unique($manifestIds));

		usort(
			$manifestIds,
			function(string $a, string $b) use($manifests): int {
				$priorityA = $manifests[$a]->priority ?? 100;
				$priorityB = $manifests[$b]->priority ?? 100;

				if($priorityA === $priorityB) {
					return strnatcasecmp($a, $b);
				}

				return $priorityA <=> $priorityB;
			}
		);

		return $manifestIds;
	}

	private function isManifestDue(MaterializationManifest $manifest): bool {
		$schedule = $manifest->schedule;
		$policy = strtolower((string)($schedule['policy'] ?? 'interval'));

		return match($policy) {
			'always' => true,
			'manual' => false,
			'daily_after' => $this->isDailyAfterDue($manifest, $schedule),
			default => $this->isIntervalDue($manifest, $schedule)
		};
	}

	private function isIntervalDue(MaterializationManifest $manifest, array $schedule): bool {
		$seconds = (int)($schedule['seconds'] ?? 300);
		$seconds = $seconds > 0 ? $seconds : 300;
		$lastSuccess = (int)$this->stateStore->get($this->stateKey($manifest, 'last_success_at'), 0);

		return $lastSuccess <= 0 || (time() - $lastSuccess) >= $seconds;
	}

	private function isDailyAfterDue(MaterializationManifest $manifest, array $schedule): bool {
		$time = trim((string)($schedule['time'] ?? '02:00'));
		if(preg_match('/^\d{2}:\d{2}$/', $time) !== 1) {
			$time = '02:00';
		}

		$today = date('Y-m-d');
		$lastSuccessDate = (string)$this->stateStore->get($this->stateKey($manifest, 'last_success_date'), '');

		return $lastSuccessDate !== $today && date('H:i') >= $time;
	}

	/**
	 * @param array<string, MaterializationManifest> $manifests
	 * @param string[] $result
	 * @param array<string, bool> $seen
	 */
	private function addManifestWithRequiredDependencies(
		string $manifestId,
		array $manifests,
		array &$result,
		array &$seen,
		bool $force
	): void {
		if(isset($seen[$manifestId])) {
			return;
		}

		$manifest = $manifests[$manifestId] ?? null;
		if($manifest === null || !$manifest->enabled) {
			return;
		}

		$seen[$manifestId] = true;

		foreach($manifest->dependsOn as $dependencyId) {
			$dependencyKey = $this->resolveDependencyKey($manifestId, $dependencyId, $manifests);
			$dependency = $dependencyKey !== null ? ($manifests[$dependencyKey] ?? null) : null;
			if($dependency === null || !$dependency->enabled) {
				continue;
			}

			if($this->shouldRefreshDependency($manifest, $dependency, $force)) {
				$this->addManifestWithRequiredDependencies($dependencyKey, $manifests, $result, $seen, $force);
			}
		}

		$result[] = $manifestId;
	}

	private function shouldRefreshDependency(MaterializationManifest $parent, MaterializationManifest $dependency, bool $force): bool {
		if($force) {
			return true;
		}

		return match($parent->dependencyRefresh) {
			'cascade' => true,
			'due' => !$this->hasCurrentGeneration($dependency) || $this->isManifestDue($dependency),
			'current' => false,
			default => !$this->hasCurrentGeneration($dependency)
		};
	}

	private function hasCurrentGeneration(MaterializationManifest $manifest): bool {
		return $this->registry->getCurrentGeneration($manifest->targetSchema, $manifest->logicalTable) !== null;
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

		foreach($manifestIds as $manifestId) {
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
		if(isset($visited[$manifestId])) {
			return;
		}

		if(isset($visiting[$manifestId])) {
			throw new RuntimeException('Circular materialization dependency detected at manifest: ' . $manifestId);
		}

		$visiting[$manifestId] = true;
		$manifest = $manifests[$manifestId] ?? null;

		if($manifest !== null) {
			foreach($manifest->dependsOn as $dependencyId) {
				$dependencyKey = $this->resolveDependencyKey($manifestId, $dependencyId, $manifests);
				if($dependencyKey !== null && isset($wanted[$dependencyKey])) {
					$this->visitManifest($dependencyKey, $wanted, $manifests, $visited, $visiting, $result);
				}
			}
		}

		unset($visiting[$manifestId]);
		$visited[$manifestId] = true;
		$result[] = $manifestId;
	}

	/**
	 * @param array<string,MaterializationManifest> $manifests
	 */
	private function resolveDependencyKey(string $parentKey, string $dependencyId, array $manifests): ?string {
		[$dependencyScope, $dependencyLocalId] = $this->splitQualifiedManifestId($dependencyId);
		if($dependencyScope !== null) {
			$key = $this->manifestKey($dependencyScope, $dependencyLocalId);
			return isset($manifests[$key]) ? $key : null;
		}

		[$parentScope] = $this->splitQualifiedManifestId($parentKey);
		if($parentScope !== null) {
			$localKey = $this->manifestKey($parentScope, $dependencyLocalId);
			if(isset($manifests[$localKey])) {
				return $localKey;
			}
		}

		$matches = array_keys(array_filter(
			$manifests,
			fn(MaterializationManifest $manifest) => $manifest->id === $dependencyLocalId
		));

		if(count($matches) > 1) {
			throw new RuntimeException('Materialization dependency is ambiguous: ' . $dependencyLocalId . ' (' . implode(', ', $matches) . ')');
		}

		return $matches[0] ?? null;
	}

	private function getManifestScope(MaterializationManifest $manifest): string {
		$scope = trim($manifest->targetSchema);
		return $scope !== '' ? $scope : 'default';
	}

	private function manifestKey(string $scope, string $manifestId): string {
		return trim($scope) . ':' . trim($manifestId);
	}

	/**
	 * @return array{0:?string,1:string}
	 */
	private function splitQualifiedManifestId(string $manifestId): array {
		if(!str_contains($manifestId, ':')) {
			return [null, $manifestId];
		}

		[$scope, $localId] = explode(':', $manifestId, 2);
		if($scope === '' || $localId === '') {
			return [null, $manifestId];
		}

		return [$scope, $localId];
	}

	private function stateKey(MaterializationManifest $manifest, string $suffix): string {
		$manifestId = $this->manifestKey($this->getManifestScope($manifest), $manifest->id);
		$manifestId = preg_replace('/[^a-zA-Z0-9_.:-]+/', '_', $manifestId) ?? $manifestId;
		return self::STATE_PREFIX . $manifestId . '.' . $suffix;
	}
}
