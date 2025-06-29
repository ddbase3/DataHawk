<?php declare(strict_types=1);

namespace DataHawk\Schema;

use Base3\Configuration\Api\IConfiguration;
use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Dto\TableMetadata;
use DataHawk\Dto\FieldMetadata;
use DataHawk\Dto\JoinMetadata;
use DataHawk\Dto\ForeignKeyReference;

class DefaultReportSchemaProvider implements IReportSchemaProvider {

	private string $schemaDir;

	/** @var TableMetadata[]|null */
	private ?array $cachedSchema = null;

	public function __construct(private readonly IConfiguration $configuration) {
		$this->schemaDir = $this->getDataDir();
	}

	/**
	 * Returns all defined tables.
	 *
	 * @return TableMetadata[]
	 */
	public function getSchema(): array {
		if ($this->cachedSchema !== null) {
			return $this->cachedSchema;
		}

		$tables = [];

		foreach (glob($this->schemaDir . '/*.json') as $file) {
			$json = file_get_contents($file);
			$data = json_decode($json, true);
			if (!is_array($data)) continue;

			$tables[] = $this->deserializeTableMetadata($data);
		}

		return $this->cachedSchema = $tables;
	}

	/**
	 * Returns a specific table by name, or null if not found.
	 */
	public function getTable(string $tableName): ?TableMetadata {
		foreach ($this->getSchema() as $table) {
			if ($table->name === $tableName) {
				return $table;
			}
		}
		return null;
	}

	/**
	 * Constructs a TableMetadata DTO from array.
	 */
	private function deserializeTableMetadata(array $data): TableMetadata {
		$fields = array_map(function ($f) {
			return new FieldMetadata(
				name: $f['name'],
				type: $f['type'],
				description: $f['description'] ?? null,
				primaryKey: $f['primaryKey'] ?? false,
				foreignKey: isset($f['foreignKey'])
					? new ForeignKeyReference(
						table: $f['foreignKey']['table'],
						column: $f['foreignKey']['column']
					)
					: null,
				nullable: $f['nullable'] ?? true,
				tags: $f['tags'] ?? [],
				alias: $f['alias'] ?? null,
				sensitive: $f['sensitive'] ?? false
			);
		}, $data['fields'] ?? []);

		$joins = array_map(function ($j) {
			return new JoinMetadata(
				targetTable: $j['targetTable'],
				on: $j['on'],
				type: $j['type'] ?? 'INNER',
				meta: $j['meta'] ?? []
			);
		}, $data['joins'] ?? []);

		return new TableMetadata(
			name: $data['name'] ?? '',
			label: $data['label'] ?? null,
			description: $data['description'] ?? null,
			domain: $data['domain'] ?? '',
			category: $data['category'] ?? '',
			tags: $data['tags'] ?? [],
			fields: $fields,
			joins: $joins,
			defaultFilters: $data['defaultFilters'] ?? [],
			sensitive: $data['sensitive'] ?? false
		);
	}

	/**
	 * Resolves configured directory for schema files.
	 */
	protected function getDataDir(): string {
		$directories = $this->configuration->get('directories');
		return isset($directories['data'])
			? rtrim($directories['data'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'datahawk' . DIRECTORY_SEPARATOR
			: '';
	}
}

