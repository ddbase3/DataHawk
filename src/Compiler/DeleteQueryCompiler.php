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

use DataHawk\Api\IReportQueryTypeCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Compiles 'delete' type queries into SQL.
 */
class DeleteQueryCompiler implements IReportQueryTypeCompiler {

	private ElementCompiler $elementCompiler;

	public function __construct(IQuerySchemaProvider $schemaProvider) {
		$aliasResolver = new AliasResolver();
		$this->elementCompiler = new ElementCompiler($aliasResolver, $this);
	}

	public function compile(array $query): QueryStatement {
		$table = $query['table'];

		$sql = 'DELETE FROM ' . $this->elementCompiler->quoteIdentifier($table);

		if (isset($query['where'])) {
			$sql .= ' WHERE ' . $this->elementCompiler->compileElement($query['where']);
		}

		if (isset($query['order_by']) && is_array($query['order_by'])) {
			$orderParts = [];
			foreach ($query['order_by'] as $order) {
				if (!isset($order['element'])) {
					throw new QueryValidationException("Missing element in order_by clause.");
				}
				$expr = $this->elementCompiler->compileElement($order['element']);
				$dir = strtoupper($order['direction'] ?? 'ASC');
				if (!in_array($dir, ['ASC', 'DESC'])) {
					throw new QueryValidationException("Invalid order direction: $dir");
				}
				$orderParts[] = $expr . ' ' . $dir;
			}
			$sql .= ' ORDER BY ' . implode(', ', $orderParts);
		}

		if (isset($query['limit'])) {
			$sql .= ' LIMIT ' . (int)$query['limit'];
		}

		return new QueryStatement($sql, [], [], false);
	}
}
