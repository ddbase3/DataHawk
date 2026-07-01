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

use DataHawk\Util\Graph;
use ResourceFoundation\Api\ITableNameResolver;
use ResourceFoundation\Dto\TableNameResolutionContext;
use ResourceFoundation\Exception\QueryValidationException;

class JoinPlanner {

	private Graph $joinGraph;

	public function __construct(
		private AliasResolver $aliasResolver,
		private ElementCompiler $elementCompiler,
		Graph $joinGraph,
		private ?ITableNameResolver $tableNameResolver = null
	) {
		$this->joinGraph = $joinGraph;
	}

	/**
	 * Scans all elements and extracts required join nodes.
	 *
	 * Important:
	 * - A table alias is not only a SQL output alias.
	 * - A table alias represents a distinct join node.
	 * - Therefore "base3system_sysentry" and "base3system_sysentry AS peer"
	 *   are two different graph nodes internally.
	 *
	 * The public query API does not change. This method still reads the existing
	 * "table", "tablealias" and "variant" fields from normal element definitions.
	 *
	 * Tables inside subqueries must not trigger JOINs in the outer query.
	 * Otherwise EXISTS(subquery) may accidentally introduce outer joins or duplicates.
	 */
	public function collectJoinDependencies(array $nodes): array {
		$joins = [];

		foreach ($nodes as $node) {
			if (!is_array($node)) continue;

			if (($node['type'] ?? null) === 'subquery') {
				continue;
			}

			if (($node['type'] ?? null) === 'fld') {
				$table = $node['table'] ?? null;
				if (!$table) continue;

				$alias = $node['tablealias'] ?? $table;
				$variant = $node['variant'] ?? null;

				$this->aliasResolver->registerAlias($alias, $table);
				$this->addJoinDependency($joins, $table, $alias, $variant);
				continue;
			}

			foreach (['element', 'params', 'args', 'left', 'right'] as $key) {
				if (empty($node[$key])) continue;

				$childNodes = is_array($node[$key])
					? (self::isAssoc($node[$key]) ? [$node[$key]] : $node[$key])
					: [];

				$subJoins = $this->collectJoinDependencies($childNodes);

				foreach ($subJoins as $join) {
					$this->addJoinDependency(
						$joins,
						$join['table'],
						$join['alias'],
						$join['variant']
					);
				}
			}
		}

		return $joins;
	}

	/**
	 * Adds a dependency keyed by table and alias.
	 *
	 * This is the central change for alias-aware joins:
	 * the key is not only the table name anymore.
	 */
	private function addJoinDependency(array &$joins, string $table, string $alias, mixed $variant): void {
		$key = $this->getAliasNodeKey($table, $alias);

		if (!isset($joins[$key])) {
			$joins[$key] = [
				'table' => $table,
				'alias' => $alias,
				'variant' => $variant
			];
			return;
		}

		/*
		 * If the same alias is referenced multiple times, required usage wins
		 * over optional usage. This keeps filter joins strict, while optional
		 * loader fields may still request LEFT JOIN behavior.
		 */
		if (!$this->isOptionalVariant($variant)) {
			$joins[$key]['variant'] = $variant;
		}
	}

	private function getAliasNodeKey(string $table, string $alias): string {
		return $table . '::' . $alias;
	}

	private static function isAssoc(array $array): bool {
		return array_keys($array) !== range(0, count($array) - 1);
	}

