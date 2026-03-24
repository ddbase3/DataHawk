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

