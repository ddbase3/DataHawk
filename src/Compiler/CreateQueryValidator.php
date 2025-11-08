<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryValidator;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Validates 'create' type queries.
 *
 * Ensures that the table name and column definitions are valid.
 */
class CreateQueryValidator implements IReportQueryValidator {

	public function validate(array $query): void {
		if (($query['type'] ?? null) !== 'create') {
			throw new QueryValidationException("Unsupported query type: " . ($query['type'] ?? '[not defined]'));
		}

		$table = $query['table'] ?? null;
		if (!is_string($table) || trim($table) === '') {
			throw new QueryValidationException("CREATE query must define a non-empty 'table' name.");
		}

		$columns = $query['columns'] ?? null;
		if (!is_array($columns) || count($columns) === 0) {
			throw new QueryValidationException("CREATE query must contain a non-empty 'columns' array.");
		}

		foreach ($columns as $index => $column) {
			if (!is_array($column)) {
				throw new QueryValidationException("Each column entry must be an object.");
			}

			$name = $column['name'] ?? null;
			$type = $column['type'] ?? null;

			if (!is_string($name) || trim($name) === '') {
				throw new QueryValidationException("Column at index $index is missing a valid 'name'.");
			}

			if (!is_string($type) || trim($type) === '') {
				throw new QueryValidationException("Column '$name' is missing a valid 'type'.");
			}

			if (isset($column['nullable']) && !is_bool($column['nullable'])) {
				throw new QueryValidationException("Column '$name': 'nullable' must be boolean.");
			}

			if (isset($column['auto_increment']) && !is_bool($column['auto_increment'])) {
				throw new QueryValidationException("Column '$name': 'auto_increment' must be boolean.");
			}

			if (isset($column['primary_key']) && !is_bool($column['primary_key'])) {
				throw new QueryValidationException("Column '$name': 'primary_key' must be boolean.");
			}

			if (isset($column['default']) && !is_scalar($column['default'])) {
				throw new QueryValidationException("Column '$name': 'default' must be a scalar value.");
			}
		}

		// Optionale Absicherung gegen Fremdfelder
		$disallowed = ['where', 'fields', 'order_by', 'group_by', 'having', 'limit'];
		foreach ($disallowed as $key) {
			if (array_key_exists($key, $query)) {
				throw new QueryValidationException("CREATE query must not contain '$key'.");
			}
		}
	}
}

