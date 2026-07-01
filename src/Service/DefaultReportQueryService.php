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

namespace DataHawk\Service;

use Base3\Api\IContainer;
use Base3\Database\Api\IDatabase;
use ResourceFoundation\Api\IQueryCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use ResourceFoundation\Dto\TableMetadata;
use ResourceFoundation\Exception\AccessDeniedException;
use ResourceFoundation\Exception\QueryValidationException;

class DefaultReportQueryService implements IQueryService {

	private IDatabase $database;

	public function __construct(
		private IQuerySchemaProvider $schemaProvider,
		private IQueryCompiler $querycompiler,
		private IContainer $container
	) {
		$this->database = $container->get('database');
	}

	/**
	 * @return TableMetadata[]
	 */
	public function listTables(): array {
		return $this->schemaProvider->getSchema();
	}

	public function getTable(string $tableName): ?TableMetadata {
		return $this->schemaProvider->getTable($tableName);
	}

	/**
	 * @throws AccessDeniedException
	 * @throws QueryValidationException
	 */
	public function executeQuery(array $queryJson): QueryResult {
		if (($queryJson['type'] ?? null) === 'transaction') {
			return $this->executeTransaction($queryJson);
		}

		$sqlQuery = $this->querycompiler->compile($queryJson);

		$type = (string)($queryJson['type'] ?? 'select');
		$isWrite = $this->isWriteQueryType($type);

		$affectedRows = null;
		$insertId = null;

		try {
			$this->database->connect();

			if ($isWrite) {
				$this->database->nonQuery($sqlQuery->sql);

				if ($this->database->isError()) {
					throw new \RuntimeException($this->database->errorMessage());
				}

				$affectedRows = $this->database->affectedRows();
				if ($type === 'insert') {
					$insertId = $this->database->insertId();
				}

				return new QueryResult([], [], $sqlQuery->sql, false, $affectedRows, $insertId);
			}

			$rows = $this->database->multiQuery($sqlQuery->sql);
			$affectedRows = $this->database->affectedRows();

		} catch (\Throwable $e) {
			return new QueryResult([], [], $sqlQuery->sql . "\n\n❌ DB Error: " . $e->getMessage(), false, $affectedRows, $insertId);
		}

		// Integrity check (only for non-wildcard SELECT)
		if (count($rows) && empty($sqlQuery->isWildcardQuery)) {
			$compiledFields = $sqlQuery->fields;
			$firstRow = $rows[0] ?? [];
			$expectedKeys = array_map(fn($f) => $f['alias'] ?? $f['name'], $compiledFields);
			$actualKeys = array_keys($firstRow);

			if (count($expectedKeys) !== count($actualKeys) || array_diff($expectedKeys, $actualKeys)) {
				throw new \RuntimeException(
					"Query result column mismatch: expected [" . implode(', ', $expectedKeys) .
					"], got [" . implode(', ', $actualKeys) . "]"
				);
			}
		}

		$columns = [];
		$firstRow = $rows[0] ?? [];
		$compiledFields = $sqlQuery->fields;

		foreach (array_keys($firstRow) as $i => $colName) {
			$field = $compiledFields[$i] ?? null;
			$name = $field['alias'] ?? $field['name'] ?? $colName;
			$phpType = gettype($firstRow[$colName]);

			$columns[] = [
				'name' => $name,
				'type' => $phpType,
				'field' => $field['name'] ?? null,
				'alias' => $field['alias'] ?? null,
				'table' => $field['table'] ?? null,
				'sensitive' => $field['sensitive'] ?? false,
			];
		}

		$isSensitive = in_array(true, array_column($columns, 'sensitive'), true);

		return new QueryResult($columns, $rows, $sqlQuery->sql, $isSensitive, $affectedRows, $insertId);
	}

	private function isWriteQueryType(string $type): bool {
		return in_array($type, [
			'insert',
			'update',
			'delete',
			'create',
			'alter',
			'drop',
			'rename',
			'truncate'
		], true);
	}

	private function executeTransaction(array $queryJson): QueryResult {
		$queries = $queryJson['queries'] ?? null;
		if (!is_array($queries) || empty($queries)) {
			throw new QueryValidationException("Transaction query requires a non-empty 'queries' array.");
		}

		$this->database->connect();
		$this->database->beginTransaction();

		$lastResult = null;
		$totalAffected = 0;
		$lastInsertId = null;

		try {
			foreach ($queries as $subQuery) {
				if (!is_array($subQuery) || empty($subQuery['type'])) {
					throw new QueryValidationException("Each transaction subquery must be a query array with a 'type'.");
				}

				$result = $this->executeQuery($subQuery);

				$debugSql = $result->debugSql ?? '';
				if ($debugSql !== '' && str_contains($debugSql, '❌ DB Error:')) {
					throw new \RuntimeException("Transaction subquery failed:\n" . $debugSql);
				}

				$lastResult = $result;

				if (is_int($result->affectedRows)) {
					$totalAffected += $result->affectedRows;
				}

				if (($subQuery['type'] ?? null) === 'insert' && $result->insertId !== null) {
					$lastInsertId = $result->insertId;
				}
			}

			$this->database->commit();
		} catch (\Throwable $e) {
			$this->database->rollback();
			throw $e;
		}

		if ($lastResult === null) {
			return new QueryResult([], [], null, false, 0, null);
		}

		$lastResult->affectedRows = $totalAffected;
		$lastResult->insertId = $lastInsertId;

		return $lastResult;
	}

	public function listDomains(): array {
		$tables = $this->schemaProvider->getSchema();
		$domains = array_map(fn(TableMetadata $t) => $t->domain ?? '', $tables);
		return array_values(array_unique(array_filter($domains)));
	}

	public function listCategories(): array {
		$tables = $this->schemaProvider->getSchema();
		$categories = array_map(fn(TableMetadata $t) => $t->category ?? '', $tables);
		return array_values(array_unique(array_filter($categories)));
	}

	public function listTags(): array {
		$tables = $this->schemaProvider->getSchema();
		$allTags = [];

		foreach ($tables as $table) {
			$allTags = array_merge($allTags, $table->tags);
			foreach ($table->fields as $field) {
				$allTags = array_merge($allTags, $field->tags ?? []);
			}
		}

		return array_values(array_unique(array_filter($allTags)));
	}
}
