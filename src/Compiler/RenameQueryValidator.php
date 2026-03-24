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

