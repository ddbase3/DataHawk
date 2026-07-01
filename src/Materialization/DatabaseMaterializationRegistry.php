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
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\IMaterializationRunRepository;
use ResourceFoundation\Dto\MaterializationGeneration;
use ResourceFoundation\Dto\MaterializationManifest;
use RuntimeException;

class DatabaseMaterializationRegistry implements IMaterializationRegistry, IMaterializationRunRepository {

	private const REGISTRY_TABLE = 'base3_mat_registry';
	private const RUN_TABLE = 'base3_mat_run';

	private bool $initialized = false;

	public function __construct(
		private readonly IDatabase $database
	) {}

	public function getCurrentGeneration(string $schema, string $logicalTable): ?MaterializationGeneration {
		$this->ensureTables();

		$where = $this->buildLogicalTableWhere($schema, $logicalTable) . ' AND is_current = 1';
		$row = $this->database->singleQuery(
			'SELECT * FROM ' . $this->quoteIdentifier(self::REGISTRY_TABLE) .
			' WHERE ' . $where .
			' ORDER BY published_at DESC, id DESC LIMIT 1'
		);

		if ($row === null) {
			return null;
		}

		return $this->rowToGeneration($row);
	}

	public function publishGeneration(MaterializationGeneration $generation): void {
		$this->ensureTables();

		$publishedAt = $generation->publishedAt ?? time();
		$status = $generation->status !== '' ? $generation->status : 'published';
		$metaJson = $this->encodeJson($generation->meta);

		$where = $this->buildLogicalTableWhere($generation->schema, $generation->logicalTable);
		$this->database->nonQuery(
			'UPDATE ' . $this->quoteIdentifier(self::REGISTRY_TABLE) .
			' SET is_current = 0 WHERE ' . $where
		);

		$this->database->nonQuery(
			'INSERT INTO ' . $this->quoteIdentifier(self::REGISTRY_TABLE) . ' (' .
				'schema_name, logical_table, physical_table, generation, schema_hash, query_hash, row_count, status, is_current, published_at, created_at, meta_json' .
			') VALUES (' .
				$this->quoteLiteral($generation->schema) . ', ' .
				$this->quoteLiteral($generation->logicalTable) . ', ' .
				$this->quoteLiteral($generation->physicalTable) . ', ' .
				$this->quoteLiteral($generation->generation) . ', ' .
				$this->quoteLiteral($generation->schemaHash) . ', ' .
				$this->quoteLiteral($generation->queryHash) . ', ' .
				$this->quoteNullableInt($generation->rowCount) . ', ' .
				$this->quoteLiteral($status) . ', ' .
				'1, ' .
				(int)$publishedAt . ', ' .
				time() . ', ' .
				$this->quoteLiteral($metaJson) .
			')'
		);
	}

	public function startRun(
		MaterializationManifest $manifest,
		string $physicalTable,
		string $generation,
		string $mode,
		array $meta = []
	): int {
		$this->ensureTables();
		$this->markOpenRunsAsFailed($manifest);

		$metaJson = $this->encodeJson($meta);
		$this->database->nonQuery(
			'INSERT INTO ' . $this->quoteIdentifier(self::RUN_TABLE) . ' (' .
				'manifest_id, schema_name, logical_table, physical_table, generation, mode, status, message, row_count, started_at, finished_at, meta_json' .
			') VALUES (' .
				$this->quoteLiteral($manifest->id) . ', ' .
				$this->quoteLiteral($manifest->targetSchema) . ', ' .
				$this->quoteLiteral($manifest->logicalTable) . ', ' .
				$this->quoteLiteral($physicalTable) . ', ' .
				$this->quoteLiteral($generation) . ', ' .
				$this->quoteLiteral($mode) . ', ' .
				$this->quoteLiteral('running') . ', ' .
				'NULL, ' .
				'NULL, ' .
				time() . ', ' .
				'NULL, ' .
				$this->quoteLiteral($metaJson) .
			')'
		);

		return $this->resolveInsertedRunId($manifest, $physicalTable, $generation);
	}

	public function finishRun(
		int $runId,
		bool $success,
		string $message,
		?int $rowCount = null,
		array $meta = []
	): void {
		$this->ensureTables();

		$status = $success ? 'success' : 'failed';
		$metaJson = $this->encodeJson($meta);
		$this->database->nonQuery(
			'UPDATE ' . $this->quoteIdentifier(self::RUN_TABLE) .
			' SET status = ' . $this->quoteLiteral($status) .
			', message = ' . $this->quoteLiteral($message) .
			', row_count = ' . $this->quoteNullableInt($rowCount) .
			', finished_at = ' . time() .
			', meta_json = ' . $this->quoteLiteral($metaJson) .
			' WHERE id = ' . (int)$runId
		);
	}

	/**
	 * @return MaterializationGeneration[]
	 */
	public function listGenerations(string $schema, string $logicalTable): array {
		$this->ensureTables();

		$rows = $this->database->multiQuery(
			'SELECT * FROM ' . $this->quoteIdentifier(self::REGISTRY_TABLE) .
			' WHERE ' . $this->buildLogicalTableWhere($schema, $logicalTable) .
			' ORDER BY published_at DESC, id DESC'
		);

		$generations = [];
		foreach ($rows as $row) {
			$generations[] = $this->rowToGeneration($row);
		}

		return $generations;
	}

