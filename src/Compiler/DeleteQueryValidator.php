<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryValidator;
use DataHawk\Exception\QueryValidationException;

/**
 * Validates queries of type 'delete'.
 *
 * Ensures that a table is specified, a WHERE clause is present
 * (to prevent full table deletions), and optional ORDER BY and LIMIT
 * clauses are valid.
 */
class DeleteQueryValidator implements IReportQueryValidator {

	/**
	 * Validates a 'delete' type query.
	 *
	 * @param array $query The query to validate
	 * @throws QueryValidationException If the query is invalid
	 */
	public function validate(array $query): void {
		if (($query['type'] ?? null) !== 'delete') {
			throw new QueryValidationException("Unsupported query type: " . ($query['type'] ?? '[not defined]'));
		}

		if (empty($query['table']) || !is_string($query['table'])) {
			throw new QueryValidationException("DELETE query must define a valid 'table'.");
		}

		if (empty($query['where']) || !is_array($query['where'])) {
			throw new QueryValidationException("DELETE query must contain a 'where' clause to avoid full deletions.");
		}

		foreach ($query['order_by'] ?? [] as $order) {
			if (!isset($order['element'])) {
				throw new QueryValidationException("Missing element in order_by clause.");
			}

			$dir = strtoupper($order['direction'] ?? 'ASC');
			if (!in_array($dir, ['ASC', 'DESC'], true)) {
				throw new QueryValidationException("Invalid order direction: $dir");
			}
		}

		if (isset($query['limit']) && !is_int($query['limit'])) {
			throw new QueryValidationException("LIMIT must be an integer.");
		}
	}
}

