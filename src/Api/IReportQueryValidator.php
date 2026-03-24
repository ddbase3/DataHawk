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

namespace DataHawk\Api;

/**
 * Interface IReportQueryValidator
 *
 * Defines the contract for validating structured report queries
 * before they are compiled into SQL.
 */
interface IReportQueryValidator {

	/**
	 * Validates a report query represented as an associative array.
	 *
	 * Implementations should check for structural and semantic correctness
	 * of the query, such as required keys, supported operations,
	 * and consistency of fields and tables.
	 *
	 * @param array $query The query structure to validate
	 *
	 * @throws \ResourceFoundation\Exception\QueryValidationException If validation fails
	 */
	public function validate(array $query): void;
}