	/**
	 * Builds JOIN SQL based on alias-aware join nodes.
	 *
	 * Rules:
	 * - The base table without alias is already joined by FROM and is skipped.
	 * - The same table with another alias is a separate join node.
	 * - Normal joins are still planned from the base table, preserving the old behavior.
	 * - Self joins are planned through already joined intermediate alias nodes.
	 * - No new query API is introduced.
	 */
	public function compileJoins(string $from, array $joinRequests, ?string $fromAlias = null, string $schema = ''): string {
		$sql = '';
		$joinRequests = $this->normalizeJoinRequests($joinRequests);

		$joined = [];
		$aliasStates = [];

		$this->markJoined(
			$joined,
			$aliasStates,
			$from,
			$fromAlias ?? $from,
			null,
			null
		);


		$normalRequests = [];
		$selfJoinRequests = [];

		foreach ($joinRequests as $joinRequest) {
			$table = $joinRequest['table'];
			$alias = $joinRequest['alias'];

			if ($table === $from && $alias === ($fromAlias ?? $from)) {
				continue;
			}

			if ($table === $from) {
				$selfJoinRequests[] = $joinRequest;
				continue;
			}

			$normalRequests[] = $joinRequest;
		}

		/*
		 * First join all non-self dependencies from the base table.
		 *
		 * This preserves the existing DataHawk behavior for ordinary queries:
		 * fields from another table are joined relative to the query's base table.
		 */
		foreach ($normalRequests as $joinRequest) {
			$path = $this->resolveJoinPath($from, $joinRequest['table']);
			$sql .= $this->compileJoinPath($path, $fromAlias ?? $from, $joinRequest, $joinRequests, $joined, $aliasStates, $schema);
		}

		/*
		 * Then resolve self joins.
		 *
		 * A self join cannot be represented by "target table" alone, because the
		 * target table is already the FROM table. Therefore the target alias is
		 * treated as a separate graph node and must be reached through another
		 * already joined alias node.
		 */
		foreach ($selfJoinRequests as $joinRequest) {
			$selfJoin = $this->resolveSelfJoinPath($from, $joinRequest, $aliasStates);

			$sql .= $this->compileJoinPath(
				$selfJoin['path'],
				$selfJoin['startAlias'],
				$joinRequest,
				$joinRequests,
				$joined,
				$aliasStates,
				$schema
			);
		}

		return $sql;
	}

	/**
	 * Supports both the new alias-aware structure and the old table=>variant map.
	 *
	 * This keeps SelectQueryCompiler and possible external callers compatible.
	 */
	private function normalizeJoinRequests(array $joinRequests): array {
		$normalized = [];

		foreach ($joinRequests as $key => $value) {
			if (is_array($value) && isset($value['table'], $value['alias'])) {
				$normalized[] = [
					'table' => $value['table'],
					'alias' => $value['alias'],
					'variant' => $value['variant'] ?? null
				];
				continue;
			}

			if (is_string($key)) {
				$normalized[] = [
					'table' => $key,
					'alias' => $key,
					'variant' => $value
				];
			}
		}

		return $normalized;
	}

	/**
	 * Compiles a concrete table path into SQL JOIN clauses.
	 *
	 * The path still consists of schema table steps, but each step is compiled
	 * against an alias node. The final step uses the requested target alias.
	 * Intermediate steps use explicitly requested aliases when available.
	 */
	private function compileJoinPath(
		array $path,
		string $startAlias,
		array $joinRequest,
		array $allJoinRequests,
		array &$joined,
		array &$aliasStates,
		string $schema = ''
	): string {
		$sql = '';
		$currentAlias = $startAlias;
		$lastStepIndex = array_key_last($path);
		$targetVariant = $joinRequest['variant'] ?? null;

		foreach ($path as $stepIndex => $step) {
			$table = $step['to'];

			$alias = $stepIndex === $lastStepIndex
				? $joinRequest['alias']
				: $this->getIntermediateAlias($table, $allJoinRequests, $aliasStates);

			$joinKey = $this->getAliasNodeKey($table, $alias);

			if (isset($joined[$joinKey])) {
				$currentAlias = $alias;
				continue;
			}

			$this->aliasResolver->registerAlias($alias, $table);

			/*
			 * Join type rule:
			 * - The selected schema edge defines the normal join type.
			 * - variant: optional may intentionally widen this to LEFT JOIN
			 *   so loader fields do not remove base rows.
			 * - meta.default is not used to decide LEFT vs INNER.
			 */
			$schemaJoinType = strtoupper((string)($step['meta']['type'] ?? 'INNER'));
			$joinType = $schemaJoinType === 'LEFT' ? 'LEFT JOIN' : 'INNER JOIN';

			if ($this->isOptionalVariant($targetVariant)) {
				$joinType = 'LEFT JOIN';
			}

			$onParts = [];
			foreach (($step['meta']['on'] ?? []) as $left => $right) {
				$onParts[] = $this->quoteJoinField($left, $step['from'], $currentAlias, $step['to'], $alias)
					. ' = '
					. $this->quoteJoinField($right, $step['from'], $currentAlias, $step['to'], $alias);
			}

			$resolvedTableName = $this->resolveTableName($table, $alias, 'select.join', $schema);
			$sql .= " $joinType " . $this->elementCompiler->quoteIdentifier($resolvedTableName);
			if ($alias !== $table || $resolvedTableName !== $table) {
				$sql .= " AS " . $this->elementCompiler->quoteIdentifier($alias);
			}
			$sql .= " ON " . implode(" AND ", $onParts);

			$this->markJoined(
				$joined,
				$aliasStates,
				$table,
				$alias,
				$currentAlias,
				$step
			);

			$currentAlias = $alias;
		}

		return $sql;
	}

