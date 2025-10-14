<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use DataHawk\Dto\SqlQuery;
use DataHawk\Exception\QueryValidationException;
use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Util\Graph;

class SelectQueryCompiler implements IReportQueryTypeCompiler {

	private ElementCompiler $elementCompiler;
	private AliasResolver $aliasResolver;
	private JoinPlanner $joinPlanner;
	private Graph $joinGraph;
	private IReportSchemaProvider $schemaProvider;

	public function __construct(IReportSchemaProvider $schemaProvider) {
		$this->schemaProvider = $schemaProvider;

		$this->aliasResolver = new AliasResolver();
		$this->elementCompiler = new ElementCompiler($this->aliasResolver, $this);

		$this->joinGraph = new Graph();
		foreach ($schemaProvider->getSchema() as $table) {
			$this->joinGraph->addNode($table->name);
			foreach ($table->joins as $join) {
				$meta = $join->meta;
				$meta['on'] = $join->on;
				$meta['type'] = $join->type;
				$label = !empty($meta['default']) ? 'default' : uniqid('join_', true);
				$this->joinGraph->addEdge($table->name, $join->targetTable, $label, $meta);
			}
		}

		$this->joinPlanner = new JoinPlanner($this->aliasResolver, $this->elementCompiler, $this->joinGraph);
	}

	public function compile(array $query): SqlQuery {
		// ✨ UNION SUPPORT
		if (isset($query['union'])) {
			return $this->compileUnion($query);
		}

		$this->aliasResolver->scan($query);

		$table = $query['table'] ?? $query['from'] ?? $this->aliasResolver->getFirstUsedTable();
		if (!$table) {
			throw new QueryValidationException("Query must contain 'table' or at least one field reference with 'table'.");
		}

		$elementSources = array_merge(
			$query['fields'] ?? [],
			$query['group_by'] ?? [],
			$query['order_by'] ?? [],
			isset($query['where']) ? [$query['where']] : [],
			isset($query['having']) ? [$query['having']] : []
		);

		$joinRequests = $this->joinPlanner->collectJoinDependencies($elementSources);

		$tableMetaMap = [];
		foreach ($this->schemaProvider->getSchema() as $tableMeta) {
			$tableMetaMap[$tableMeta->name] = $tableMeta;
		}

		$selectParts = [];
		$compiledFields = [];
		$isSensitiveQuery = false;
		$hasWildcard = false;

		foreach ($query['fields'] as $entry) {
			if (!isset($entry['element'])) {
				throw new QueryValidationException("Missing element in fields entry.");
			}

			$element = $entry['element'];
			$fieldTable = $element['table'] ?? null;
			$fieldName = $element['field'] ?? null;
			$alias = $entry['alias'] ?? $element['alias'] ?? null;

			$isWildcard = ($fieldName === '*');
			if ($isWildcard) {
				$hasWildcard = true;
			}

			$sqlExpr = !empty($entry['distinct']) && empty($query['distinct']) ? 'DISTINCT ' : '';
			$sqlExpr .= $this->elementCompiler->compileElement($element);
			if ($alias) {
				$sqlExpr .= ' AS ' . $this->elementCompiler->quoteIdentifier($alias);
			}

			$tableMeta = $tableMetaMap[$fieldTable] ?? null;
			$tableSensitive = $tableMeta?->sensitive ?? false;
			$fieldSensitive = false;

			foreach ($tableMeta?->fields ?? [] as $fieldMeta) {
				if ($fieldMeta->name !== $fieldName) continue;
				$fieldSensitive = $fieldMeta->sensitive || $tableSensitive;
				break;
			}

			if ($fieldSensitive) $isSensitiveQuery = true;

			$compiledFields[] = [
				'name'      => $fieldName,
				'alias'     => $alias,
				'table'     => $fieldTable,
				'type'      => $element['type'] ?? null,
				'distinct'  => !empty($entry['distinct']) || !empty($query['distinct']),
				'sensitive' => $fieldSensitive,
				'wildcard'  => $isWildcard
			];

			$selectParts[] = $sqlExpr;
		}

		$sql = 'SELECT ' . (!empty($query['distinct']) ? 'DISTINCT ' : '') . implode(', ', $selectParts);
		$sql .= ' FROM ' . $this->elementCompiler->quoteIdentifier($table);
		$sql .= $this->joinPlanner->compileJoins($table, $joinRequests);

		if (isset($query['where'])) {
			$sql .= ' WHERE ' . $this->elementCompiler->compileElement($query['where']);
		}

		if (isset($query['group_by']) && is_array($query['group_by'])) {
			$groupParts = array_map(fn($el) => $this->elementCompiler->compileElement($el), $query['group_by']);
			$sql .= ' GROUP BY ' . implode(', ', $groupParts);
		}

		if (isset($query['having'])) {
			$sql .= ' HAVING ' . $this->elementCompiler->compileElement($query['having']);
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

		if (isset($query['offset'])) {
			$sql .= ' OFFSET ' . (int)$query['offset'];
		}

		// ✅ mark wildcard query mode in result
		return new SqlQuery($sql, [], $compiledFields, $isSensitiveQuery, $hasWildcard);
	}

	private function compileUnion(array $query): SqlQuery {
		$union = $query['union'];
		$all = !($union['distinct'] ?? true); // false → UNION ALL

		$sqlParts = [];
		foreach ($union['queries'] as $subQuery) {
			$compiled = $this->compile($subQuery); // recursive call
			$sqlParts[] = '(' . $compiled->sql . ')';
		}

		$sql = implode($all ? ' UNION ALL ' : ' UNION ', $sqlParts);

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

		if (isset($query['offset'])) {
			$sql .= ' OFFSET ' . (int)$query['offset'];
		}

		return new SqlQuery($sql, [], [], false);
	}
}

