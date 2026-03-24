<?php

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

namespace DataHawk\Util;

/**
 * A simple directed graph with labeled edges and optional metadata.
 *
 * Nodes are strings. Edges go from one node to another, with a label and metadata.
 * Supports finding all paths and selecting a default edge (relation) among multiple edges.
 */
class Graph
{
	/**
	 * Adjacency list.
	 * Format:
	 * [
	 *   'NodeA' => [
	 *     ['to' => 'NodeB', 'label' => 'relationName', 'meta' => [...]],
	 *     ...
	 *   ],
	 *   ...
	 * ]
	 *
	 * @var array<string, list<array{to: string, label: string, meta: array}>>
	 */
	protected array $adjacency = [];

	/**
	 * Adds a node to the graph.
	 *
	 * @param string $node
	 * @return void
	 */
	public function addNode(string $node): void
	{
		if (!isset($this->adjacency[$node])) {
			$this->adjacency[$node] = [];
		}
	}

	/**
	 * Adds a directed edge from $from to $to with a label and optional metadata.
	 * Metadata can contain a key 'default' (bool) to mark the edge as default among multiple edges.
	 *
	 * @param string $from
	 * @param string $to
	 * @param string $label
	 * @param array $meta
	 * @return void
	 */
	public function addEdge(string $from, string $to, string $label, array $meta = []): void
	{
		$this->addNode($from);
		$this->addNode($to);

		$this->adjacency[$from][] = [
			'to' => $to,
			'label' => $label,
			'meta' => $meta,
		];
	}

	/**
	 * Returns all nodes in the graph.
	 *
	 * @return string[]
	 */
	public function getNodes(): array
	{
		return array_keys($this->adjacency);
	}

	/**
	 * Returns all outgoing edges from a node.
	 *
	 * @param string $node
	 * @return list<array{to: string, label: string, meta: array}>
	 */
	public function getEdges(string $node): array
	{
		return $this->adjacency[$node] ?? [];
	}

	/**
	 * Returns all edges in the graph as flat list.
	 *
	 * @return list<array{from: string, to: string, label: string, meta: array}>
	 */
	public function getAllEdges(): array
	{
		$all = [];

		foreach ($this->adjacency as $from => $edges) {
			foreach ($edges as $edge) {
				$all[] = [
					'from' => $from,
					'to' => $edge['to'],
					'label' => $edge['label'],
					'meta' => $edge['meta'],
				];
			}
		}

		return $all;
	}

	/**
	 * Checks whether a node exists.
	 *
	 * @param string $node
	 * @return bool
	 */
	public function hasNode(string $node): bool
	{
		return isset($this->adjacency[$node]);
	}

	/**
	 * Returns all paths from $start to $end as lists of edges.
	 *
	 * Each path is an ordered list of edges (array with keys: from, to, label, meta).
	 *
	 * @param string $start
	 * @param string $end
	 * @return list<list<array{from: string, to: string, label: string, meta: array}>>
	 */
	public function findAllPaths(string $start, string $end): array
	{
		if (!$this->hasNode($start)) {
			throw new \RuntimeException("Start node '$start' does not exist.");
		}
		if (!$this->hasNode($end)) {
			throw new \RuntimeException("End node '$end' does not exist.");
		}

		if ($start === $end) {
			return [[]]; // one empty path (no steps needed)
		}

		$results = [];
		$this->dfs($start, $end, [], [], $results);
		return $results;
	}

	/**
	 * Depth-first search helper for finding all paths.
	 *
	 * @param string $current
	 * @param string $end
	 * @param array<string,bool> $visited
	 * @param list<array{from: string, to: string, label: string, meta: array}> $path
	 * @param list<list<array{from: string, to: string, label: string, meta: array}>> &$results
	 */
	protected function dfs(string $current, string $end, array $visited, array $path, array &$results): void
	{
		if (isset($visited[$current])) {
			return; // prevent cycles
		}

		$visited[$current] = true;

		foreach ($this->getEdges($current) as $edge) {
			$step = [
				'from' => $current,
				'to' => $edge['to'],
				'label' => $edge['label'],
				'meta' => $edge['meta'],
			];

			$newPath = [...$path, $step];

			if ($edge['to'] === $end) {
				$results[] = $newPath;
			} else {
				$this->dfs($edge['to'], $end, $visited, $newPath, $results);
			}
		}
	}

	/**
	 * Finds all edges from $from to $to.
	 *
	 * @param string $from
	 * @param string $to
	 * @return list<array{to: string, label: string, meta: array}>
	 */
	public function getEdgesTo(string $from, string $to): array
	{
		return array_filter(
			$this->getEdges($from),
			fn($edge) => $edge['to'] === $to
		);
	}

	/**
	 * Finds the default edge from $from to $to.
	 * If multiple edges exist, tries to find one with meta['default'] === true.
	 * Otherwise returns the first edge or null if none exists.
	 *
	 * @param string $from
	 * @param string $to
	 * @return array{to: string, label: string, meta: array}|null
	 */
	public function getDefaultEdge(string $from, string $to): ?array
	{
		$edges = $this->getEdgesTo($from, $to);
		if (empty($edges)) {
			return null;
		}

		// Try to find edge marked as default
		foreach ($edges as $edge) {
			if (!empty($edge['meta']['default'])) {
				return $edge;
			}
		}

		// Return first edge as fallback
		return reset($edges);
	}
}