	/**
	 * Finds the alias to use for an intermediate path step.
	 *
	 * Preference order:
	 * 1. Already joined alias for the table.
	 * 2. Explicitly requested alias for the table.
	 * 3. Plain table name.
	 *
	 * This allows a query that references sysallocview AS loadallocuuidalloc
	 * and sysentry AS loadallocuuidpeer to produce:
	 *
	 * sysentry
	 * -> sysallocview AS loadallocuuidalloc
	 * -> sysentry AS loadallocuuidpeer
	 */
	private function getIntermediateAlias(string $table, array $allJoinRequests, array $aliasStates): string {
		foreach ($aliasStates as $aliasState) {
			if ($aliasState['table'] === $table) {
				return $aliasState['alias'];
			}
		}

		foreach ($allJoinRequests as $joinRequest) {
			if ($joinRequest['table'] === $table) {
				return $joinRequest['alias'];
			}
		}

		return $table;
	}

	/**
	 * Marks an alias node as joined.
	 *
	 * The stored parent edge is important for self joins. It lets the planner
	 * detect and avoid immediate backtracking through the same local field.
	 */
	private function markJoined(
		array &$joined,
		array &$aliasStates,
		string $table,
		string $alias,
		?string $parentAlias,
		?array $parentStep
	): void {
		$joined[$this->getAliasNodeKey($table, $alias)] = true;

		$aliasStates[$alias] = [
			'table' => $table,
			'alias' => $alias,
			'parentAlias' => $parentAlias,
			'parentStep' => $parentStep
		];
	}

	/**
	 * Resolves a self join without extending the query API.
	 *
	 * Example:
	 *
	 * base3system_sysentry
	 * -> base3system_sysallocview AS loadallocuuidalloc
	 * -> base3system_sysentry AS loadallocuuidpeer
	 *
	 * The target table is the same as the FROM table, but the target alias is
	 * different. That makes it a distinct alias graph node.
	 */
	private function resolveSelfJoinPath(string $from, array $joinRequest, array $aliasStates): array {
		$candidates = [];

		foreach ($aliasStates as $aliasState) {
			if ($aliasState['table'] === $from && $aliasState['alias'] === $from) {
				continue;
			}

			$paths = $this->findJoinPaths($aliasState['table'], $joinRequest['table']);

			foreach ($paths as $path) {
				if (empty($path)) continue;
				if ($this->pathStartsWithBacktracking($aliasState, $path)) continue;

				$candidates[] = [
					'startAlias' => $aliasState['alias'],
					'path' => $path
				];
			}
		}

		if (empty($candidates)) {
			/*
			 * Fallback for self joins where no explicit intermediate alias was
			 * referenced. This keeps the planner usable for simple self joins,
			 * but explicit intermediate aliases remain preferred and clearer.
			 */
			$paths = $this->findJoinPaths($from, $joinRequest['table']);

			foreach ($paths as $path) {
				if (empty($path)) continue;

				$candidates[] = [
					'startAlias' => $from,
					'path' => $path
				];
			}
		}

		if (empty($candidates)) {
			throw new QueryValidationException("No self join path from '$from' to alias '" . $joinRequest['alias'] . "'");
		}

		usort($candidates, function(array $a, array $b): int {
			return count($a['path']) <=> count($b['path']);
		});

		return $candidates[0];
	}

	/**
	 * Prevents immediate backtracking in self joins.
	 *
	 * Example:
	 *
	 * The current alias loadallocuuidalloc was joined by:
	 *   sysentry.id = loadallocuuidalloc.entry_id
	 *
	 * When joining back to sysentry, this edge would just point back to the
	 * original entry and must be avoided:
	 *   loadallocuuidalloc.entry_id = sysentry.id
	 *
	 * The peer edge is correct because it uses a different local field:
	 *   loadallocuuidalloc.peer_id = sysentry.id
	 */
	private function pathStartsWithBacktracking(array $aliasState, array $path): bool {
		$parentStep = $aliasState['parentStep'] ?? null;
		if (!$parentStep) return false;

		$firstStep = $path[0] ?? null;
		if (!$firstStep) return false;

		$currentTable = $aliasState['table'];
		$parentLocalFields = $this->getFieldsForTable($parentStep['meta']['on'] ?? [], $currentTable);
		$nextLocalFields = $this->getFieldsForTable($firstStep['meta']['on'] ?? [], $currentTable);

		if (empty($parentLocalFields) || empty($nextLocalFields)) {
			return false;
		}

		return count(array_intersect($parentLocalFields, $nextLocalFields)) > 0;
	}

