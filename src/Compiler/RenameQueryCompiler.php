<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Dto\SqlQuery;
use DataHawk\Exception\QueryValidationException;

/**
 * Compiles 'rename' type queries into SQL.
 */
class RenameQueryCompiler implements IReportQueryTypeCompiler {

	private ElementCompiler $elementCompiler;

	public function __construct(IReportSchemaProvider $schemaProvider) {
		$aliasResolver = new AliasResolver();
		$this->elementCompiler = new ElementCompiler($aliasResolver, $this);
	}

	public function compile(array $query): SqlQuery {
		$from = $query['tables']['from'] ?? null;
		$to = $query['tables']['to'] ?? null;

		if (!is_string($from) || trim($from) === '') {
			throw new QueryValidationException("RENAME query requires a valid 'from' table name.");
		}

		if (!is_string($to) || trim($to) === '') {
			throw new QueryValidationException("RENAME query requires a valid 'to' table name.");
		}

		$sql = 'RENAME TABLE ' .
			$this->elementCompiler->quoteIdentifier($from) .
			' TO ' .
			$this->elementCompiler->quoteIdentifier($to);

		return new SqlQuery($sql, [], [], false);
	}
}

