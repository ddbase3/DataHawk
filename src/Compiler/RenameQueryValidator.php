<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryValidator;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Validates 'rename' type queries.
 *
 * Ensures that both 'from' and 'to' table names are defined and valid.
 */
class RenameQueryValidator implements IReportQueryValidator {

	public function validate(array $query): void {
		if (($query['type'] ?? null) !== 'rename') {
			throw new QueryValidationException("Unsupported query type: " . ($query['type'] ?? '[not defined]'));
		}

		if (empty($query['tables']) || !is_array($query['tables'])) {
			throw new QueryValidationException("RENAME query must contain a 'tables' object with 'from' and 'to'.");
		}

		$from = $query['tables']['from'] ?? null;
		$to = $query['tables']['to'] ?? null;

		if (!is_string($from) || trim($from) === '') {
			throw new QueryValidationException("RENAME query requires a non-empty 'from' table name.");
		}

		if (!is_string($to) || trim($to) === '') {
			throw new QueryValidationException("RENAME query requires a non-empty 'to' table name.");
		}

		// Absicherung gegen unpassende DML-Felder
		$disallowed = ['fields', 'where', 'order_by', 'group_by', 'limit', 'having'];
		foreach ($disallowed as $key) {
			if (array_key_exists($key, $query)) {
				throw new QueryValidationException("RENAME query must not contain '$key'.");
			}
		}
	}
}

