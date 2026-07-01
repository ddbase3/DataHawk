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

use Base3\Database\Api\IDatabase;
use ResourceFoundation\Api\IMaterializationManifestProvider;
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\IMaterializationRunRepository;
use ResourceFoundation\Api\IMaterializationService;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\MaterializationGeneration;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\MaterializationRunResult;
use ResourceFoundation\Dto\QueryResult;
use ResourceFoundation\Exception\MaterializationException;

class DefaultMaterializationService implements IMaterializationService {

	private const DEFAULT_KEEP_GENERATIONS = 2;

	public function __construct(
		private readonly IMaterializationManifestProvider $manifestProvider,
		private readonly IQueryService $queryService,
		private readonly IMaterializationRegistry $registry,
		private readonly MaterializationPhysicalTableNameGenerator $physicalTableNameGenerator,
		private readonly ?IMaterializationRunRepository $runRepository = null,
		private readonly ?IDatabase $database = null
	) {}

	public function buildFull(string $manifestId): MaterializationRunResult {
		$manifest = $this->getRequiredManifest($manifestId);
		$this->validateFullBuildManifest($manifest);

		$generation = $this->createGenerationName();
		$physicalTable = $this->physicalTableNameGenerator->getGenerationTableName($manifest, $generation);

		$runId = $this->startRun($manifest, $physicalTable, $generation, 'full');

		try {
			$this->executeChecked($this->createTableQuery($manifest, $physicalTable));
			$insertResult = $this->executeChecked($this->createInsertQuery($manifest, $physicalTable));
			$this->createIndexes($manifest, $physicalTable);

			$rowCount = $this->countRows($physicalTable, $insertResult->affectedRows);
			$publishedGeneration = new MaterializationGeneration(
				schema: $manifest->targetSchema,
				logicalTable: $manifest->logicalTable,
				physicalTable: $physicalTable,
				generation: $generation,
				schemaHash: $manifest->getSchemaHash(),
				queryHash: $manifest->getQueryHash(),
				rowCount: $rowCount,
				status: 'published',
				publishedAt: time(),
				meta: [
					'manifestId' => $manifest->id,
					'mode' => 'full'
				]
			);

			$this->registry->publishGeneration($publishedGeneration);
			$cleanup = $this->cleanupOldPhysicalTables($manifest, $physicalTable);
			$meta = [
				'physicalTable' => $physicalTable,
				'buildMode' => 'new_table',
				'cleanup' => $cleanup
			];

			$this->finishRun($runId, true, 'Materialization full build completed.', $rowCount, $meta);

			return new MaterializationRunResult(
				manifestId: $manifest->id,
				success: true,
				message: 'Materialization full build completed.',
				generation: $publishedGeneration,
				rowCount: $rowCount,
				meta: $meta
			);
		} catch (\Throwable $e) {
			$dropFailedTable = $this->dropPhysicalTableIfExists($physicalTable);
			$meta = [
				'physicalTable' => $physicalTable,
				'buildMode' => 'new_table',
				'dropFailedTable' => $dropFailedTable
			];

			$this->finishRun($runId, false, $e->getMessage(), null, $meta);

			return new MaterializationRunResult(
				manifestId: $manifest->id,
				success: false,
				message: $e->getMessage(),
				generation: null,
				rowCount: null,
				meta: $meta
			);
		}
	}

	public function buildIncremental(string $manifestId): MaterializationRunResult {
		$manifest = $this->getRequiredManifest($manifestId);

		return new MaterializationRunResult(
			manifestId: $manifest->id,
			success: false,
			message: 'Incremental materialization is not implemented yet.',
			generation: null,
			rowCount: null,
			meta: [
				'mode' => 'incremental'
			]
		);
	}

	public function refresh(string $manifestId): MaterializationRunResult {
		$manifest = $this->getRequiredManifest($manifestId);
		$mode = (string)($manifest->refresh['mode'] ?? 'full');

		return match ($mode) {
			'incremental' => $this->buildIncremental($manifestId),
			default => $this->buildFull($manifestId)
		};
	}

	private function startRun(MaterializationManifest $manifest, string $physicalTable, string $generation, string $mode): ?int {
		if ($this->runRepository === null) {
			return null;
		}

		return $this->runRepository->startRun($manifest, $physicalTable, $generation, $mode, [
			'buildMode' => 'new_table'
		]);
	}

	private function finishRun(?int $runId, bool $success, string $message, ?int $rowCount, array $meta = []): void {
		if ($runId === null || $this->runRepository === null) {
			return;
		}

		$this->runRepository->finishRun($runId, $success, $message, $rowCount, $meta);
	}

	private function getRequiredManifest(string $manifestId): MaterializationManifest {
		$manifest = $this->manifestProvider->getManifest($manifestId);
		if ($manifest === null) {
			throw new MaterializationException('Unknown materialization manifest: ' . $manifestId);
		}

		return $manifest;
	}

