<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of DataHawk for BASE3 Framework.
 *
 * DataHawk extends the BASE3 framework with a schema-driven query
 * engine for reporting and data access.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/datahawk
 * https://github.com/ddbase3/DataHawk
 **********************************************************************/

namespace DataHawk\Schema;

use ResourceFoundation\Api\IQuerySchemaDefinitionProvider;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\FieldMetadata;
use ResourceFoundation\Dto\JoinMetadata;
use ResourceFoundation\Dto\TableMetadata;

final class DefinitionQuerySchemaProvider implements IQuerySchemaProvider {

	/** @var TableMetadata[]|null */
	private ?array $schema = null;

	public function __construct(
		private readonly IQuerySchemaDefinitionProvider $definitionProvider
	) {}

	public function getSchema(): array {
		if($this->schema !== null) {
			return $this->schema;
		}

		$tables = [];
		$settings = $this->definitionProvider->getDefinitions();
		ksort($settings);

		foreach($settings as $name => $dataset) {
			if(!is_array($dataset) || !$this->isEnabled($dataset)) {
				continue;
			}

			$definition = $dataset['definition'] ?? null;
			if(!is_array($definition)) {
				throw new \RuntimeException('Query-schema definition dataset must contain a definition array: ' . (string)$name);
			}

			$tables[] = $this->deserializeTable($definition, (string)$name);
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

	private function deserializeTable(array $data, string $name): TableMetadata {
		if(empty($data['name']) || !is_string($data['name'])) {
			throw new \RuntimeException('Query-schema definition must define a non-empty table name: ' . $name);
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
	 * @param array<int,array<string,mixed>> $fields
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

			$required = (bool)($field['required'] ?? false);
			$nullable = array_key_exists('nullable', $field)
				? (bool)$field['nullable']
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
	 * @param array<int,array<string,mixed>> $joins
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

	private function isEnabled(array $dataset): bool {
		if(!array_key_exists('enabled', $dataset)) {
			return true;
		}

		return $this->normalizeBoolean($dataset['enabled'], true);
	}

	private function normalizeBoolean(mixed $value, bool $default): bool {
		if(is_bool($value)) {
			return $value;
		}

		if(is_numeric($value)) {
			return (int)$value !== 0;
		}

		if(is_string($value)) {
			$value = strtolower(trim($value));
			if(in_array($value, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
				return true;
			}

			if(in_array($value, ['0', 'false', 'no', 'off', 'disabled'], true)) {
				return false;
			}
		}

		return $default;
	}
}
