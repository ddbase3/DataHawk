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
use ResourceFoundation\Exception\QueryValidationException;

class JoinPlanner {

	private Graph $joinGraph;

	public function __construct(
		private AliasResolver $aliasResolver,
		private ElementCompiler $elementCompiler,
		Graph $joinGraph
	) {
		$this->joinGraph = $joinGraph;
	}

	/**
	 * Scans all elements and extracts required tables (including optional "variant").
	 *
	 * IMPORTANT:
	 * - Tables inside subqueries must NOT trigger JOINs in the outer query.
	 *   Otherwise EXISTS(subquery) may accidentally introduce outer joins / duplicates.
	 */
	public function collectJoinDependencies(array $nodes): array {
		$tables = [];

		foreach ($nodes as $node) {
			if (!is_array($node)) continue;

			// If this node is a subquery wrapper, do NOT scan into it for outer JOIN planning.
			if (($node['type'] ?? null) === 'subquery') {
				continue;
			}

			// Direct field reference
			if (($node['type'] ?? null) === 'fld') {
				$table = $node['table'];
				$alias = $node['tablealias'] ?? $table;

				$this->aliasResolver->registerAlias($alias, $table);

				if (!isset($tables[$table])) {
					$tables[$table] = $node['variant'] ?? null;
				}
				continue;
			}

			// Recursively scan known subkeys (but NOT 'query' to avoid pulling in subquery tables)
			foreach (['element', 'params', 'args', 'left', 'right'] as $key) {
				if (empty($node[$key])) continue;

				$childNodes = is_array($node[$key])
					? (self::isAssoc($node[$key]) ? [$node[$key]] : $node[$key])
					: [];

				$subTables = $this->collectJoinDependencies($childNodes);

				foreach ($subTables as $tbl => $variant) {
					if (!isset($tables[$tbl])) $tables[$tbl] = $variant;
				}
			}
		}

		return $tables;
	}

	private static function isAssoc(array $array): bool {
		return array_keys($array) !== range(0, count($array) - 1);
	}

	/**
	 * Builds JOIN SQL based on resolved join paths.
	 *
	 * Fixes:
	 * - Global join deduplication (no duplicate JOINs across multiple join requests / overlapping paths)
	 * - Correct join field quoting: alias is applied to the correct side (table being joined)
	 * - Prefer direct joins over indirect graph paths
	 * - Prefer shorter paths over longer ones to avoid unrelated detours through metadata tables
	 */
	public function compileJoins(string $from, array $joinRequests): string {
		$sql = '';
		$aliasUsage = $this->aliasResolver->getAliasUsage();

		// Global dedup map: table::alias => true
		$joined = [];
		$joined[$from . '::' . $from] = true;

		foreach ($joinRequests as $target => $variant) {
			if ($target === $from) continue;

			$path = $this->resolveJoinPath($from, $target);
			$lastStepIndex = array_key_last($path);

			foreach ($path as $stepIndex => $step) {
				$table = $step['to'];

				// Use all aliases that are actually referenced for this table; if none, fall back to the table name.
				$aliases = array_keys($aliasUsage[$table] ?? [$table => true]);

				foreach ($aliases as $alias) {
					$alreadyJoined = "{$table}::{$alias}";
					if (isset($joined[$alreadyJoined])) continue;
					$joined[$alreadyJoined] = true;

					$this->aliasResolver->registerAlias($alias, $table);

					$isDefault = (bool)($step['meta']['default'] ?? false);
					$stepType = strtoupper((string)($step['meta']['type'] ?? 'INNER'));
					$isLastStep = ($stepIndex === $lastStepIndex);

					// Determine join type (default join type can come from schema meta; variant may override on last step).
					$useLeft = ($isLastStep && $variant && strtoupper((string)$variant) === 'OPTIONAL')
						|| ($isLastStep && !$variant && $isDefault && $stepType === 'LEFT');

					$joinType = $useLeft ? 'LEFT JOIN' : 'INNER JOIN';

					// Build ON clause (apply alias only for the table that is being joined in this step)
					$onParts = [];
					foreach (($step['meta']['on'] ?? []) as $left => $right) {
						$onParts[] = $this->quoteJoinField($left, $table, $alias) . ' = ' . $this->quoteJoinField($right, $table, $alias);
					}

					$sql .= " $joinType " . $this->elementCompiler->quoteIdentifier($table);
					if ($alias !== $table) {
						$sql .= " AS " . $this->elementCompiler->quoteIdentifier($alias);
					}
					$sql .= " ON " . implode(" AND ", $onParts);
				}
			}
		}

		return $sql;
	}

	/**
	 * Resolves the best join path from $from to $target.
	 *
	 * Strategy:
	 * - Prefer a direct edge if available
	 * - Otherwise prefer the shortest path
	 * - If multiple paths have the same length, prefer the one with more default-marked steps
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

		$paths = $this->joinGraph->findAllPaths($from, $target);
		if (empty($paths)) {
			throw new QueryValidationException("No join path from '$from' to '$target'");
		}

		usort($paths, function(array $a, array $b): int {
			$lengthCompare = count($a) <=> count($b);
			if ($lengthCompare !== 0) {
				return $lengthCompare;
			}

			$defaultCompare = $this->countDefaultSteps($b) <=> $this->countDefaultSteps($a);
			if ($defaultCompare !== 0) {
				return $defaultCompare;
			}

			return 0;
		});

		return $paths[0];
	}

	/**
	 * Counts how many steps in a path are marked as default.
	 */
	private function countDefaultSteps(array $path): int {
		$count = 0;

		foreach ($path as $step) {
			if (!empty($step['meta']['default'])) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Quotes "table.field" with correct aliasing:
	 * - If the reference uses the table that is currently being joined, replace it by $joinAlias.
	 * - Otherwise keep the table as-is (it may already be an alias or a base table).
	 */
	private function quoteJoinField(string $ref, string $joinTable, string $joinAlias): string {
		if (!str_contains($ref, '.')) {
			// Fallback: treat as identifier
			return $this->elementCompiler->quoteIdentifier($ref);
		}

		[$tableOrAlias, $field] = explode('.', $ref, 2);

		// Only rewrite the side that references the table being joined in the current step.
		$usedTable = ($tableOrAlias === $joinTable) ? $joinAlias : $tableOrAlias;

		return $this->elementCompiler->quoteIdentifier($usedTable) . '.' . $this->elementCompiler->quoteIdentifier($field);
	}
}
