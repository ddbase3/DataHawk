<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Compiles 'rename' type queries into SQL.
 */
class RenameQueryCompiler implements IReportQueryTypeCompiler {

	private ElementCompiler $elementCompiler;

	public function __construct(IQuerySchemaProvider $schemaProvider) {
		$aliasResolver = new AliasResolver();
		$this->elementCompiler = new ElementCompiler($aliasResolver, $this);
	}

	public function compile(array $query): QueryStatement {
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

		return new QueryStatement($sql, [], [], false);
	}
}
