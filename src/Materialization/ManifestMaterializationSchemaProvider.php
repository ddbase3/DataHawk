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
use ResourceFoundation\Api\IMaterializationSchemaProvider;
use ResourceFoundation\Dto\FieldMetadata;
use ResourceFoundation\Dto\ForeignKeyReference;
use ResourceFoundation\Dto\JoinMetadata;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\TableMetadata;

class ManifestMaterializationSchemaProvider implements IMaterializationSchemaProvider {

	/** @var array<string, MaterializationManifest>|null */
	private ?array $manifests = null;

	/** @var array<string, array>|null */
	private ?array $manifestData = null;

	/** @var TableMetadata[]|null */
	private ?array $schema = null;

	/** @var array<string, bool> */
	private array $tableExistsCache = [];

	public function __construct(
		private readonly string $manifestDir,
		private readonly ?IDatabase $database = null
	) {}

	/**
	 * @return TableMetadata[]
	 */
	public function getSchema(): array {
		if ($this->schema !== null) {
			return $this->schema;
		}

		$schema = [];
		foreach ($this->getManifests() as $manifest) {
			$data = $this->getManifestData($manifest->id);
			$schema[] = $this->createTableMetadata($manifest, $data);
		}

		return $this->schema = $schema;
	}

	public function getTable(string $tableName): ?TableMetadata {
		[$schemaName, $localTableName] = $this->splitQualifiedTableName($tableName);

		foreach ($this->getSchema() as $table) {
			if ($schemaName !== null && $table->domain !== $schemaName) {
				continue;
			}

			if ($table->name === $localTableName) {
				return $table;
			}
		}

		return null;
	}

	public function getManifest(string $id): ?MaterializationManifest {
		$manifests = $this->loadManifests();
		if (isset($manifests[$id])) {
			return $manifests[$id];
		}

		foreach ($manifests as $manifest) {
			if ($manifest->logicalTable === $id) {
				return $manifest;
			}
		}

		return null;
	}

	/**
	 * @return MaterializationManifest[]
	 */
	public function getManifests(): array {
		return array_values($this->loadManifests());
	}

	/**
	 * @return array<string, MaterializationManifest>
	 */
	private function loadManifests(): array {
		if ($this->manifests !== null) {
			return $this->manifests;
		}

		$manifestDir = rtrim($this->manifestDir, DIRECTORY_SEPARATOR . '/\\');
		$files = is_dir($manifestDir) ? (glob($manifestDir . DIRECTORY_SEPARATOR . '*.json') ?: []) : [];
		sort($files);

		$manifests = [];
		$manifestData = [];
		$manifestPriorities = [];

		foreach ($files as $file) {
			$data = $this->loadManifestData($file);
			if (!$this->isManifestDataActive($data)) {
				continue;
			}

			$data = $this->applyConditionalQueryParts($data);
			$manifest = MaterializationManifest::fromArray($data);
			$this->validateManifest($manifest, $file);

			$variantPriority = $this->getVariantPriority($data);
			$currentPriority = $manifestPriorities[$manifest->id] ?? PHP_INT_MIN;
			if (isset($manifests[$manifest->id]) && $variantPriority < $currentPriority) {
				continue;
			}

			$manifests[$manifest->id] = $manifest;
			$manifestData[$manifest->id] = $data;
			$manifestPriorities[$manifest->id] = $variantPriority;
		}

		$this->manifestData = $manifestData;
		return $this->manifests = $manifests;
	}

	private function loadManifestData(string $file): array {
		$json = file_get_contents($file);
		if ($json === false) {
			throw new \RuntimeException('Unable to read materialization manifest: ' . $file);
		}

		$data = json_decode($json, true);
		if (!is_array($data)) {
			throw new \RuntimeException('Invalid materialization manifest JSON: ' . $file);
		}

		return $data;
	}

	private function isManifestDataActive(array $data): bool {
		if (array_key_exists('enabled', $data) && !$this->normalizeBoolean($data['enabled'], true)) {
			return false;
		}

		return $this->conditionsMatch($this->getConditions($data));
	}

	private function conditionsMatch(array $conditions): bool {
		if ($conditions === []) {
			return true;
		}

		$requiresTables = $this->normalizeStringList($conditions['requiresTables'] ?? $conditions['requires_tables'] ?? []);
		foreach ($requiresTables as $table) {
			if (!$this->tableExists($table)) {
				return false;
			}
		}

		$missingTables = $this->normalizeStringList($conditions['missingTables'] ?? $conditions['missing_tables'] ?? []);
		foreach ($missingTables as $table) {
			if ($this->tableExists($table)) {
				return false;
			}
		}

		return true;
	}