	private function validateFullBuildManifest(MaterializationManifest $manifest): void {
		if ($manifest->id === '') {
			throw new MaterializationException('Materialization manifest id must not be empty.');
		}

		if ($manifest->logicalTable === '') {
			throw new MaterializationException('Materialization manifest must define a logical table.');
		}

		if ($manifest->query === []) {
			throw new MaterializationException('Materialization manifest must define a source query.');
		}

		if ($manifest->columns === []) {
			throw new MaterializationException('Materialization manifest must define target columns.');
		}
	}

	private function createTableQuery(MaterializationManifest $manifest, string $physicalTable): array {
		return [
			'type' => 'create',
			'table' => $physicalTable,
			'columns' => array_map(fn(array $column) => $this->createColumnDefinition($column), $manifest->columns)
		];
	}

	private function createColumnDefinition(array $column): array {
		$name = (string)($column['name'] ?? '');
		if ($name === '') {
			throw new MaterializationException('Materialization target column must define a non-empty name.');
		}

		$required = (bool)($column['required'] ?? false);
		$nullable = array_key_exists('nullable', $column)
			? (bool)$column['nullable']
			: !$required;

		$result = [
			'name' => $name,
			'type' => $this->mapColumnType($column),
			'nullable' => $nullable
		];

		if (array_key_exists('default', $column)) {
			$result['default'] = $column['default'];
		}

		if (!empty($column['primaryKey']) || !empty($column['primary_key'])) {
			$result['primary_key'] = true;
		}

		if (!empty($column['autoIncrement']) || !empty($column['auto_increment'])) {
			$result['auto_increment'] = true;
		}

		return $result;
	}

	private function mapColumnType(array $column): string {
		$type = strtolower((string)($column['type'] ?? 'string'));
		$length = isset($column['length']) ? (int)$column['length'] : null;
		$precision = isset($column['precision']) ? (int)$column['precision'] : 14;
		$scale = isset($column['scale']) ? (int)$column['scale'] : 4;

		if (str_contains($type, '(') || str_contains($type, ' ')) {
			return strtoupper($type);
		}

		return match ($type) {
			'int', 'integer' => 'INT',
			'bigint' => 'BIGINT',
			'smallint' => 'SMALLINT',
			'tinyint' => 'TINYINT',
			'bool', 'boolean' => 'TINYINT(1)',
			'float' => 'FLOAT',
			'double' => 'DOUBLE',
			'decimal' => 'DECIMAL(' . $precision . ',' . $scale . ')',
			'date' => 'DATE',
			'datetime' => 'DATETIME',
			'timestamp' => 'TIMESTAMP',
			'text' => 'TEXT',
			'longtext' => 'LONGTEXT',
			'json' => 'JSON',
			default => 'VARCHAR(' . ($length !== null && $length > 0 ? $length : 255) . ')'
		};
	}

	private function createInsertQuery(MaterializationManifest $manifest, string $physicalTable): array {
		$sourceQuery = $manifest->query;
		$sourceQuery['type'] = $sourceQuery['type'] ?? 'select';

		if ($manifest->sourceSchema !== '' && !isset($sourceQuery['schema'])) {
			$sourceQuery['schema'] = $manifest->sourceSchema;
		}

		return [
			'type' => 'insert',
			'table' => $physicalTable,
			'columns' => $this->getColumnNames($manifest),
			'from' => $sourceQuery
		];
	}

	private function getColumnNames(MaterializationManifest $manifest): array {
		$columns = [];
		foreach ($manifest->columns as $column) {
			if (!is_array($column)) {
				continue;
			}

			$name = (string)($column['name'] ?? '');
			if ($name !== '') {
				$columns[] = $name;
			}
		}

		return $columns;
	}

	private function createIndexes(MaterializationManifest $manifest, string $physicalTable): void {
		$actions = [];
		foreach ($manifest->indexes as $index => $definition) {
			$action = $this->createIndexAction($manifest, $index, $definition);
			if ($action !== null) {
				$actions[] = $action;
			}
		}

		if ($actions === []) {
			return;
		}

		$this->executeChecked([
			'type' => 'alter',
			'table' => $physicalTable,
			'actions' => $actions
		]);
	}

	private function createIndexAction(MaterializationManifest $manifest, int|string $index, mixed $definition): ?array {
		if (is_array($definition) && array_is_list($definition)) {
			$columns = array_values(array_map('strval', $definition));
			return [
				'action' => 'ADD_INDEX',
				'name' => $this->createIndexName($manifest, (string)$index, $columns),
				'columns' => $columns
			];
		}

		if (!is_array($definition)) {
			return null;
		}

		$columns = $definition['columns'] ?? $definition['fields'] ?? [];
		if (!is_array($columns) || $columns === []) {
			return null;
		}

		$columns = array_values(array_map('strval', $columns));
		$unique = (bool)($definition['unique'] ?? false);

		return [
			'action' => $unique ? 'ADD_UNIQUE_INDEX' : 'ADD_INDEX',
			'name' => (string)($definition['name'] ?? $this->createIndexName($manifest, (string)$index, $columns)),
			'columns' => $columns
		];
	}

