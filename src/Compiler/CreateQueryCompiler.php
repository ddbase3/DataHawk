<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Compiles 'create' type queries into SQL.
 */
class CreateQueryCompiler implements IReportQueryTypeCompiler {

	private ElementCompiler $elementCompiler;

	public function __construct(IQuerySchemaProvider $schemaProvider) {
		$aliasResolver = new AliasResolver();
		$this->elementCompiler = new ElementCompiler($aliasResolver, $this);
	}

	public function compile(array $query): QueryStatement {
		$table = $query['table'];
		$columns = $query['columns'];

		if (!is_string($table) || trim($table) === '') {
			throw new QueryValidationException("CREATE query requires a valid table name.");
		}

		$columnSql = [];

		foreach ($columns as $column) {
			$name = $this->elementCompiler->quoteIdentifier($column['name']);
			$type = $column['type'];

			$parts = [$name, $type];

			if (($column['nullable'] ?? true) === false) {
				$parts[] = 'NOT NULL';
			}

			if (isset($column['default'])) {
				$default = $column['default'];
				if (is_string($default) && strtoupper($default) === 'CURRENT_TIMESTAMP') {
					$parts[] = 'DEFAULT CURRENT_TIMESTAMP';
				} elseif (is_numeric($default)) {
					$parts[] = 'DEFAULT ' . $default;
				} else {
					$parts[] = 'DEFAULT ' . $this->quoteLiteral($default);
				}
			}

			if (!empty($column['auto_increment'])) {
				$parts[] = 'AUTO_INCREMENT';
			}

			if (!empty($column['primary_key'])) {
				$parts[] = 'PRIMARY KEY';
			}

			$columnSql[] = implode(' ', $parts);
		}

		$sql = 'CREATE TABLE ' . $this->elementCompiler->quoteIdentifier($table) .
		       ' (' . implode(', ', $columnSql) . ')';

		return new QueryStatement($sql, [], [], false);
	}

	private function quoteLiteral(string $value): string {
		// einfache SQL-kompatible String-Escaping-Logik
		$escaped = str_replace("'", "''", $value);
		return "'" . $escaped . "'";
	}
}