	private function getVariantPriority(array $data): int {
		$conditions = $this->getConditions($data);
		return (int)($data['variantPriority'] ?? $data['variant_priority'] ?? $conditions['priority'] ?? 0);
	}

	private function getConditions(array $data): array {
		$conditions = $data['conditions'] ?? $data['condition'] ?? [];
		if (is_array($conditions) && $conditions !== []) {
			return $conditions;
		}

		if (isset($data['requiresTables']) || isset($data['requires_tables']) || isset($data['missingTables']) || isset($data['missing_tables'])) {
			return $data;
		}

		return [];
	}

	private function applyConditionalQueryParts(array $data): array {
		$conditionalWhere = $this->normalizeConditionalEntries($data['conditionalWhere'] ?? $data['conditional_where'] ?? []);
		unset($data['conditionalWhere'], $data['conditional_where']);

		if ($conditionalWhere === []) {
			return $data;
		}

		foreach ($conditionalWhere as $entry) {
			if (!is_array($entry) || !$this->conditionsMatch($this->getConditions($entry))) {
				continue;
			}

			$where = $entry['where'] ?? $entry['filter'] ?? null;
			if (is_array($where)) {
				$query = is_array($data['query'] ?? null) ? $data['query'] : [];
				$query['where'] = $this->mergeWhere($query['where'] ?? null, $where);
				$data['query'] = $query;
			}

			$dependsOn = $this->normalizeStringList($entry['dependsOn'] ?? $entry['depends_on'] ?? []);
			if ($dependsOn !== []) {
				$data['dependsOn'] = array_values(array_unique(array_merge(
					$this->normalizeStringList($data['dependsOn'] ?? $data['depends_on'] ?? []),
					$dependsOn
				)));
			}
		}

		return $data;
	}

	private function mergeWhere(mixed $existingWhere, array $additionalWhere): array {
		if (!is_array($existingWhere) || $existingWhere === []) {
			return $additionalWhere;
		}

		return [
			'type' => 'op',
			'operator' => 'AND',
			'params' => [
				$existingWhere,
				$additionalWhere
			]
		];
	}

	/**
	 * @return array<int, array>
	 */
	private function normalizeConditionalEntries(mixed $value): array {
		if (!is_array($value) || $value === []) {
			return [];
		}

		if (!$this->isList($value)) {
			return [$value];
		}

		$result = [];
		foreach ($value as $entry) {
			if (is_array($entry)) {
				$result[] = $entry;
			}
		}

		return $result;
	}

