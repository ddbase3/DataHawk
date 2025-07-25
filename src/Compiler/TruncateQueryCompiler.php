<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Dto\SqlQuery;
use DataHawk\Exception\QueryValidationException;

/**
 * Compiles 'truncate' type queries into SQL.
 */
class TruncateQueryCompiler implements IReportQueryTypeCompiler {

	private ElementCompiler $elementCompiler;

	public function __construct(IReportSchemaProvider $schemaProvider) {
		$aliasResolver = new AliasResolver();
		$this->elementCompiler = new ElementCompiler($aliasResolver, $this);
	}

	public function compile(array $query): SqlQuery {
		$table = $query['table'] ?? null;

		if (!$table || !is_string($table)) {
			throw new QueryValidationException("TRUNCATE query must define a valid 'table'.");
		}

		$sql = 'TRUNCATE TABLE ' . $this->elementCompiler->quoteIdentifier($table);

		return new SqlQuery($sql, [], [], false);
	}
}

