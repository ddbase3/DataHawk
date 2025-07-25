<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryCompiler;
use DataHawk\Api\IReportQueryValidator;
use DataHawk\Api\IReportQueryTypeCompiler;
use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Dto\SqlQuery;
use DataHawk\Exception\QueryValidationException;

/**
 * Central coordinator for compiling structured report queries.
 *
 * Delegates validation and SQL generation to specialized components.
 */
class MysqlReportQueryCompiler implements IReportQueryCompiler {

	private QueryValidatorFactory $validatorFactory;
	private QueryCompilerFactory $compilerFactory;

	public function __construct(private IReportSchemaProvider $schemaProvider) {
		$this->validatorFactory = new QueryValidatorFactory();
		$this->compilerFactory = new QueryCompilerFactory($schemaProvider, $this);
	}

	/**
	 * Compiles a structured query into a SQLQuery DTO.
	 *
	 * @param array $query Structured query input
	 * @return SqlQuery
	 * @throws QueryValidationException
	 */
	public function compile(array $query): SqlQuery {
		$type = $query['type'] ?? 'select';

		$validator = $this->validatorFactory->getValidator($type);
		$validator->validate($query);

		$compiler = $this->compilerFactory->getCompiler($type);
		return $compiler->compile($query);
	}
}