	private function normalizeBoolean(mixed $value, bool $default): bool {
		if (is_bool($value)) {
			return $value;
		}

		if (is_numeric($value)) {
			return (int)$value !== 0;
		}

		if (is_string($value)) {
			$value = strtolower(trim($value));
			if (in_array($value, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
				return true;
			}

			if (in_array($value, ['0', 'false', 'no', 'off', 'disabled'], true)) {
				return false;
			}
		}

		return $default;
	}

	private function isList(array $value): bool {
		return $value === [] || array_keys($value) === range(0, count($value) - 1);
	}

	/**
	 * @return string[]
	 */
	private function normalizeStringList(mixed $value): array {
		if (is_string($value)) {
			$value = explode(',', $value);
		}

		if (!is_array($value)) {
			return [];
		}

		$result = [];
		foreach ($value as $item) {
			$item = trim((string)$item);
			if ($item !== '') {
				$result[] = $item;
			}
		}

		return array_values(array_unique($result));
	}

	private function tableExists(string $tableName): bool {
		$tableName = trim($tableName);
		if ($tableName === '') {
			return false;
		}

		if (array_key_exists($tableName, $this->tableExistsCache)) {
			return $this->tableExistsCache[$tableName];
		}

		if ($this->database === null) {
			return $this->tableExistsCache[$tableName] = false;
		}

		try {
			$this->database->connect();
			if (!$this->database->connected()) {
				return $this->tableExistsCache[$tableName] = false;
			}

			$rows = $this->database->listQuery('SHOW TABLES LIKE ' . $this->quoteLiteral($this->escapeLike($tableName)));
			return $this->tableExistsCache[$tableName] = $rows !== [];
		} catch (\Throwable) {
			return $this->tableExistsCache[$tableName] = false;
		}
	}

	private function validateManifest(MaterializationManifest $manifest, string $file): void {
		if ($manifest->id === '') {
			throw new \RuntimeException('Materialization manifest must define a non-empty id: ' . $file);
		}

		if ($manifest->logicalTable === '') {
			throw new \RuntimeException('Materialization manifest must define target.logicalTable or logicalTable: ' . $file);
		}
	}

	private function getManifestData(string $id): array {
		$this->loadManifests();
		return $this->manifestData[$id] ?? [];
	}

	private function createTableMetadata(MaterializationManifest $manifest, array $data): TableMetadata {
		$table = is_array($data['table'] ?? null) ? $data['table'] : [];
		$target = is_array($data['target'] ?? null) ? $data['target'] : [];

		$tags = array_values(array_unique(array_merge(
			$data['tags'] ?? [],
			$table['tags'] ?? [],
			['materialized']
		)));

		return new TableMetadata(
			name: $manifest->logicalTable,
			label: $data['label'] ?? $table['label'] ?? $target['label'] ?? $manifest->logicalTable,
			description: $data['description'] ?? $table['description'] ?? null,
			domain: $manifest->targetSchema,
			category: $data['category'] ?? $table['category'] ?? 'materialized',
			tags: $tags,
			fields: $this->deserializeFields($manifest->columns !== [] ? $manifest->columns : ($data['fields'] ?? [])),
			joins: $this->deserializeJoins($data['joins'] ?? []),
			defaultFilters: $data['defaultFilters'] ?? $table['defaultFilters'] ?? [],
			sensitive: $data['sensitive'] ?? $table['sensitive'] ?? false,
			position: $data['position'] ?? $table['position'] ?? []
		);
	}

	/**
	 * @return FieldMetadata[]
	 */
	private function deserializeFields(array $fields): array {
		$result = [];

		foreach ($fields as $field) {
			if (!is_array($field)) {
				continue;
			}

			$name = (string)($field['name'] ?? '');
			if ($name === '') {
				throw new \RuntimeException('Materialization field must define a non-empty name.');
			}

			$required = (bool)($field['required'] ?? false);
			$nullable = array_key_exists('nullable', $field)
				? (bool)$field['nullable']
				: !$required;

			$result[] = new FieldMetadata(
				name: $name,
				type: (string)($field['type'] ?? 'string'),
				description: $field['description'] ?? $field['label'] ?? null,
				primaryKey: (bool)($field['primaryKey'] ?? false),
				foreignKey: isset($field['foreignKey']) && is_array($field['foreignKey'])
					? new ForeignKeyReference(
						table: (string)$field['foreignKey']['table'],
						column: (string)$field['foreignKey']['column']
					)
					: null,
				nullable: $nullable,
				tags: $field['tags'] ?? [],
				alias: $field['alias'] ?? null,
				sensitive: (bool)($field['sensitive'] ?? false)
			);
		}

		return $result;
	}

	/**
	 * @return JoinMetadata[]
	 */
	private function deserializeJoins(array $joins): array {
		$result = [];

		foreach ($joins as $join) {
			if (!is_array($join)) {
				continue;
			}

			$targetTable = (string)($join['targetTable'] ?? '');
			if ($targetTable === '') {
				throw new \RuntimeException('Materialization join must define a non-empty targetTable.');
			}

			$result[] = new JoinMetadata(
				targetTable: $targetTable,
				on: $join['on'] ?? [],
				type: $join['type'] ?? 'LEFT',
				meta: $join['meta'] ?? []
			);
		}

		return $result;
	}

	private function quoteLiteral(string $value): string {
		if ($this->database === null) {
			return "'" . str_replace("'", "''", $value) . "'";
		}

		return "'" . $this->database->escape($value) . "'";
	}

	private function escapeLike(string $value): string {
		return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
	}

	private function splitQualifiedTableName(string $tableName): array {
		foreach ([':', '.'] as $separator) {
			if (!str_contains($tableName, $separator)) {
				continue;
			}

			[$schemaName, $localTableName] = explode($separator, $tableName, 2);
			if ($schemaName !== '' && $localTableName !== '') {
				return [$schemaName, $localTableName];
			}
		}

		return [null, $tableName];
	}
}
