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

namespace DataHawk\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IContainer;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Database\Api\IDatabase;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\State\Api\IStateStore;
use DataHawk\Materialization\MaterializationRefreshPlanner;
use ResourceFoundation\Api\IMaterializationManifestProvider;
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\IMaterializationService;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\MaterializationRunResult;
use Throwable;

abstract class AbstractDataHawkMaterializationDisplay implements IDisplay {

	private const STATE_PREFIX = 'datahawk.materialization.';

	private const TECHNICAL_TABLES = [
		'base3_mat_registry',
		'base3_mat_run',
	];

	public function __construct(
		private readonly IContainer $container,
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IDatabase $database
	) {}

	public function setData($data) {
		// no-op
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$out = strtolower((string)$out);

		if ($out === 'json') {
			return $this->handleJson($final);
		}

		return $this->handleHtml();
	}

	abstract protected function getTitle(): string;

	abstract protected function getViewName(): string;

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'DataHawk');
		$this->view->setTemplate('Display/MaterializationAdminDisplay.php');
		$this->view->assign('title', $this->getTitle());
		$this->view->assign('viewName', $this->getViewName());
		$this->view->assign(
			'modularGridCssUrl',
			$this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/styles/modulargrid.css')
		);
		$this->view->assign(
			'modularGridJsUrl',
			$this->assetResolver->resolve('plugin/ClientStack/assets/modulargrid/index.js')
		);
		$this->view->assign(
			'service',
			$this->linkTargetService->getLink(
				[
					'name' => static::getName(),
					'out' => 'json'
				]
			)
		);

		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final = false): string {
		try {
			$response = $this->buildJsonResponse();
		} catch (Throwable $e) {
			$response = [
				'ok' => false,
				'error' => 'Materialization admin request failed.',
				'details' => $e->getMessage(),
			];
		}

		if ($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string)json_encode(
			$response,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildJsonResponse(): array {
		$payload = $this->request->getJsonBody();

		if (!is_array($payload)) {
			$payload = [];
		}

		$mode = $this->readString($payload, 'mode', 'page');

		if ($mode === 'grid') {
			return $this->buildGridResponse($payload);
		}

		if ($mode === 'refresh_manifest') {
			return $this->buildRefreshManifestResponse($payload);
		}

		if ($mode === 'refresh_due') {
			return $this->buildRefreshDueResponse(false);
		}

		if ($mode === 'refresh_all') {
			return $this->buildRefreshDueResponse(true);
		}

		return $this->buildPageResponse();
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function buildGridResponse(array $payload): array {
		$gridView = $this->readString($payload, 'gridView', $this->getViewName());
		$rows = $this->loadGridRows($gridView);
		$search = $this->readString($payload, 'search');
		$filters = $this->normalizeGridFilters($payload['filters'] ?? null);
		$sort = $this->normalizeGridSort($payload['sort'] ?? null, $payload);

		$rows = array_values(array_filter($rows, fn(array $row) => $this->matchesGridSearch($row, $search) && $this->matchesGridFilters($row, $filters)));
		usort($rows, fn(array $a, array $b) => $this->compareGridRows($a, $b, $sort));

		$total = count($rows);
		$page = isset($payload['page']) ? max(1, (int)$payload['page']) : 1;
		$pageSize = isset($payload['pageSize']) ? (int)$payload['pageSize'] : 50;
		$pageSize = max(1, min(250, $pageSize));
		$offset = max(0, ($page - 1) * $pageSize);
		$data = array_slice($rows, $offset, $pageSize);

		return [
			'ok' => true,
			'mode' => 'grid',
			'gridView' => $gridView,
			'data' => array_values($data),
			'total' => $total,
			'page' => $page,
			'pageSize' => $pageSize,
			'totalPages' => $pageSize > 0 ? (int)ceil($total / $pageSize) : 0,
			'hasMore' => ($offset + $pageSize) < $total,
			'nextCursor' => null,
			'appliedSearch' => $search,
			'appliedSort' => [$sort],
			'appliedFilters' => $filters,
			'appliedGroup' => [],
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function loadGridRows(string $gridView): array {
		return match ($gridView) {
			'overview_due' => array_values(array_filter($this->loadManifestRows(), fn(array $row) => (bool)($row['is_due'] ?? false))),
			'overview_manifests', 'manifests' => $this->loadManifestRows(),
			'overview_runs', 'runs' => $this->loadRunRows(),
			'registry' => $this->loadRegistryRows(),
			'tables' => $this->loadTableRows(),
			default => $this->loadManifestRows(),
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildPageResponse(): array {
		return [
			'ok' => true,
			'mode' => 'page',
			'view' => $this->getViewName(),
			'generated_at' => time(),
			'overview' => $this->buildOverview(),
			'manifests' => $this->loadManifestRows(),
			'registry' => $this->loadRegistryRows(),
			'runs' => $this->loadRunRows(),
			'tables' => $this->loadTableRows(),
		];
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>
	 */
	private function buildRefreshManifestResponse(array $payload): array {
		$manifestId = $this->readString($payload, 'manifestId');
		$mode = $this->normalizeBuildMode($this->readString($payload, 'buildMode', 'refresh'));

		if ($manifestId === '') {
			return $this->buildErrorResponse('Missing materialization manifest id.', 'refresh_manifest');
		}

		$manifestProvider = $this->getManifestProvider();
		if ($manifestProvider === null) {
			return $this->buildErrorResponse('Materialization manifest provider is not wired.', 'refresh_manifest');
		}

		$manifest = $manifestProvider->getManifest($manifestId);
		if ($manifest === null) {
			return $this->buildErrorResponse('Unknown materialization manifest: ' . $manifestId, 'refresh_manifest');
		}

		$service = $this->getMaterializationService();
		if ($service === null) {
			return $this->buildErrorResponse('Materialization service is not wired.', 'refresh_manifest');
		}

		$result = $this->refreshManifest($service, $manifestId, $mode);
		$this->markManualResult($manifest, $result);

		return [
			'ok' => $result->success,
			'mode' => 'refresh_manifest',
			'buildMode' => $mode,
			'result' => $this->formatRunResult($result),
			'page' => $this->buildPageResponse(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildRefreshDueResponse(bool $force): array {
		$manifestProvider = $this->getManifestProvider();
		if ($manifestProvider === null) {
			return $this->buildErrorResponse('Materialization manifest provider is not wired.', $force ? 'refresh_all' : 'refresh_due');
		}

		$registry = $this->getMaterializationRegistry();
		if ($registry === null) {
			return $this->buildErrorResponse('Materialization registry is not wired.', $force ? 'refresh_all' : 'refresh_due');
		}

		$stateStore = $this->getStateStore();
		if ($stateStore === null) {
			return $this->buildErrorResponse('State store is not wired.', $force ? 'refresh_all' : 'refresh_due');
		}

		$service = $this->getMaterializationService();
		if ($service === null) {
			return $this->buildErrorResponse('Materialization service is not wired.', $force ? 'refresh_all' : 'refresh_due');
		}

		$planner = new MaterializationRefreshPlanner($manifestProvider, $registry, $stateStore);
		$manifestIds = $planner->getManifestIdsToRefresh([], $force);
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
			$result = $this->refreshManifest($service, $manifestId, 'refresh');
			$planner->markResult($manifest, $result);
			$results[] = $result;
		}

		$planner->flushState();

		$failed = array_values(array_filter($results, fn(MaterializationRunResult $result) => !$result->success));

		return [
			'ok' => $failed === [],
			'mode' => $force ? 'refresh_all' : 'refresh_due',
			'force' => $force,
			'manifestIds' => $manifestIds,
			'results' => array_map(fn(MaterializationRunResult $result) => $this->formatRunResult($result), $results),
			'page' => $this->buildPageResponse(),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildOverview(): array {
		$manifests = $this->loadManifestRows();
		$registry = $this->loadRegistryRows();
		$runs = $this->loadRunRows();
		$tables = $this->loadTableRows();

		$currentRegistry = array_values(array_filter($registry, fn(array $row) => (int)($row['is_current'] ?? 0) === 1));
		$failedRuns = array_values(array_filter($runs, fn(array $row) => (string)($row['status'] ?? '') === 'failed'));
		$runningRuns = array_values(array_filter($runs, fn(array $row) => (string)($row['status'] ?? '') === 'running'));
		$dueManifests = array_values(array_filter($manifests, fn(array $row) => (bool)($row['is_due'] ?? false)));

		return [
			'manifest_count' => count($manifests),
			'enabled_manifest_count' => count(array_values(array_filter($manifests, fn(array $row) => (bool)($row['enabled'] ?? false)))),
			'due_manifest_count' => count($dueManifests),
			'current_generation_count' => count($currentRegistry),
			'materialized_table_count' => count($tables),
			'recent_run_count' => count($runs),
			'failed_recent_run_count' => count($failedRuns),
			'running_recent_run_count' => count($runningRuns),
		];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function loadManifestRows(): array {
		$provider = $this->getManifestProvider();
		if ($provider === null) {
			return [];
		}

		$rows = [];
		foreach ($provider->getManifests() as $manifest) {
			$current = $this->getCurrentGeneration($manifest);
			$state = $this->getManifestState($manifest);

			$rows[] = [
				'id' => $manifest->id,
				'enabled' => $manifest->enabled,
				'priority' => $manifest->priority,
				'source_schema' => $manifest->sourceSchema,
				'target_schema' => $manifest->targetSchema,
				'logical_table' => $manifest->logicalTable,
				'physical_prefix' => $manifest->physicalPrefix,
				'refresh_mode' => (string)($manifest->refresh['mode'] ?? 'full'),
				'schedule_policy' => (string)($manifest->schedule['policy'] ?? 'interval'),
				'schedule_text' => $this->formatSchedule($manifest),
				'dependency_refresh' => $manifest->dependencyRefresh,
				'depends_on' => $manifest->dependsOn,
				'columns' => count($manifest->columns),
				'indexes' => count($manifest->indexes),
				'is_due' => $this->isManifestDue($manifest),
				'due_text' => $this->formatDueText($manifest),
				'last_success_at' => $state['last_success_at'],
				'last_success_text' => $this->formatTimestamp($state['last_success_at']),
				'last_status' => $state['last_status'],
				'last_message' => $state['last_message'],
				'last_row_count' => $state['last_row_count'],
				'current_physical_table' => $current?->physicalTable ?? '',
				'current_row_count' => $current?->rowCount,
				'current_generation' => $current?->generation ?? '',
				'published_at' => $current?->publishedAt,
				'published_text' => $this->formatTimestamp($current?->publishedAt),
			];
		}

		usort($rows, fn(array $a, array $b) => ((int)$a['priority'] <=> (int)$b['priority']) ?: strnatcasecmp((string)$a['id'], (string)$b['id']));

		return $rows;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function loadRegistryRows(): array {
		if (!$this->tableExists('base3_mat_registry')) {
			return [];
		}

		$rows = $this->database->multiQuery(
			'SELECT id, schema_name, logical_table, physical_table, generation, row_count, status, is_current, published_at, created_at, meta_json ' .
			'FROM `base3_mat_registry` ORDER BY logical_table ASC, is_current DESC, published_at DESC, id DESC LIMIT 500'
		);

		return array_map(fn(array $row) => $this->normalizeRegistryRow($row), $rows);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function loadRunRows(): array {
		if (!$this->tableExists('base3_mat_run')) {
			return [];
		}

		$rows = $this->database->multiQuery(
			'SELECT id, manifest_id, schema_name, logical_table, physical_table, generation, mode, status, message, row_count, started_at, finished_at, meta_json ' .
			'FROM `base3_mat_run` ORDER BY id DESC LIMIT 200'
		);

		return array_map(fn(array $row) => $this->normalizeRunRow($row), $rows);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function loadTableRows(): array {
		$this->database->connect();
		if (!$this->database->connected()) {
			return [];
		}

		$rows = $this->database->multiQuery("SHOW TABLES LIKE 'base3\\_mat\\_%'");
		$registry = $this->loadRegistryRows();
		$currentByPhysical = [];
		$registeredByPhysical = [];

		foreach ($registry as $registryRow) {
			$physicalTable = (string)($registryRow['physical_table'] ?? '');
			if ($physicalTable === '') {
				continue;
			}

			$registeredByPhysical[$physicalTable] = $registryRow;
			if ((int)($registryRow['is_current'] ?? 0) === 1) {
				$currentByPhysical[$physicalTable] = $registryRow;
			}
		}

		$result = [];
		foreach ($rows as $row) {
			$tableName = $this->firstRowValue($row);
			if ($tableName === '') {
				continue;
			}

			if (in_array($tableName, self::TECHNICAL_TABLES, true)) {
				continue;
			}

			$registryRow = $registeredByPhysical[$tableName] ?? null;
			$currentRow = $currentByPhysical[$tableName] ?? null;

			$result[] = [
				'table_name' => $tableName,
				'logical_table' => (string)($registryRow['logical_table'] ?? ''),
				'schema_name' => (string)($registryRow['schema_name'] ?? ''),
				'generation' => (string)($registryRow['generation'] ?? ''),
				'row_count' => $registryRow['row_count'] ?? null,
				'is_current' => $currentRow !== null,
				'is_registered' => $registryRow !== null,
				'published_at' => $registryRow['published_at'] ?? null,
				'published_text' => $this->formatTimestamp(isset($registryRow['published_at']) ? (int)$registryRow['published_at'] : null),
			];
		}

		usort($result, fn(array $a, array $b) => strnatcasecmp((string)$a['table_name'], (string)$b['table_name']));

		return $result;
	}

	/**
	 * @param mixed $filtersPayload
	 * @return array<string, string>
	 */
	private function normalizeGridFilters(mixed $filtersPayload): array {
		if (!is_array($filtersPayload)) {
			return [];
		}

		$result = [];
		foreach ($filtersPayload as $key => $value) {
			if (!is_string($key) && !is_int($key)) {
				continue;
			}

			if (!is_scalar($value)) {
				continue;
			}

			$value = trim((string)$value);
			if ($value === '') {
				continue;
			}

			$result[(string)$key] = $value;
		}

		return $result;
	}

	/**
	 * @param mixed $sortPayload
	 * @param array<string, mixed> $payload
	 * @return array{key: string, dir: string, type: string}
	 */
	private function normalizeGridSort(mixed $sortPayload, array $payload): array {
		$sort = [
			'key' => $this->readString($payload, 'sortKey', 'id'),
			'dir' => strtolower($this->readString($payload, 'sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc',
			'type' => 'string',
		];

		if (is_array($sortPayload) && count($sortPayload) > 0) {
			$first = reset($sortPayload);
			if (is_array($first)) {
				$key = isset($first['key']) && is_scalar($first['key']) ? trim((string)$first['key']) : '';
				$dir = isset($first['dir']) && is_scalar($first['dir']) ? strtolower(trim((string)$first['dir'])) : 'asc';
				$type = isset($first['type']) && is_scalar($first['type']) ? strtolower(trim((string)$first['type'])) : 'string';

				if ($key !== '') {
					$sort['key'] = $key;
				}

				$sort['dir'] = $dir === 'desc' ? 'desc' : 'asc';
				$sort['type'] = in_array($type, ['number', 'int', 'float', 'bool', 'string'], true) ? $type : 'string';
			}
		}

		return $sort;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function matchesGridSearch(array $row, string $search): bool {
		if ($search === '') {
			return true;
		}

		$needle = $this->toLower($search);
		$haystack = [];

		foreach ($row as $value) {
			if (is_array($value)) {
				$haystack[] = implode(' ', array_map('strval', $value));
				continue;
			}

			if (is_scalar($value) || $value === null) {
				$haystack[] = (string)$value;
			}
		}

		return strpos($this->toLower(implode(' ', $haystack)), $needle) !== false;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param array<string, string> $filters
	 */
	private function matchesGridFilters(array $row, array $filters): bool {
		foreach ($filters as $key => $expected) {
			$value = $row[$key] ?? '';

			if (is_bool($value)) {
				$value = $value ? '1' : '0';
			}
			elseif (is_array($value)) {
				$value = implode(', ', array_map('strval', $value));
			}
			elseif (!is_scalar($value) && $value !== null) {
				$value = '';
			}

			if (strpos($this->toLower((string)$value), $this->toLower($expected)) === false) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $a
	 * @param array<string, mixed> $b
	 * @param array{key: string, dir: string, type: string} $sort
	 */
	private function compareGridRows(array $a, array $b, array $sort): int {
		$key = $sort['key'];
		$aValue = $a[$key] ?? '';
		$bValue = $b[$key] ?? '';

		if (is_bool($aValue)) {
			$aValue = $aValue ? 1 : 0;
		}

		if (is_bool($bValue)) {
			$bValue = $bValue ? 1 : 0;
		}

		if (in_array($sort['type'], ['number', 'int', 'float', 'bool'], true)) {
			$result = ((float)$aValue <=> (float)$bValue);
		}
		else {
			$result = strnatcasecmp((string)$aValue, (string)$bValue);
		}

		if ($result === 0) {
			$result = strnatcasecmp((string)($a['id'] ?? $a['manifest_id'] ?? $a['logical_table'] ?? $a['table_name'] ?? ''), (string)($b['id'] ?? $b['manifest_id'] ?? $b['logical_table'] ?? $b['table_name'] ?? ''));
		}

		return $sort['dir'] === 'desc' ? -$result : $result;
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

	private function markManualResult(MaterializationManifest $manifest, MaterializationRunResult $result): void {
		$stateStore = $this->getStateStore();
		if ($stateStore === null) {
			return;
		}

		$now = time();
		$stateStore->set($this->stateKey($manifest->id, 'last_attempt_at'), $now);
		$stateStore->set($this->stateKey($manifest->id, 'last_status'), $result->success ? 'success' : 'failed');
		$stateStore->set($this->stateKey($manifest->id, 'last_message'), $result->message);

		if ($result->rowCount !== null) {
			$stateStore->set($this->stateKey($manifest->id, 'last_row_count'), $result->rowCount);
		}

		if ($result->success) {
			$stateStore->set($this->stateKey($manifest->id, 'last_success_at'), $now);
			$stateStore->set($this->stateKey($manifest->id, 'last_success_date'), date('Y-m-d', $now));
		}

		$stateStore->flush();
	}

	private function getCurrentGeneration(MaterializationManifest $manifest): mixed {
		$registry = $this->getMaterializationRegistry();
		if ($registry === null) {
			return null;
		}

		return $registry->getCurrentGeneration($manifest->targetSchema, $manifest->logicalTable);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getManifestState(MaterializationManifest $manifest): array {
		$stateStore = $this->getStateStore();

		if ($stateStore === null) {
			return [
				'last_success_at' => null,
				'last_status' => '',
				'last_message' => '',
				'last_row_count' => null,
			];
		}

		return [
			'last_success_at' => (int)$stateStore->get($this->stateKey($manifest->id, 'last_success_at'), 0),
			'last_status' => (string)$stateStore->get($this->stateKey($manifest->id, 'last_status'), ''),
			'last_message' => (string)$stateStore->get($this->stateKey($manifest->id, 'last_message'), ''),
			'last_row_count' => $stateStore->get($this->stateKey($manifest->id, 'last_row_count'), null),
		];
	}

	private function isManifestDue(MaterializationManifest $manifest): bool {
		if (!$manifest->enabled) {
			return false;
		}

		$stateStore = $this->getStateStore();
		if ($stateStore === null) {
			return false;
		}

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
		$stateStore = $this->getStateStore();
		if ($stateStore === null) {
			return false;
		}

		$seconds = (int)($schedule['seconds'] ?? 300);
		$seconds = $seconds > 0 ? $seconds : 300;
		$lastSuccess = (int)$stateStore->get($this->stateKey($manifest->id, 'last_success_at'), 0);

		return $lastSuccess <= 0 || (time() - $lastSuccess) >= $seconds;
	}

	private function isDailyAfterDue(MaterializationManifest $manifest, array $schedule): bool {
		$stateStore = $this->getStateStore();
		if ($stateStore === null) {
			return false;
		}

		$time = trim((string)($schedule['time'] ?? '02:00'));
		if (preg_match('/^\d{2}:\d{2}$/', $time) !== 1) {
			$time = '02:00';
		}

		$today = date('Y-m-d');
		$lastSuccessDate = (string)$stateStore->get($this->stateKey($manifest->id, 'last_success_date'), '');

		return $lastSuccessDate !== $today && date('H:i') >= $time;
	}

	private function formatDueText(MaterializationManifest $manifest): string {
		if (!$manifest->enabled) {
			return 'disabled';
		}

		$schedule = $manifest->schedule;
		$policy = strtolower((string)($schedule['policy'] ?? 'interval'));

		if ($policy === 'manual') {
			return 'manual';
		}

		if ($policy === 'always') {
			return 'always due';
		}

		if ($this->isManifestDue($manifest)) {
			return 'due';
		}

		$state = $this->getManifestState($manifest);
		$lastSuccess = (int)($state['last_success_at'] ?? 0);

		if ($lastSuccess <= 0) {
			return 'pending first run';
		}

		if ($policy === 'daily_after') {
			return 'done today';
		}

		$seconds = (int)($schedule['seconds'] ?? 300);
		$next = $lastSuccess + ($seconds > 0 ? $seconds : 300);

		return 'next ' . $this->formatTimestamp($next);
	}

	private function formatSchedule(MaterializationManifest $manifest): string {
		$schedule = $manifest->schedule;
		$policy = strtolower((string)($schedule['policy'] ?? 'interval'));

		return match ($policy) {
			'always' => 'always',
			'manual' => 'manual',
			'daily_after' => 'daily after ' . (string)($schedule['time'] ?? '02:00'),
			default => 'every ' . (int)($schedule['seconds'] ?? 300) . ' sec'
		};
	}

	/**
	 * @return array<string, mixed>
	 */
	private function normalizeRegistryRow(array $row): array {
		return [
			'id' => (int)($row['id'] ?? 0),
			'schema_name' => (string)($row['schema_name'] ?? ''),
			'logical_table' => (string)($row['logical_table'] ?? ''),
			'physical_table' => (string)($row['physical_table'] ?? ''),
			'generation' => (string)($row['generation'] ?? ''),
			'row_count' => isset($row['row_count']) && $row['row_count'] !== null ? (int)$row['row_count'] : null,
			'status' => (string)($row['status'] ?? ''),
			'is_current' => (int)($row['is_current'] ?? 0),
			'published_at' => isset($row['published_at']) && $row['published_at'] !== null ? (int)$row['published_at'] : null,
			'published_text' => $this->formatTimestamp(isset($row['published_at']) ? (int)$row['published_at'] : null),
			'created_at' => isset($row['created_at']) && $row['created_at'] !== null ? (int)$row['created_at'] : null,
			'created_text' => $this->formatTimestamp(isset($row['created_at']) ? (int)$row['created_at'] : null),
			'meta' => $this->decodeJson((string)($row['meta_json'] ?? '')),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function normalizeRunRow(array $row): array {
		$startedAt = isset($row['started_at']) && $row['started_at'] !== null ? (int)$row['started_at'] : null;
		$finishedAt = isset($row['finished_at']) && $row['finished_at'] !== null ? (int)$row['finished_at'] : null;

		return [
			'id' => (int)($row['id'] ?? 0),
			'manifest_id' => (string)($row['manifest_id'] ?? ''),
			'schema_name' => (string)($row['schema_name'] ?? ''),
			'logical_table' => (string)($row['logical_table'] ?? ''),
			'physical_table' => (string)($row['physical_table'] ?? ''),
			'generation' => (string)($row['generation'] ?? ''),
			'mode' => (string)($row['mode'] ?? ''),
			'status' => (string)($row['status'] ?? ''),
			'message' => (string)($row['message'] ?? ''),
			'row_count' => isset($row['row_count']) && $row['row_count'] !== null ? (int)$row['row_count'] : null,
			'started_at' => $startedAt,
			'finished_at' => $finishedAt,
			'started_text' => $this->formatTimestamp($startedAt),
			'finished_text' => $this->formatTimestamp($finishedAt),
			'duration' => $startedAt !== null && $finishedAt !== null ? max(0, $finishedAt - $startedAt) : null,
			'meta' => $this->decodeJson((string)($row['meta_json'] ?? '')),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function formatRunResult(MaterializationRunResult $result): array {
		return [
			'manifestId' => $result->manifestId,
			'success' => $result->success,
			'message' => $result->message,
			'rowCount' => $result->rowCount,
			'physicalTable' => $result->generation?->physicalTable ?? '',
			'generation' => $result->generation?->generation ?? '',
		];
	}

	private function tableExists(string $tableName): bool {
		$this->database->connect();
		if (!$this->database->connected()) {
			return false;
		}

		$row = $this->database->singleQuery("SHOW TABLES LIKE '" . $this->database->escape($tableName) . "'");
		return !empty($row);
	}

	private function firstRowValue(array $row): string {
		foreach ($row as $value) {
			return trim((string)$value);
		}

		return '';
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

	private function getMaterializationService(): ?IMaterializationService {
		if (!$this->container->has(IMaterializationService::class)) {
			return null;
		}

		$service = $this->container->get(IMaterializationService::class);
		return $service instanceof IMaterializationService ? $service : null;
	}

	private function getStateStore(): ?IStateStore {
		if (!$this->container->has(IStateStore::class)) {
			return null;
		}

		$stateStore = $this->container->get(IStateStore::class);
		return $stateStore instanceof IStateStore ? $stateStore : null;
	}

	/**
	 * @param array<string, mixed> $payload
	 */
	private function readString(array $payload, string $key, string $default = ''): string {
		if (!isset($payload[$key]) || !is_scalar($payload[$key])) {
			return $default;
		}

		$value = trim((string)$payload[$key]);
		return $value !== '' ? $value : $default;
	}

	private function normalizeBuildMode(string $mode): string {
		$mode = strtolower(trim($mode));
		return in_array($mode, ['refresh', 'full', 'incremental'], true) ? $mode : 'refresh';
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildErrorResponse(string $message, string $mode): array {
		return [
			'ok' => false,
			'mode' => $mode,
			'error' => $message,
		];
	}

	private function formatTimestamp(?int $timestamp): string {
		if ($timestamp === null || $timestamp <= 0) {
			return '';
		}

		return date('Y-m-d H:i:s', $timestamp);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function decodeJson(string $json): array {
		$json = trim($json);
		if ($json === '') {
			return [];
		}

		$decoded = json_decode($json, true);
		return is_array($decoded) ? $decoded : [];
	}

	private function toLower(string $value): string {
		if (function_exists('mb_strtolower')) {
			return mb_strtolower($value);
		}

		return strtolower($value);
	}

	private function stateKey(string $manifestId, string $suffix): string {
		$manifestId = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $manifestId) ?? $manifestId;
		return self::STATE_PREFIX . $manifestId . '.' . $suffix;
	}
}
