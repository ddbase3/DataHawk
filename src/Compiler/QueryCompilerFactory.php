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
use ResourceFoundation\Api\ITableNameResolver;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Factory for retrieving the appropriate query compiler instance
 * based on the query type.
 */
class QueryCompilerFactory {

	/** @var array<string, IReportQueryTypeCompiler> */
	private array $compilers = [];

	public function __construct(
		IQuerySchemaProvider $schemaProvider,
		MysqlReportQueryCompiler $mainCompiler,
		?ITableNameResolver $tableNameResolver = null
	) {
		$this->compilers = [
			'select'   => new SelectQueryCompiler($schemaProvider, $tableNameResolver),
			'insert'   => new InsertQueryCompiler($schemaProvider, $mainCompiler),
			'update'   => new UpdateQueryCompiler($schemaProvider),
			'delete'   => new DeleteQueryCompiler($schemaProvider),
			'truncate' => new TruncateQueryCompiler($schemaProvider),
			'drop'     => new DropQueryCompiler($schemaProvider),
			'rename'   => new RenameQueryCompiler($schemaProvider),
			'create'   => new CreateQueryCompiler($schemaProvider),
			'alter'    => new AlterQueryCompiler(),
		];
	}

	/**
	 * Returns the compiler for the given query type.
	 *
	 * @param string $type
	 * @return IReportQueryTypeCompiler
	 * @throws QueryValidationException if no compiler is registered
	 */
	public function getCompiler(string $type): IReportQueryTypeCompiler {
		if (!isset($this->compilers[$type])) {
			throw new QueryValidationException("No compiler registered for query type: $type");
		}
		return $this->compilers[$type];
	}
}

