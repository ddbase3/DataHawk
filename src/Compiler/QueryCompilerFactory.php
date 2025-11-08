<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
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
		MysqlReportQueryCompiler $mainCompiler
	) {
		$this->compilers = [
			'select'   => new SelectQueryCompiler($schemaProvider),
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

