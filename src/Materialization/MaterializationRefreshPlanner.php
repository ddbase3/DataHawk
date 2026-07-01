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
	 * @return string[]
	 */
	public function getManifestIdsToRefresh(array $configuredManifestIds = [], bool $force = false): array {
		$manifests = $this->getManifestMap();
		$candidates = $configuredManifestIds === [] ? array_keys($manifests) : $configuredManifestIds;
		$candidates = $this->sortManifestIdsByPriority($candidates, $manifests);

		$result = [];
		$seen = [];

		foreach ($candidates as $manifestId) {
			$manifest = $manifests[$manifestId] ?? null;
			if ($manifest === null || !$manifest->enabled) {
				continue;
			}

			if (!$force && !$this->isManifestDue($manifest)) {
				continue;
			}

			$this->addManifestWithRequiredDependencies($manifestId, $manifests, $result, $seen, $force);
		}

		return $this->sortManifestIdsByDependencies($result, $manifests);
	}

	public function markAttempt(MaterializationManifest $manifest): void {
		$this->stateStore->set($this->stateKey($manifest->id, 'last_attempt_at'), time());
	}

	public function markResult(MaterializationManifest $manifest, MaterializationRunResult $result): void {
		$now = time();
		$this->stateStore->set($this->stateKey($manifest->id, 'last_status'), $result->success ? 'success' : 'failed');
		$this->stateStore->set($this->stateKey($manifest->id, 'last_message'), $result->message);

		if ($result->rowCount !== null) {
			$this->stateStore->set($this->stateKey($manifest->id, 'last_row_count'), $result->rowCount);
		}

		if ($result->success) {
			$this->stateStore->set($this->stateKey($manifest->id, 'last_success_at'), $now);
			$this->stateStore->set($this->stateKey($manifest->id, 'last_success_date'), date('Y-m-d', $now));
		}
	}

	public function flushState(): void {
		$this->stateStore->flush();
	}

	public function getManifest(string $manifestId): ?MaterializationManifest {
		return $this->manifestProvider->getManifest($manifestId);
	}

	/**
	 * @return array<string, MaterializationManifest>
	 */
	private function getManifestMap(): array {
		$manifests = [];
		foreach ($this->manifestProvider->getManifests() as $manifest) {
			if ($manifest->id !== '') {
				$manifests[$manifest->id] = $manifest;
			}
		}

		return $manifests;
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
			function(string $a, string $b) use ($manifests): int {
				$priorityA = $manifests[$a]->priority ?? 100;
				$priorityB = $manifests[$b]->priority ?? 100;

				if ($priorityA === $priorityB) {
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

		return match ($policy) {
			'always' => true,
			'manual' => false,
			'daily_after' => $this->isDailyAfterDue($manifest, $schedule),
			default => $this->isIntervalDue($manifest, $schedule)
		};
	}

	private function isIntervalDue(MaterializationManifest $manifest, array $schedule): bool {
		$seconds = (int)($schedule['seconds'] ?? 300);
		$seconds = $seconds > 0 ? $seconds : 300;
		$lastSuccess = (int)$this->stateStore->get($this->stateKey($manifest->id, 'last_success_at'), 0);

		return $lastSuccess <= 0 || (time() - $lastSuccess) >= $seconds;
	}

	private function isDailyAfterDue(MaterializationManifest $manifest, array $schedule): bool {
		$time = trim((string)($schedule['time'] ?? '02:00'));
		if (preg_match('/^\d{2}:\d{2}$/', $time) !== 1) {
			$time = '02:00';
		}

		$today = date('Y-m-d');
		$lastSuccessDate = (string)$this->stateStore->get($this->stateKey($manifest->id, 'last_success_date'), '');

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
		if (isset($seen[$manifestId])) {
			return;
		}

		$manifest = $manifests[$manifestId] ?? null;
		if ($manifest === null || !$manifest->enabled) {
			return;
		}

		$seen[$manifestId] = true;

		foreach ($manifest->dependsOn as $dependencyId) {
			$dependency = $manifests[$dependencyId] ?? null;
			if ($dependency === null || !$dependency->enabled) {
				continue;
			}

			if ($this->shouldRefreshDependency($manifest, $dependency, $force)) {
				$this->addManifestWithRequiredDependencies($dependencyId, $manifests, $result, $seen, $force);
			}
		}

		$result[] = $manifestId;
	}

	private function shouldRefreshDependency(MaterializationManifest $parent, MaterializationManifest $dependency, bool $force): bool {
		if ($force) {
			return true;
		}

		return match ($parent->dependencyRefresh) {
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
			throw new RuntimeException('Circular materialization dependency detected at manifest: ' . $manifestId);
		}

		$visiting[$manifestId] = true;
		$manifest = $manifests[$manifestId] ?? null;

		if ($manifest !== null) {
			foreach ($manifest->dependsOn as $dependencyId) {
				if (isset($wanted[$dependencyId])) {
					$this->visitManifest($dependencyId, $wanted, $manifests, $visited, $visiting, $result);
				}
			}
		}

		unset($visiting[$manifestId]);
		$visited[$manifestId] = true;
		$result[] = $manifestId;
	}

	private function stateKey(string $manifestId, string $suffix): string {
		$manifestId = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $manifestId) ?? $manifestId;
		return self::STATE_PREFIX . $manifestId . '.' . $suffix;
	}
}
