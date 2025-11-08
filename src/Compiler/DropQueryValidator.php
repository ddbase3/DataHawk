<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryValidator;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Validates 'drop' type queries.
 *
 * Ensures that a valid table name is specified and
 * no illegal fields are present.
 */
class DropQueryValidator implements IReportQueryValidator {

	public function validate(array $query): void {
		if (($query['type'] ?? null) !== 'drop') {
			throw new QueryValidationException("Unsupported query type: " . ($query['type'] ?? '[not defined]'));
		}

		if (empty($query['table']) || !is_string($query['table'])) {
			throw new QueryValidationException("DROP query must define a non-empty 'table' as string.");
		}

		// Disallow DML-related options
		$disallowed = ['where', 'fields', 'limit', 'order_by', 'group_by', 'having'];
		foreach ($disallowed as $key) {
			if (array_key_exists($key, $query)) {
				throw new QueryValidationException("DROP query must not contain '$key'.");
			}
		}
	}
}

