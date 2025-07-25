<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Dto\SqlQuery;
use DataHawk\Exception\QueryValidationException;
use DataHawk\Util\Graph;

/**
 * Compiles 'update' type queries into SQL.
 */
class UpdateQueryCompiler implements IReportQueryTypeCompiler {

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
		$this->aliasResolver->scan($query);

		$table = $query['table'] ?? null;
		if (!$table || !is_string($table)) {
			throw new QueryValidationException("UPDATE query must contain 'table'.");
		}

		$set = $query['set'] ?? [];
		if (empty($set)) {
			throw new QueryValidationException("UPDATE query must contain non-empty 'set' definition.");
		}

		// Sammle alle referenzierten Elemente (SET + WHERE)
		$joinSources = array_merge(
			array_values($set),
			isset($query['where']) ? [$query['where']] : []
		);
		$joinRequests = $this->joinPlanner->collectJoinDependencies($joinSources);

		// Beginne SQL
		$sql = 'UPDATE ' . $this->elementCompiler->quoteIdentifier($table);
		$sql .= $this->joinPlanner->compileJoins($table, $joinRequests);

		// SET
		$setParts = [];
		foreach ($set as $field => $value) {
			$fieldSql = $this->elementCompiler->quoteIdentifier($field);
			$exprSql = is_array($value) && isset($value['type'])
				? $this->elementCompiler->compileElement($value)
				: $this->elementCompiler->quoteLiteral($value);
			$setParts[] = $fieldSql . ' = ' . $exprSql;
		}
		$sql .= ' SET ' . implode(', ', $setParts);

		// WHERE (optional)
		if (isset($query['where'])) {
			$sql .= ' WHERE ' . $this->elementCompiler->compileElement($query['where']);
		}

		// LIMIT (optional)
		if (isset($query['limit'])) {
			$sql .= ' LIMIT ' . (int)$query['limit'];
		}

		return new SqlQuery($sql, [], [], false);
	}
}