	private function getFieldsForTable(array $on, string $table): array {
		$fields = [];

		foreach ($on as $left => $right) {
			foreach ([$left, $right] as $ref) {
				if (!is_string($ref) || !str_contains($ref, '.')) continue;

				[$refTable, $field] = explode('.', $ref, 2);
				if ($refTable !== $table) continue;

				$fields[] = $field;
			}
		}

		return array_values(array_unique($fields));
	}

	/**
	 * Resolves the best join path from one schema table to another schema table.
	 *
	 * Important:
	 * - meta.default is only used by Graph::getDefaultEdge() to select the
	 *   preferred schema edge when multiple direct edges exist.
	 * - The selected edge's "type" remains authoritative for LEFT/INNER.
	 * - We do not count default edges across a path as a scoring mechanism.
	 */
	private function resolveJoinPath(string $from, string $target): array {
		$directEdge = $this->joinGraph->getDefaultEdge($from, $target);
		if ($directEdge !== null) {
			return [[
				'from' => $from,
				'to' => $directEdge['to'],
				'label' => $directEdge['label'],
				'meta' => $directEdge['meta'],
			]];
		}

		$paths = $this->findJoinPaths($from, $target);

		if (empty($paths)) {
			throw new QueryValidationException("No join path from '$from' to '$target'");
		}

		return $paths[0];
	}

	private function findJoinPaths(string $from, string $target): array {
		$paths = $this->joinGraph->findAllPaths($from, $target);

		usort($paths, function(array $a, array $b): int {
			$lengthCompare = count($a) <=> count($b);
			if ($lengthCompare !== 0) {
				return $lengthCompare;
			}

			return $this->comparePathTableSequence($a, $b);
		});

		return $paths;
	}

	/**
	 * Keeps ordering deterministic without treating "more default edges" as
	 * generally better.
	 *
	 * If two paths have the same length, this compares their table sequence.
	 * Direct parallel edges between the same tables are already handled by
	 * Graph::getDefaultEdge() in resolveJoinPath().
	 */
	private function comparePathTableSequence(array $a, array $b): int {
		$aSequence = $this->getPathTableSequence($a);
		$bSequence = $this->getPathTableSequence($b);

		return $aSequence <=> $bSequence;
	}

	private function getPathTableSequence(array $path): string {
		$parts = [];

		foreach ($path as $step) {
			$parts[] = ($step['from'] ?? '') . '>' . ($step['to'] ?? '');
		}

		return implode('|', $parts);
	}

	private function isOptionalVariant(mixed $variant): bool {
		return $variant && strtoupper((string)$variant) === 'OPTIONAL';
	}

	/**
	 * Quotes a join field and replaces schema table names with the concrete
	 * aliases of the current join step.
	 *
	 * This method intentionally receives both sides of the current step:
	 * - from table + from alias
	 * - to table + to alias
	 *
	 * That makes alias handling explicit and prevents accidental reuse of the
	 * plain table name when a table is joined multiple times.
	 */
	private function resolveTableName(string $tableName, ?string $alias, string $operation, string $schema = ''): string {
		return $this->tableNameResolver?->resolveTableName(
			$tableName,
			new TableNameResolutionContext(
				schema: $schema,
				alias: $alias,
				operation: $operation
			)
		) ?? $tableName;
	}

	private function quoteJoinField(
		string $ref,
		string $fromTable,
		string $fromAlias,
		string $toTable,
		string $toAlias
	): string {
		if (!str_contains($ref, '.')) {
			return $this->elementCompiler->quoteIdentifier($ref);
		}

		[$tableOrAlias, $field] = explode('.', $ref, 2);

		if ($tableOrAlias === $toTable) {
			$usedTable = $toAlias;
		} elseif ($tableOrAlias === $fromTable) {
			$usedTable = $fromAlias;
		} else {
			$usedTable = $tableOrAlias;
		}

		return $this->elementCompiler->quoteIdentifier($usedTable) . '.' . $this->elementCompiler->quoteIdentifier($field);
	}
}
