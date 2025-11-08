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
	 * Analysiert alle Elemente und extrahiert benötigte Tabellen (mit Variant)
	 */
	public function collectJoinDependencies(array $nodes): array {
		$tables = [];

		foreach ($nodes as $node) {
			if (!is_array($node)) continue;

			// Direct field reference
			if (($node['type'] ?? null) === 'fld') {
				$table = $node['table'];
				$alias = $node['tablealias'] ?? $table;
				$this->aliasResolver->registerAlias($alias, $table);
				if (!isset($tables[$table])) {
					$tables[$table] = $node['variant'] ?? null;
				}
				continue; // No deeper scan needed
			}

			// Recursively scan known subkeys
			foreach (['element', 'params', 'query', 'args', 'left', 'right'] as $key) {
				if (!empty($node[$key])) {
					$childNodes = is_array($node[$key])
						? (self::isAssoc($node[$key]) ? [$node[$key]] : $node[$key])
						: [];

					$subTables = $this->collectJoinDependencies($childNodes);

					foreach ($subTables as $tbl => $variant) {
						if (!isset($tables[$tbl])) $tables[$tbl] = $variant;
					}
				}
			}
		}

		return $tables;
	}

	private static function isAssoc(array $array): bool {
		return array_keys($array) !== range(0, count($array) - 1);
	}

	/**
	 * Build JOIN-SQL based of scaned aliasUsage and resolved paths
	 */
	public function compileJoins(string $from, array $joinRequests): string {
		$sql = '';
		$visited = [$from];
		$aliasUsage = $this->aliasResolver->getAliasUsage();

		foreach ($joinRequests as $target => $variant) {
			if ($target === $from) continue;

			$paths = $this->joinGraph->findAllPaths($from, $target);
			if (empty($paths)) {
				throw new QueryValidationException("No join path from '$from' to '$target'");
			}

			// Fallback: bevorzugt "default"-Pfad
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
				$aliases = array_keys($aliasUsage[$table] ?? [$table => true]);

				foreach ($aliases as $alias) {
					$alreadyJoined = "{$table}::{$alias}";
					$joined = [];

					if (isset($joined[$alreadyJoined])) continue;
					$joined[$alreadyJoined] = true;

					$this->aliasResolver->registerAlias($alias, $table);

					$isDefault = $step['meta']['default'] ?? false;
					$stepType = strtoupper($step['meta']['type'] ?? 'INNER');
					$isLastStep = ($step === $lastStep);

					$useLeft = ($isLastStep && $variant && strtoupper($variant) === 'OPTIONAL')
						|| ($isLastStep && !$variant && $isDefault && $stepType === 'LEFT');

					$joinType = $useLeft ? 'LEFT JOIN' : 'INNER JOIN';

					$onParts = [];
					foreach ($step['meta']['on'] as $left => $right) {
						$onParts[] = $this->quoteJoinField($left) . ' = ' . $this->quoteJoinField($right, $alias);
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

	private function quoteJoinField(string $str, ?string $aliasOverride = null): string {
		if (str_contains($str, '.')) {
			[$table, $field] = explode('.', $str, 2);
			$alias = $aliasOverride ?? $this->aliasResolver->getAliasForTable($table) ?? $table;
			return $this->elementCompiler->quoteIdentifier($alias) . '.' . $this->elementCompiler->quoteIdentifier($field);
		}
		return $this->elementCompiler->quoteIdentifier($aliasOverride ?? $str);
	}
}

