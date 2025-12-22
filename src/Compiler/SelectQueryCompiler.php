<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use DataHawk\Util\Graph;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

class SelectQueryCompiler implements IReportQueryTypeCompiler {

	private ElementCompiler $elementCompiler;
	private AliasResolver $aliasResolver;
	private JoinPlanner $joinPlanner;
	private Graph $joinGraph;
	private IQuerySchemaProvider $schemaProvider;

	public function __construct(IQuerySchemaProvider $schemaProvider) {
		$this->schemaProvider = $schemaProvider;

		$this->aliasResolver = new AliasResolver();
		$this->elementCompiler = new ElementCompiler($this->aliasResolver, $this);

		// Build graph of all join relations between tables
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

	public function compile(array $query): QueryStatement {
		// ✨ UNION SUPPORT
		if (isset($query['union'])) {
			return $this->compileUnion($query);
		}

		$this->aliasResolver->scan($query);

		$table = $query['table'] ?? $query['from'] ?? $this->aliasResolver->getFirstUsedTable();
		if (!$table) {
			throw new QueryValidationException("Query must contain 'table' or at least one field reference with 'table'.");
		}

		// Collect all elements that may reference tables
		$elementSources = array_merge(
			$query['fields'] ?? [],
			$query['group_by'] ?? [],
			$query['order_by'] ?? [],
			(!empty($query['where'])  ? $this->flattenElements($query['where'])  : []),
			(!empty($query['having']) ? $this->flattenElements($query['having']) : [])
		);

		// Let JoinPlanner determine required joins
		$joinRequests = $this->joinPlanner->collectJoinDependencies($elementSources);

		// Build table metadata map
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

			// ✅ Allow scalar elements in SELECT fields (e.g. 1, "x", true, null)
			$fieldTable = is_array($element) ? ($element['table'] ?? null) : null;
			$fieldName = is_array($element) ? ($element['field'] ?? null) : null;
			$alias = $entry['alias'] ?? (is_array($element) ? ($element['alias'] ?? null) : null);

			$isWildcard = ($fieldName === '*');
			if ($isWildcard) $hasWildcard = true;

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
				'type'      => is_array($element) ? ($element['type'] ?? null) : null,
				'distinct'  => !empty($entry['distinct']) || !empty($query['distinct']),
				'sensitive' => $fieldSensitive,
				'wildcard'  => $isWildcard
			];

			$selectParts[] = $sqlExpr;
		}

		// No fields → invalid query
		if (empty($selectParts)) {
			throw new QueryValidationException("Query must contain at least one field in 'fields'.");
		}

		// Build final SQL
		$sql = 'SELECT ' . (!empty($query['distinct']) ? 'DISTINCT ' : '') . implode(', ', $selectParts);
		$sql .= ' FROM ' . $this->elementCompiler->quoteIdentifier($table);
		$sql .= $this->joinPlanner->compileJoins($table, $joinRequests);

		// WHERE (only if non-empty and compiles to non-empty SQL)
		if (!empty($query['where'])) {
			$whereSql = trim($this->elementCompiler->compileElement($query['where']));
			if ($whereSql !== '') {
				$sql .= ' WHERE ' . $whereSql;
			}
		}

		// GROUP BY (only if there are compiled expressions)
		if (!empty($query['group_by']) && is_array($query['group_by'])) {
			$groupParts = array_map(fn($el) => $this->elementCompiler->compileElement($el), $query['group_by']);
			$groupParts = array_values(array_filter(array_map('trim', $groupParts), fn($p) => $p !== ''));
			if (!empty($groupParts)) {
				$sql .= ' GROUP BY ' . implode(', ', $groupParts);
			}
		}

		// HAVING (only if non-empty and compiles to non-empty SQL)
		if (!empty($query['having'])) {
			$havingSql = trim($this->elementCompiler->compileElement($query['having']));
			if ($havingSql !== '') {
				$sql .= ' HAVING ' . $havingSql;
			}
		}

		// ORDER BY (only if there are compiled expressions)
		if (!empty($query['order_by']) && is_array($query['order_by'])) {
			$orderParts = [];
			foreach ($query['order_by'] as $order) {
				if (!isset($order['element'])) {
					throw new QueryValidationException("Missing element in order_by clause.");
				}
				$expr = trim($this->elementCompiler->compileElement($order['element']));
				if ($expr === '') {
					continue;
				}
				$dir = strtoupper($order['direction'] ?? 'ASC');
				if (!in_array($dir, ['ASC', 'DESC'])) {
					throw new QueryValidationException("Invalid order direction: $dir");
				}
				$orderParts[] = $expr . ' ' . $dir;
			}
			if (!empty($orderParts)) {
				$sql .= ' ORDER BY ' . implode(', ', $orderParts);
			}
		}

		if (isset($query['limit'])) {
			$sql .= ' LIMIT ' . (int)$query['limit'];
		}

		if (isset($query['offset'])) {
			$sql .= ' OFFSET ' . (int)$query['offset'];
		}

		// Return full compiled query including wildcard info
		return new QueryStatement($sql, [], $compiledFields, $isSensitiveQuery, $hasWildcard);
	}

	private function compileUnion(array $query): QueryStatement {
		$union = $query['union'];
		$all = !($union['distinct'] ?? true); // false → UNION ALL

		$sqlParts = [];
		foreach ($union['queries'] as $subQuery) {
			$compiled = $this->compile($subQuery); // recursive call
			$sqlParts[] = '(' . $compiled->sql . ')';
		}

		$sql = implode($all ? ' UNION ALL ' : ' UNION ', $sqlParts);

		// ORDER BY for unions – also guard against empty/invalid parts
		if (!empty($query['order_by']) && is_array($query['order_by'])) {
			$orderParts = [];
			foreach ($query['order_by'] as $order) {
				if (!isset($order['element'])) {
					throw new QueryValidationException("Missing element in order_by clause.");
				}
				$expr = trim($this->elementCompiler->compileElement($order['element']));
				if ($expr === '') {
					continue;
				}
				$dir = strtoupper($order['direction'] ?? 'ASC');
				if (!in_array($dir, ['ASC', 'DESC'])) {
					throw new QueryValidationException("Invalid order direction: $dir");
				}
				$orderParts[] = $expr . ' ' . $dir;
			}
			if (!empty($orderParts)) {
				$sql .= ' ORDER BY ' . implode(', ', $orderParts);
			}
		}

		if (isset($query['limit'])) {
			$sql .= ' LIMIT ' . (int)$query['limit'];
		}

		if (isset($query['offset'])) {
			$sql .= ' OFFSET ' . (int)$query['offset'];
		}

		return new QueryStatement($sql, [], [], false);
	}

	/**
	 * Recursively flatten nested WHERE / HAVING structures
	 * so JoinPlanner can detect all used tables.
	 */
	private function flattenElements(mixed $element): array {
		if (!is_array($element)) return [];
		$result = [$element];
		foreach ($element as $v) {
			if (is_array($v)) {
				$result = array_merge($result, $this->flattenElements($v));
			}
		}
		return $result;
	}
}
