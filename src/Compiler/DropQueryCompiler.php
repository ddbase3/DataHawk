<?php declare(strict_types=1);

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
