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

namespace DataHawk\Schema;

use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\FieldMetadata;
use ResourceFoundation\Dto\JoinMetadata;
use ResourceFoundation\Dto\TableMetadata;

class FileQuerySchemaProvider implements IQuerySchemaProvider {

	/** @var TableMetadata[]|null */
	private ?array $schema = null;

	public function __construct(
		private readonly string $schemaDir
	) {}

	/**
	 * @return TableMetadata[]
	 */
	public function getSchema(): array {
		if($this->schema !== null) {
			return $this->schema;
		}

		$tables = [];
		$schemaDir = $this->getSchemaDir();
		if($schemaDir === '' || !is_dir($schemaDir)) {
			return $this->schema = [];
		}

		$files = glob($schemaDir . DIRECTORY_SEPARATOR . '*.json') ?: [];

		foreach($files as $file) {
			$tables[] = $this->loadTableFromFile($file);
		}

		return $this->schema = $tables;
	}

	public function getTable(string $tableName): ?TableMetadata {
		foreach($this->getSchema() as $table) {
			if($table->name === $tableName) {
				return $table;
			}
		}

		return null;
	}

	private function getSchemaDir(): string {
		return rtrim($this->schemaDir, DIRECTORY_SEPARATOR . '/\\');
	}

	private function loadTableFromFile(string $file): TableMetadata {
		$json = file_get_contents($file);
		if($json === false) {
			throw new \RuntimeException('Unable to read schema file: ' . $file);
		}

		$data = json_decode($json, true);
		if(!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException('Invalid schema JSON in ' . $file . ': ' . json_last_error_msg());
		}

		if(empty($data['name']) || !is_string($data['name'])) {
			throw new \RuntimeException('Schema file must define a non-empty table name: ' . $file);
		}

		return new TableMetadata(
			name: $data['name'],
			label: $data['label'] ?? $data['name'],
			description: $data['description'] ?? null,
			domain: $data['domain'] ?? '',
			category: $data['category'] ?? '',
			tags: $data['tags'] ?? [],
			fields: $this->deserializeFields($data['fields'] ?? []),
			joins: $this->deserializeJoins($data['joins'] ?? []),
			defaultFilters: $data['defaultFilters'] ?? [],
			sensitive: $data['sensitive'] ?? false,
			position: $data['position'] ?? []
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $fields
	 * @return FieldMetadata[]
	 */
	private function deserializeFields(array $fields): array {
		return array_map(function(array $field): FieldMetadata {
			if(empty($field['name']) || !is_string($field['name'])) {
				throw new \RuntimeException('Schema field must define a non-empty name.');
			}

			if(isset($field['foreignKey'])) {
				throw new \RuntimeException('Field-level foreignKey metadata is not available in the current ResourceFoundation API; define relations through table joins.');
			}

			$required = (bool) ($field['required'] ?? false);
			$nullable = array_key_exists('nullable', $field)
				? (bool) $field['nullable']
				: !$required;

			return new FieldMetadata(
				name: $field['name'],
				type: $field['type'] ?? 'string',
				description: $field['description'] ?? $field['label'] ?? null,
				primaryKey: $field['primaryKey'] ?? false,
				foreignKey: null,
				nullable: $nullable,
				tags: $field['tags'] ?? [],
				alias: $field['alias'] ?? null,
				sensitive: $field['sensitive'] ?? false
			);
		}, $fields);
	}

	/**
	 * @param array<int, array<string, mixed>> $joins
	 * @return JoinMetadata[]
	 */
	private function deserializeJoins(array $joins): array {
		return array_map(fn(array $join) => new JoinMetadata(
			targetTable: $join['targetTable'],
			on: $join['on'] ?? [],
			type: $join['type'] ?? 'LEFT',
			meta: $join['meta'] ?? []
		), $joins);
	}
}