	private function createIndexName(MaterializationManifest $manifest, string $index, array $columns): string {
		$base = $index !== '' && !ctype_digit($index)
			? $index
			: implode('_', $columns);

		$name = 'idx_' . $manifest->logicalTable . '_' . $base;
		$name = strtolower($name);
		$name = preg_replace('/[^a-z0-9_]+/', '_', $name) ?? '';
		$name = preg_replace('/_+/', '_', $name) ?? '';
		$name = trim($name, '_');

		return substr($name, 0, 64);
	}

	private function executeChecked(array $query): QueryResult {
		$result = $this->queryService->executeQuery($query);
		$debugSql = $result->debugSql ?? '';

		if ($debugSql !== '' && str_contains($debugSql, '❌ DB Error:')) {
			throw new MaterializationException($debugSql);
		}

		return $result;
	}

	private function countRows(string $physicalTable, int $fallback): int {
		if ($this->database === null) {
			return $fallback;
		}

		$this->assertSafePhysicalTable($physicalTable);
		$value = $this->database->scalarQuery('SELECT COUNT(*) FROM ' . $this->quoteIdentifier($physicalTable));

		return $value === null ? $fallback : (int)$value;
	}

	private function cleanupOldPhysicalTables(MaterializationManifest $manifest, string $currentPhysicalTable): array {
		if ($this->database === null) {
			return [
				'skipped' => 'database_not_available'
			];
		}

		try {
			$keepGenerations = $this->getKeepGenerations($manifest);
			$tables = $this->listPhysicalTables($manifest);
			rsort($tables, SORT_STRING);

			$keep = array_slice($tables, 0, $keepGenerations);
			if (!in_array($currentPhysicalTable, $keep, true)) {
				$keep[] = $currentPhysicalTable;
			}

			$dropped = [];
			foreach ($tables as $table) {
				if (in_array($table, $keep, true)) {
					continue;
				}

				if ($this->dropPhysicalTableIfExists($table)) {
					$dropped[] = $table;
				}
			}

			return [
				'keepGenerations' => $keepGenerations,
				'kept' => array_values($keep),
				'dropped' => $dropped
			];
		} catch (\Throwable $e) {
			return [
				'error' => $e->getMessage()
			];
		}
	}

	private function dropPhysicalTableIfExists(string $physicalTable): bool {
		if ($this->database === null) {
			return false;
		}

		try {
			$this->assertSafePhysicalTable($physicalTable);
			$this->database->nonQuery('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($physicalTable));
			return true;
		} catch (\Throwable) {
			return false;
		}
	}

	/**
	 * @return string[]
	 */
	private function listPhysicalTables(MaterializationManifest $manifest): array {
		$prefix = $this->getPhysicalPrefix($manifest);
		$this->assertSafePhysicalTable($prefix . '_placeholder');

		$like = $this->escapeLike($prefix . '_') . '%';
		$tables = $this->database->listQuery('SHOW TABLES LIKE ' . $this->quoteLiteral($like));

		$result = [];
		foreach ($tables as $table) {
			$table = (string)$table;
			if (str_starts_with($table, $prefix . '_') && $this->isSafePhysicalTable($table)) {
				$result[] = $table;
			}
		}

		return array_values(array_unique($result));
	}

	private function getKeepGenerations(MaterializationManifest $manifest): int {
		$value = $manifest->options['keepGenerations']
			?? $manifest->options['keep_generations']
			?? $manifest->refresh['keepGenerations']
			?? $manifest->refresh['keep_generations']
			?? self::DEFAULT_KEEP_GENERATIONS;

		$keepGenerations = (int)$value;
		return $keepGenerations > 0 ? $keepGenerations : self::DEFAULT_KEEP_GENERATIONS;
	}

	private function getPhysicalPrefix(MaterializationManifest $manifest): string {
		$prefix = $manifest->physicalPrefix !== ''
			? $manifest->physicalPrefix
			: 'base3_mat_' . $manifest->logicalTable;

		if (!str_starts_with($prefix, 'base3_mat_')) {
			throw new MaterializationException('Materialization cleanup is limited to base3_mat_* tables.');
		}

		return $prefix;
	}

	private function assertSafePhysicalTable(string $table): void {
		if (!$this->isSafePhysicalTable($table)) {
			throw new MaterializationException('Unsafe materialization physical table name: ' . $table);
		}
	}

	private function isSafePhysicalTable(string $table): bool {
		return str_starts_with($table, 'base3_mat_') && preg_match('/^[a-zA-Z0-9_]+$/', $table) === 1;
	}

	private function quoteIdentifier(string $identifier): string {
		return '`' . str_replace('`', '``', $identifier) . '`';
	}

	private function quoteLiteral(string $value): string {
		return "'" . $this->database->escape($value) . "'";
	}

	private function escapeLike(string $value): string {
		return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
	}

	private function createGenerationName(): string {
		try {
			$suffix = bin2hex(random_bytes(2));
		} catch (\Throwable) {
			$suffix = substr(str_replace('.', '', uniqid('', true)), -4);
		}

		return date('YmdHis') . '_' . $suffix;
	}
}
