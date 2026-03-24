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

class AliasResolver {

	private array $tableAliases = [];   // alias => table
	private array $aliasUsage = [];     // table => alias[]
	private ?string $firstTableUsed = null;

	public function reset(): void {
		$this->tableAliases = [];
		$this->aliasUsage = [];
		$this->firstTableUsed = null;
	}

	public function scan(array $query): void {
		$this->reset();

		$nodes = array_merge(
			$query['fields'] ?? [],
			$query['group_by'] ?? [],
			$query['order_by'] ?? [],
			isset($query['where']) ? [$query['where']] : [],
			isset($query['having']) ? [$query['having']] : []
		);

		foreach ($nodes as $node) {
			$this->scanNode($node);
		}
	}

	private function scanNode(mixed $node): void {
		if (!is_array($node)) return;

		if (($node['type'] ?? null) === 'fld') {
			$table = $node['table'] ?? null;
			if (!$table) return;
			$alias = $node['tablealias'] ?? $table;

			$this->aliasUsage[$table][$alias] = true;

			if ($this->firstTableUsed === null) {
				$this->firstTableUsed = $table;
			}
		}

		foreach ($node as $child) {
			if (is_array($child)) {
				$this->scanNode($child);
			}
		}
	}

	public function getAliasUsage(): array {
		return $this->aliasUsage;
	}

	public function getFirstUsedTable(): ?string {
		return $this->firstTableUsed;
	}

	public function registerAlias(string $alias, string $table): void {
		$this->tableAliases[$alias] = $table;
		$this->aliasUsage[$table][$alias] = true;
	}

	public function getAliasForTable(string $table): ?string {
		foreach ($this->tableAliases as $alias => $mappedTable) {
			if ($mappedTable === $table) return $alias;
		}
		return null;
	}

	public function getTableForAlias(string $alias): ?string {
		return $this->tableAliases[$alias] ?? null;
	}

	public function getRegisteredAliases(): array {
		return $this->tableAliases;
	}
}

