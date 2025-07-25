<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryValidator;
use DataHawk\Exception\QueryValidationException;

/**
 * Validates 'truncate' type queries.
 *
 * Ensures that a valid table is specified.
 */
class TruncateQueryValidator implements IReportQueryValidator {

	/**
	 * Validates a 'truncate' query.
	 *
	 * @param array $query
	 * @throws QueryValidationException if invalid
	 */
	public function validate(array $query): void {
		if (($query['type'] ?? null) !== 'truncate') {
			throw new QueryValidationException("Unsupported query type: " . ($query['type'] ?? '[not defined]'));
		}

		if (empty($query['table']) || !is_string($query['table'])) {
			throw new QueryValidationException("TRUNCATE query must define a non-empty 'table' as string.");
		}

		// Optionale Absicherung gegen unerwartete Felder
		$unsupported = ['where', 'order_by', 'limit', 'fields', 'group_by', 'having'];
		foreach ($unsupported as $key) {
			if (array_key_exists($key, $query)) {
				throw new QueryValidationException("TRUNCATE query must not contain '$key'.");
			}
		}
	}
}

