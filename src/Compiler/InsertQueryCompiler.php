<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Compiles 'insert' type queries into SQL.
 *
 * Supports:
 * - INSERT INTO ... VALUES (...)
 * - INSERT IGNORE INTO ... VALUES (...)
 * - INSERT INTO ... SELECT ...
 * - INSERT IGNORE INTO ... SELECT ...
 * - optional ON DUPLICATE KEY UPDATE ...
 */
class InsertQueryCompiler implements IReportQueryTypeCompiler {

	private ElementCompiler $elementCompiler;

	/** Used to compile 'from' subqueries (SELECT inside INSERT) */
	private MysqlReportQueryCompiler $mainCompiler;

	public function __construct(IQuerySchemaProvider $schemaProvider, MysqlReportQueryCompiler $mainCompiler) {
		$this->mainCompiler = $mainCompiler;

		$aliasResolver = new AliasResolver();
		$this->elementCompiler = new ElementCompiler($aliasResolver, $this);
	}

	public function compile(array $query): QueryStatement {
		$table = $query['table'];
		$columns = $query['columns'] ?? null;
		$ignore = !empty($query['ignore']);

		$sql = ($ignore ? 'INSERT IGNORE INTO ' : 'INSERT INTO ') . $this->elementCompiler->quoteIdentifier($table);

		// Optional: column list
		if ($columns) {
			$colList = array_map(fn($c) => $this->elementCompiler->quoteIdentifier($c), $columns);
			$sql .= ' (' . implode(', ', $colList) . ')';
		}

		// INSERT ... VALUES
		if (isset($query['values'])) {
			$rows = $query['values'];
			if (!is_array($rows) || empty($rows)) {
				throw new QueryValidationException("'values' must be a non-empty array.");
			}

			$firstRow = $rows[0];
			$inferredColumns = array_keys($firstRow);

			if (!$columns) {
				$colList = array_map(fn($k) => $this->elementCompiler->quoteIdentifier($k), $inferredColumns);
				$sql .= ' (' . implode(', ', $colList) . ')';
			}

			$valueRows = [];
			foreach ($rows as $row) {
				$vals = [];
				foreach ($columns ?? $inferredColumns as $colName) {
					$val = $row[$colName] ?? null;
					$vals[] = $this->quoteValue($val);
				}
				$valueRows[] = '(' . implode(', ', $vals) . ')';
			}

			$sql .= ' VALUES ' . implode(', ', $valueRows);
		}

		// INSERT ... SELECT ...
		elseif (isset($query['from'])) {
			if (!is_array($query['from'])) {
				throw new QueryValidationException("'from' must be a valid SELECT query structure.");
			}
			$selectQueryStatement = $this->mainCompiler->compile($query['from']);
			$sql .= ' ' . $selectQueryStatement->sql;
		}

		if (!empty($query['on_duplicate'])) {
			$updateParts = [];
			foreach ($query['on_duplicate'] as $field => $val) {
				$fieldSql = $this->elementCompiler->quoteIdentifier($field);
				$exprSql = is_array($val) && isset($val['type'])
					? $this->elementCompiler->compileElement($val)
					: $this->quoteValue($val);
				$updateParts[] = $fieldSql . ' = ' . $exprSql;
			}
			$sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts);
		}

		return new QueryStatement($sql, [], [], false);
	}

	private function quoteValue(mixed $val): string {
		if (is_array($val) && isset($val['type'])) {
			return $this->elementCompiler->compileElement($val);
		}

		if (is_null($val)) return 'NULL';
		if (is_bool($val)) return $val ? 'TRUE' : 'FALSE';
		if (is_numeric($val)) return (string)$val;

		$escaped = str_replace("'", "''", (string)$val);
		return "'" . $escaped . "'";
	}
}
