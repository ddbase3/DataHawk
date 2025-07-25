<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Dto\SqlQuery;
use DataHawk\Exception\QueryValidationException;

/**
 * Compiles 'delete' type queries into SQL.
 */
class DeleteQueryCompiler implements IReportQueryTypeCompiler {

	private ElementCompiler $elementCompiler;

	public function __construct(IReportSchemaProvider $schemaProvider) {
		$aliasResolver = new AliasResolver();
		$this->elementCompiler = new ElementCompiler($aliasResolver, $this);
	}

	public function compile(array $query): SqlQuery {
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

		return new SqlQuery($sql, [], [], false);
	}
}

