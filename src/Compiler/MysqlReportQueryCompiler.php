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

namespace Base3\DataHawk\Compiler;

use Base3\DataHawk\Api\IReportQueryValidator;
use Base3\DataHawk\Api\IReportQueryTypeCompiler;
use Base3\ResourceFoundation\Api\IQueryCompiler;
use Base3\ResourceFoundation\Api\IQuerySchemaProvider;
use Base3\ResourceFoundation\Api\ITableNameResolver;
use Base3\ResourceFoundation\Dto\QueryStatement;
use Base3\ResourceFoundation\Exception\QueryValidationException;

/**
 * Central coordinator for compiling structured report queries.
 *
 * Delegates validation and SQL generation to specialized components.
 */
class MysqlReportQueryCompiler implements IQueryCompiler {

	private QueryValidatorFactory $validatorFactory;
	private QueryCompilerFactory $compilerFactory;

	public function __construct(
		private IQuerySchemaProvider $schemaProvider,
		private ?ITableNameResolver $tableNameResolver = null
	) {
		$this->validatorFactory = new QueryValidatorFactory();
		$this->compilerFactory = new QueryCompilerFactory($schemaProvider, $this, $tableNameResolver);
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