	public function ensureTables(): void {
		if ($this->initialized) {
			return;
		}

		$this->database->connect();

		if (!$this->database->connected()) {
			throw new RuntimeException('DataHawk materialization registry requires an active database connection.');
		}

		$this->database->nonQuery(
			'CREATE TABLE IF NOT EXISTS ' . $this->quoteIdentifier(self::REGISTRY_TABLE) . ' (' .
				'id INT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
				'schema_name VARCHAR(128) NOT NULL DEFAULT \'\', ' .
				'logical_table VARCHAR(191) NOT NULL, ' .
				'physical_table VARCHAR(191) NOT NULL, ' .
				'generation VARCHAR(64) NOT NULL, ' .
				'schema_hash CHAR(64) NOT NULL DEFAULT \'\', ' .
				'query_hash CHAR(64) NOT NULL DEFAULT \'\', ' .
				'row_count BIGINT NULL, ' .
				'status VARCHAR(32) NOT NULL DEFAULT \'published\', ' .
				'is_current TINYINT(1) NOT NULL DEFAULT 0, ' .
				'published_at INT UNSIGNED NULL, ' .
				'created_at INT UNSIGNED NOT NULL, ' .
				'meta_json MEDIUMTEXT NULL, ' .
				'PRIMARY KEY (id), ' .
				'KEY idx_base3_mat_registry_current (schema_name, logical_table, is_current), ' .
				'KEY idx_base3_mat_registry_generation (schema_name, logical_table, generation), ' .
				'KEY idx_base3_mat_registry_physical (physical_table)' .
			') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
		);

		$this->database->nonQuery(
			'CREATE TABLE IF NOT EXISTS ' . $this->quoteIdentifier(self::RUN_TABLE) . ' (' .
				'id INT UNSIGNED NOT NULL AUTO_INCREMENT, ' .
				'manifest_id VARCHAR(191) NOT NULL, ' .
				'schema_name VARCHAR(128) NOT NULL DEFAULT \'\', ' .
				'logical_table VARCHAR(191) NOT NULL DEFAULT \'\', ' .
				'physical_table VARCHAR(191) NOT NULL DEFAULT \'\', ' .
				'generation VARCHAR(64) NOT NULL DEFAULT \'\', ' .
				'mode VARCHAR(32) NOT NULL DEFAULT \'\', ' .
				'status VARCHAR(32) NOT NULL DEFAULT \'running\', ' .
				'message TEXT NULL, ' .
				'row_count BIGINT NULL, ' .
				'started_at INT UNSIGNED NOT NULL, ' .
				'finished_at INT UNSIGNED NULL, ' .
				'meta_json MEDIUMTEXT NULL, ' .
				'PRIMARY KEY (id), ' .
				'KEY idx_base3_mat_run_manifest (manifest_id, started_at), ' .
				'KEY idx_base3_mat_run_logical (schema_name, logical_table, started_at)' .
			') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
		);

		$this->initialized = true;
	}

	private function markOpenRunsAsFailed(MaterializationManifest $manifest): void {
		$this->database->nonQuery(
			'UPDATE ' . $this->quoteIdentifier(self::RUN_TABLE) .
			' SET status = ' . $this->quoteLiteral('failed') .
			', message = ' . $this->quoteLiteral('Superseded by a newer materialization run.') .
			', finished_at = ' . time() .
			' WHERE manifest_id = ' . $this->quoteLiteral($manifest->id) .
			' AND schema_name = ' . $this->quoteLiteral($manifest->targetSchema) .
			' AND logical_table = ' . $this->quoteLiteral($manifest->logicalTable) .
			' AND status = ' . $this->quoteLiteral('running')
		);
	}

	private function resolveInsertedRunId(MaterializationManifest $manifest, string $physicalTable, string $generation): int {
		$insertId = $this->database->insertId();
		if (is_numeric($insertId) && (int)$insertId > 0) {
			return (int)$insertId;
		}

		$row = $this->database->singleQuery(
			'SELECT id FROM ' . $this->quoteIdentifier(self::RUN_TABLE) .
			' WHERE manifest_id = ' . $this->quoteLiteral($manifest->id) .
			' AND physical_table = ' . $this->quoteLiteral($physicalTable) .
			' AND generation = ' . $this->quoteLiteral($generation) .
			' ORDER BY id DESC LIMIT 1'
		);

		return (int)($row['id'] ?? 0);
	}

	private function buildLogicalTableWhere(string $schema, string $logicalTable): string {
		$where = 'logical_table = ' . $this->quoteLiteral($logicalTable);
		if ($schema !== '') {
			$where .= ' AND schema_name = ' . $this->quoteLiteral($schema);
		}

		return $where;
	}

	private function rowToGeneration(array $row): MaterializationGeneration {
		$meta = [];
		$metaJson = (string)($row['meta_json'] ?? '');
		if ($metaJson !== '') {
			$decoded = json_decode($metaJson, true);
			if (is_array($decoded)) {
				$meta = $decoded;
			}
		}

		return new MaterializationGeneration(
			schema: (string)($row['schema_name'] ?? ''),
			logicalTable: (string)($row['logical_table'] ?? ''),
			physicalTable: (string)($row['physical_table'] ?? ''),
			generation: (string)($row['generation'] ?? ''),
			schemaHash: (string)($row['schema_hash'] ?? ''),
			queryHash: (string)($row['query_hash'] ?? ''),
			rowCount: isset($row['row_count']) && $row['row_count'] !== null ? (int)$row['row_count'] : null,
			status: (string)($row['status'] ?? 'published'),
			publishedAt: isset($row['published_at']) && $row['published_at'] !== null ? (int)$row['published_at'] : null,
			meta: $meta
		);
	}

	private function encodeJson(array $data): string {
		$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		return $json === false ? '{}' : $json;
	}

	private function quoteIdentifier(string $identifier): string {
		return '`' . str_replace('`', '``', $identifier) . '`';
	}

	private function quoteLiteral(string $value): string {
		return "'" . $this->database->escape($value) . "'";
	}

	private function quoteNullableInt(?int $value): string {
		return $value === null ? 'NULL' : (string)$value;
	}
}
