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
 * Factory for selecting the appropriate query validator
 * based on the query type (e.g. 'select', 'delete', ...).
 */
class QueryValidatorFactory {

	/**
	 * Returns a validator instance based on the query type.
	 *
	 * @param string $type The type of the query (e.g. 'select')
	 * @return IReportQueryValidator
	 * @throws QueryValidationException If no validator exists for the type
	 */
	public function getValidator(string $type): IReportQueryValidator {
		return match ($type) {
			'select'   => new SelectQueryValidator(),
			'insert'   => new InsertQueryValidator(),	
			'update'   => new UpdateQueryValidator(),
			'delete'   => new DeleteQueryValidator(),
			'truncate' => new TruncateQueryValidator(),
			'drop'     => new DropQueryValidator(),
			'rename'   => new RenameQueryValidator(),
			'create'   => new CreateQueryValidator(),
			'alter'    => new AlterQueryValidator(),
			default  => throw new QueryValidationException("Unsupported query type: $type")
		};
	}
}

