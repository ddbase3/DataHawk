<?php declare(strict_types=1);

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

