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

use DataHawk\Api\IReportQueryValidator;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Validates 'update' type queries.
 *
 * Supports literal and expression values in 'set',
 * and optional 'where' conditions.
 */
class UpdateQueryValidator implements IReportQueryValidator {

	public function validate(array $query): void {
		if (($query['type'] ?? null) !== 'update') {
			throw new QueryValidationException("Unsupported query type: " . ($query['type'] ?? '[not defined]'));
		}

		if (empty($query['table']) || !is_string($query['table'])) {
			throw new QueryValidationException("UPDATE query must contain a valid 'table' name.");
		}

		$set = $query['set'] ?? null;
		if (!is_array($set) || empty($set)) {
			throw new QueryValidationException("UPDATE query must contain a non-empty 'set' object.");
		}

		foreach ($set as $field => $value) {
			if (!is_string($field) || trim($field) === '') {
				throw new QueryValidationException("Each 'set' key must be a non-empty string.");
			}

			if (is_array($value) && !isset($value['type'])) {
				throw new QueryValidationException("Invalid 'set' value for '$field': missing 'type' in expression.");
			}
			// Literal is ok: string, number, bool, null → no check needed
		}

		// Optional: where expression must be valid structure
		if (isset($query['where']) && !is_array($query['where'])) {
			throw new QueryValidationException("'where' must be a valid expression object.");
		}

		// Optional: block disallowed root-level fields
		$disallowed = ['fields', 'values', 'group_by', 'having'];
		foreach ($disallowed as $key) {
			if (array_key_exists($key, $query)) {
				throw new QueryValidationException("UPDATE query must not contain '$key'.");
			}
		}
	}
}

