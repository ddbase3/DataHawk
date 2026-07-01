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

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use ResourceFoundation\Dto\QueryStatement;

/**
 * Compiles ALTER TABLE queries to SQL.
 */
class AlterQueryCompiler implements IReportQueryTypeCompiler {

	public function compile(array $query): QueryStatement {
		$table = $query['table'];
		$actions = [];

		foreach ($query['actions'] as $action) {
			$cmd = strtoupper($action['action']);

			switch ($cmd) {
				case 'ADD_INDEX': {
					$name = $this->quoteIdentifier($action['name']);
					$columns = $this->quoteIdentifierList($action['columns'] ?? []);
					$actions[] = "ADD INDEX $name ($columns)";
					break;
				}

				case 'ADD_UNIQUE_INDEX': {
					$name = $this->quoteIdentifier($action['name']);
					$columns = $this->quoteIdentifierList($action['columns'] ?? []);
					$actions[] = "ADD UNIQUE INDEX $name ($columns)";
					break;
				}

				case 'DROP_INDEX': {
					$name = $this->quoteIdentifier($action['name']);
					$actions[] = "DROP INDEX $name";
					break;
				}

				case 'ADD_COLUMN': {
					$col = $this->quoteIdentifier($action['name']);
					$type = $action['type'];
					$nullable = ($action['nullable'] ?? true) ? '' : ' NOT NULL';
					$default = isset($action['default']) ? ' DEFAULT ' . $this->quoteLiteral($action['default']) : '';
					$actions[] = "ADD COLUMN $col $type$nullable$default";
					break;
				}

				case 'DROP_COLUMN': {
					$col = $this->quoteIdentifier($action['name']);
					$actions[] = "DROP COLUMN $col";
					break;
				}

				case 'MODIFY_COLUMN': {
					$col = $this->quoteIdentifier($action['name']);
					$type = $action['type'];
					$nullable = ($action['nullable'] ?? true) ? '' : ' NOT NULL';
					$default = isset($action['default']) ? ' DEFAULT ' . $this->quoteLiteral($action['default']) : '';
					$actions[] = "MODIFY COLUMN $col $type$nullable$default";
					break;
				}

				case 'RENAME_COLUMN': {
					$from = $this->quoteIdentifier($action['from']);
					$to = $this->quoteIdentifier($action['to']);
					$actions[] = "RENAME COLUMN $from TO $to";
					break;
				}

				case 'CHANGE_COLUMN': {
					$from = $this->quoteIdentifier($action['from']);
					$to = $this->quoteIdentifier($action['to']);
					$type = $action['type'];
					$nullable = ($action['nullable'] ?? true) ? '' : ' NOT NULL';
					$default = isset($action['default']) ? ' DEFAULT ' . $this->quoteLiteral($action['default']) : '';
					$actions[] = "CHANGE COLUMN $from $to $type$nullable$default";
					break;
				}

				default:
					throw new \InvalidArgumentException("Unsupported ALTER action: $cmd");
			}
		}

		$sql = 'ALTER TABLE ' . $this->quoteIdentifier($table) . "\n  " . implode(",\n  ", $actions);
		return new QueryStatement($sql);
	}

	private function quoteIdentifier(string $str): string {
		return '`' . str_replace('`', '``', $str) . '`';
	}

	private function quoteIdentifierList(array $columns): string {
		if ($columns === []) {
			throw new \InvalidArgumentException('ALTER index action requires at least one column.');
		}

		return implode(', ', array_map(fn($column) => $this->quoteIdentifier((string)$column), $columns));
	}

	private function quoteLiteral(string|int|float|bool|null $value): string {
		if (is_null($value)) return 'NULL';
		if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
		return is_numeric($value) ? (string)$value : "'" . str_replace("'", "''", (string)$value) . "'";
	}
}
