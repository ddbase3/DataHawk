<?php declare(strict_types=1);

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
	 */
	public function compileJoins(string $from, array $joinRequests): string {
		$sql = '';
		$aliasUsage = $this->aliasResolver->getAliasUsage();

		// Global dedup map: table::alias => true
		$joined = [];
		$joined[$from . '::' . $from] = true;

		foreach ($joinRequests as $target => $variant) {
			if ($target === $from) continue;

			$paths = $this->joinGraph->findAllPaths($from, $target);
			if (empty($paths)) {
				throw new QueryValidationException("No join path from '$from' to '$target'");
			}

			// Prefer a "default" path if available
			$path = null;
			foreach ($paths as $candidate) {
				if (!empty($candidate[0]['meta']['default'])) {
					$path = $candidate;
					break;
				}
			}
			if (!$path) $path = $paths[0];

			$lastStep = $path[array_key_last($path)];

			foreach ($path as $step) {
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
					$isLastStep = ($step === $lastStep);

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
