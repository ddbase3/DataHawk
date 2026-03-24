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
use DataHawk\Api\IReportQueryTypeCompiler;
use ResourceFoundation\Api\IQueryCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Central coordinator for compiling structured report queries.
 *
 * Delegates validation and SQL generation to specialized components.
 */
class MysqlReportQueryCompiler implements IQueryCompiler {

	private QueryValidatorFactory $validatorFactory;
	private QueryCompilerFactory $compilerFactory;

	public function __construct(private IQuerySchemaProvider $schemaProvider) {
		$this->validatorFactory = new QueryValidatorFactory();
		$this->compilerFactory = new QueryCompilerFactory($schemaProvider, $this);
	}

	/**
	 * Compiles a structured query into a SQLQuery DTO.
	 *
	 * @param array $query Structured query input
	 * @return QueryStatement 
	 * @throws QueryValidationException
	 */
	public function compile(array $query): QueryStatement {
		$type = $query['type'] ?? 'select';

		$validator = $this->validatorFactory->getValidator($type);
		$validator->validate($query);

		$compiler = $this->compilerFactory->getCompiler($type);
		return $compiler->compile($query);
	}
}

