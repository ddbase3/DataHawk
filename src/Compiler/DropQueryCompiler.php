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

use DataHawk\Api\IReportQueryTypeCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Compiles 'drop' type queries into SQL.
 */
class DropQueryCompiler implements IReportQueryTypeCompiler {

	private ElementCompiler $elementCompiler;

	public function __construct(IQuerySchemaProvider $schemaProvider) {
		$aliasResolver = new AliasResolver();
		$this->elementCompiler = new ElementCompiler($aliasResolver, $this);
	}

	public function compile(array $query): QueryStatement {
		$table = $query['table'] ?? null;

		if (!$table || !is_string($table)) {
			throw new QueryValidationException("DROP query must define a valid 'table'.");
		}

		$sql = 'DROP TABLE ' . $this->elementCompiler->quoteIdentifier($table);

		return new QueryStatement($sql, [], [], false);
	}
}
