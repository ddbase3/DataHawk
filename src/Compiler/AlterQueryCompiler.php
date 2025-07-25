<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use DataHawk\Dto\SqlQuery;

/**
 * Compiles ALTER TABLE queries to SQL.
 */
class AlterQueryCompiler implements IReportQueryTypeCompiler {

	public function compile(array $query): SqlQuery {
		$table = $query['table'];
		$actions = [];

		foreach ($query['actions'] as $action) {
			$cmd = strtoupper($action['action']);

			switch ($cmd) {
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
		return new SqlQuery($sql);
	}

	private function quoteIdentifier(string $str): string {
		return '`' . str_replace('`', '``', $str) . '`';
	}

	private function quoteLiteral(string|int|float|bool|null $value): string {
		if (is_null($value)) return 'NULL';
		if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
		return is_numeric($value) ? (string)$value : "'" . str_replace("'", "''", (string)$value) . "'";
	}
}

