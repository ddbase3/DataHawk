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

use ResourceFoundation\Dto\QueryStatement;

/**
 * Interface for compilers that handle a specific report query type
 * (e.g. 'select', 'delete', 'create', etc.) and generate SQL.
 */
interface IReportQueryTypeCompiler {

	/**
	 * Compiles a structured report query into an SQL representation.
	 *
	 * @param array $query The structured query input
	 * @return QueryStatement The compiled SQL with metadata
	 */
	public function compile(array $query): QueryStatement;
}

