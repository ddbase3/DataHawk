<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Api\IReportQueryCompiler;
use DataHawk\Exception\QueryValidationException;
use DataHawk\Dto\SqlQuery;
use DataHawk\Util\Graph;

class MysqlReportQueryCompiler implements IReportQueryCompiler
{
	private ElementCompiler $elementCompiler;
	private AliasResolver $aliasResolver;
	private JoinPlanner $joinPlanner;
	private QueryValidator $queryValidator;

	private Graph $joinGraph;

	public function __construct(
		private IReportSchemaProvider $schemaprovider
	) {
		$this->aliasResolver = new AliasResolver();
		$this->elementCompiler = new ElementCompiler($this->aliasResolver, $this);

		$this->joinGraph = new Graph();
		foreach ($this->schemaprovider->getSchema() as $table) {
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

		$this->queryValidator = new QueryValidator();
	}

	public function compile(array $query): SqlQuery {
		$this->queryValidator->validate($query);

		// Tabelle(n) scannen und Alias-Usage + erste Tabelle erfassen
		$this->aliasResolver->scan($query);

		// FROM bestimmen: entweder explizit oder implizit aus erster Feldreferenz
		$from = $query['from'] ?? $this->aliasResolver->getFirstUsedTable();
		if (!$from) {
			throw new QueryValidationException("Query must contain 'from' or at least one field reference with 'table'.");
		}

		// Join-Bedarf aus allen Elementen analysieren
		$joinRequests = [];
		$elementSources = array_merge(
			$query['fields'] ?? [],
			$query['group_by'] ?? [],
			$query['order_by'] ?? [],
			isset($query['where']) ? [$query['where']] : [],
			isset($query['having']) ? [$query['having']] : []
		);
		$joinRequests = $this->joinPlanner->collectJoinDependencies($elementSources);

		$selectParts = [];
		$compiledFields = [];
		$isSensitiveQuery = false;

		// Tabelle → Metadaten-Mapping vorbereiten
		$tableMetaMap = [];
		foreach ($this->schemaprovider->getSchema() as $tableMeta) {
			$tableMetaMap[$tableMeta->name] = $tableMeta;
		}

		foreach ($query['fields'] as $entry) {
			if (!isset($entry['element'])) {
				throw new QueryValidationException("Missing element in fields entry.");
			}

			$element = $entry['element'];
			$table = $element['table'] ?? null;
			$fieldName = $element['field'] ?? null;
			$alias = $entry['alias'] ?? $element['alias'] ?? null;

			$sqlExpr = !empty($entry['distinct']) && empty($query['distinct']) ? 'DISTINCT ' : '';
			$sqlExpr .= $this->elementCompiler->compileElement($element);
			if ($alias) {
				$sqlExpr .= ' AS ' . $this->elementCompiler->quoteIdentifier($alias);
			}

			// Sensitivity check: table or field
			$tableMeta = $tableMetaMap[$table] ?? null;
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
				'table'     => $table,
				'type'      => $element['type'] ?? null,
				'distinct'  => !empty($entry['distinct']) || !empty($query['distinct']),
				'sensitive' => $fieldSensitive,
			];

			$selectParts[] = $sqlExpr;
		}

		$selectModifier = !empty($query['distinct']) ? 'DISTINCT ' : '';
		$sql = 'SELECT ' . $selectModifier . implode(', ', $selectParts);

		$sql .= ' FROM ' . $this->elementCompiler->quoteIdentifier($from);
		$sql .= $this->joinPlanner->compileJoins($from, $joinRequests);

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

		return new SqlQuery($sql, [], $compiledFields, $isSensitiveQuery);
	}
}

