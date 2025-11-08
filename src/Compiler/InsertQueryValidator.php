<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryValidator;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Validates 'insert' type queries.
 *
 * Supports two forms: insert-values and insert-from-select.
 */
class InsertQueryValidator implements IReportQueryValidator {

	public function validate(array $query): void {
		if (($query['type'] ?? null) !== 'insert') {
			throw new QueryValidationException("Unsupported query type: " . ($query['type'] ?? '[not defined]'));
		}

		if (empty($query['table']) || !is_string($query['table'])) {
			throw new QueryValidationException("INSERT query must contain a valid 'table' name.");
		}

		$hasValues = isset($query['values']);
		$hasFrom   = isset($query['from']);

		if (!$hasValues && !$hasFrom) {
			throw new QueryValidationException("INSERT query must contain either 'values' or 'from'.");
		}

		if ($hasValues && $hasFrom) {
			throw new QueryValidationException("INSERT query cannot contain both 'values' and 'from'.");
		}

		// Validate "values" if present
		if ($hasValues) {
			if (!is_array($query['values']) || empty($query['values'])) {
				throw new QueryValidationException("'values' must be a non-empty array.");
			}
			foreach ($query['values'] as $i => $row) {
				if (!is_array($row)) {
					throw new QueryValidationException("Each entry in 'values' must be an object (row). Entry at index $i is invalid.");
				}
				foreach ($row as $key => $val) {
					if (!is_string($key) || $key === '') {
						throw new QueryValidationException("Each row must have named columns. Found invalid key in row $i.");
					}
				}
			}
		}

		// Validate "columns" if present
		if (isset($query['columns'])) {
			if (!is_array($query['columns']) || empty($query['columns'])) {
				throw new QueryValidationException("'columns' must be a non-empty array of strings.");
			}
			foreach ($query['columns'] as $col) {
				if (!is_string($col) || trim($col) === '') {
					throw new QueryValidationException("Each entry in 'columns' must be a non-empty string.");
				}
			}
		}

		// Basic blocklist – no SELECT-related fields in root
		$disallowed = ['fields', 'where', 'group_by', 'order_by', 'having', 'limit'];
		foreach ($disallowed as $key) {
			if (array_key_exists($key, $query)) {
				throw new QueryValidationException("INSERT query must not contain '$key' at the top level.");
			}
		}
	}
}

