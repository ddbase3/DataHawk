<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\ISchemaProvider;
use DataHawk\Api\IQueryCompiler;
use DataHawk\Exception\QueryValidationException;
use DataHawk\Dto\SqlQuery;
use DataHawk\Util\Graph;

class QueryCompiler implements IQueryCompiler
{
    private Graph $joinGraph;
    private array $tableAliases = []; // alias => table
    private array $aliasUsage = [];   // table => alias[]

    public function __construct(
        private ISchemaProvider $schemaprovider
    ) {
        $this->joinGraph = new Graph();

        foreach ($this->schemaprovider->getSchema() as $table) {
            $this->joinGraph->addNode($table->name);
            foreach ($table->joins as $join) {
                $meta = $join->meta;
                $meta['on'] = $join->on;
                $meta['type'] = $join->type;

                $label = !empty($meta['default']) ? 'default' : uniqid('join_', true);

                $this->joinGraph->addEdge(
                    $table->name,
                    $join->targetTable,
                    $label,
                    $meta
                );
            }
        }
    }

    public function compile(array $query): SqlQuery
    {
        $this->aliasUsage = [];
        $this->tableAliases = [];

        if (!isset($query['select'], $query['from'])) {
            throw new QueryValidationException("Query must contain 'select' and 'from'.");
        }

        $this->collectAliasUsage($query);

        $from = $query['from'];

        $joinRequests = [];
        $elementSources = array_merge(
            $query['select'] ?? [],
            $query['group_by'] ?? [],
            $query['order_by'] ?? [],
            isset($query['where']) ? [$query['where']] : [],
            isset($query['having']) ? [$query['having']] : []
        );
        $this->collectJoinDependencies($elementSources, $joinRequests);

        $selectParts = [];
        foreach ($query['select'] as $entry) {
            if (!isset($entry['element'])) {
                throw new QueryValidationException("Missing element in select entry.");
            }
            $sqlExpr = $this->compileElement($entry['element']);
            if (isset($entry['alias'])) {
                $sqlExpr .= ' AS ' . $this->quoteIdentifier($entry['alias']);
            }
            $selectParts[] = $sqlExpr;
        }

        $sql = 'SELECT ' . implode(', ', $selectParts);
        $sql .= ' FROM ' . $this->quoteIdentifier($from);
        $sql .= $this->compileJoins($from, $joinRequests);

        if (isset($query['where'])) {
            $sql .= ' WHERE ' . $this->compileElement($query['where']);
        }

        if (isset($query['group_by']) && is_array($query['group_by'])) {
            $groupParts = array_map(fn($el) => $this->compileElement($el), $query['group_by']);
            $sql .= ' GROUP BY ' . implode(', ', $groupParts);
        }

        if (isset($query['having'])) {
            $sql .= ' HAVING ' . $this->compileElement($query['having']);
        }

        if (isset($query['order_by']) && is_array($query['order_by'])) {
            $orderParts = [];
            foreach ($query['order_by'] as $order) {
                if (!isset($order['element'])) {
                    throw new QueryValidationException("Missing element in order_by clause.");
                }
                $expr = $this->compileElement($order['element']);
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

        return new SqlQuery($sql);
    }

    private function collectAliasUsage(array $query): void
    {
        $nodes = array_merge(
            $query['select'] ?? [],
            $query['group_by'] ?? [],
            $query['order_by'] ?? [],
            isset($query['where']) ? [$query['where']] : [],
            isset($query['having']) ? [$query['having']] : []
        );

        foreach ($nodes as $node) {
            $this->scanForAliases($node);
        }
    }

    private function scanForAliases(mixed $node): void
    {
        if (!is_array($node)) return;

        if (isset($node['type']) && $node['type'] === 'fld') {
            $table = $node['table'];
            $alias = $node['tablealias'] ?? $table;
            $this->aliasUsage[$table][$alias] = true;
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $this->scanForAliases($child);
            }
        }
    }

/*
    private function collectJoinDependencies(array $nodes, array &$tables): void
    {
        foreach ($nodes as $node) {
            if (is_array($node)) {
                if (isset($node['type']) && $node['type'] === 'fld') {
                    $table = $node['table'];
                    $alias = $node['tablealias'] ?? $table;
                    $this->aliasUsage[$table][$alias] = true;

                    if (!empty($node['variant'])) {
                        $tables[$table] = $node['variant'];
                    }
                }
                if (isset($node['element'])) {
                    $this->collectJoinDependencies([$node['element']], $tables);
                }
                if (isset($node['params']) && is_array($node['params'])) {
                    $this->collectJoinDependencies($node['params'], $tables);
                }
            }
        }
    }
 */

    private function collectJoinDependencies(array $nodes, array &$tables): void
    {
        foreach ($nodes as $node) {
            if (is_array($node)) {
                if (isset($node['type']) && $node['type'] === 'fld') {
                    $table = $node['table'];
                    $alias = $node['tablealias'] ?? $table;
if (!isset($this->aliasUsage[$table])) {
    $this->aliasUsage[$table] = [];
}
$this->aliasUsage[$table][$alias] = true;
		    $tables[$table] = $node['variant'] ?? ($tables[$table] ?? null);
                }
                if (isset($node['element'])) {
                    $this->collectJoinDependencies([$node['element']], $tables);
                }
                if (isset($node['params']) && is_array($node['params'])) {
                    $this->collectJoinDependencies($node['params'], $tables);
                }
                if (isset($node['query']) && is_array($node['query'])) {
                    $this->collectJoinDependencies([$node['query']], $tables);
                }
            }
        }
    }

    private function compileJoins(string $from, array $joinRequests): string
    {
        $sql = '';
        $visited = [$from];

        foreach ($joinRequests as $target => $variant) {
            if ($target === $from) continue;

            $paths = $this->joinGraph->findAllPaths($from, $target);
            if (empty($paths)) {
                throw new QueryValidationException("No join path from '$from' to '$target'");
            }

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

                $aliases = array_keys($this->aliasUsage[$table] ?? [$table => true]);
                foreach ($aliases as $alias) {
                    if (isset($this->tableAliases[$alias])) continue;

                    $this->tableAliases[$alias] = $table;

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

                    $sql .= " $joinType " . $this->quoteIdentifier($table);
                    if ($alias !== $table) {
                        $sql .= " AS " . $this->quoteIdentifier($alias);
                    }
                    $sql .= " ON " . implode(" AND ", $onParts);
                }
            }
        }

        return $sql;
    }

    private function quoteJoinField(string $str, ?string $aliasOverride = null): string
    {
        if (str_contains($str, '.')) {
            [$table, $field] = explode('.', $str, 2);
            $alias = $aliasOverride ?? $this->getAliasForTable($table) ?? $table;
            return $this->quoteIdentifier($alias) . '.' . $this->quoteIdentifier($field);
        }
        return $this->quoteIdentifier($aliasOverride ?? $str);
    }

    private function compileElement($element): string
    {
        if (is_string($element) || is_numeric($element)) {
            return $this->quoteLiteral($element);
        }

        if (!is_array($element) || !isset($element['type'])) {
            throw new QueryValidationException("Invalid element structure.");
        }

        return match ($element['type']) {
            'fld' => $this->compileField($element),
            'fn'  => $this->compileFunction($element),
            'op'  => $this->compileOperation($element),
            'subquery' => $this->compileSubquery($element),
            default => throw new QueryValidationException("Unsupported element type: " . $element['type'])
        };
    }

    private function compileField(array $fld): string
    {
        $alias = $fld['tablealias'] ?? $this->getAliasForTable($fld['table']) ?? $fld['table'];
        $column = $fld['field'];
        return $this->quoteIdentifier($alias) . '.' . $this->quoteIdentifier($column);
    }

    private function compileFunction(array $fn): string
    {
        if (!isset($fn['function'], $fn['params'])) {
            throw new QueryValidationException("Function must have 'function' and 'params'.");
        }

        $args = array_map(fn($param) => $this->compileElement($param), $fn['params']);
        return strtoupper($fn['function']) . '(' . implode(', ', $args) . ')';
    }

    private function compileOperation(array $op): string
    {
        $opName = strtoupper($op['operator'] ?? '');
        $params = $op['params'] ?? [];

        return match ($opName) {
            'IS NULL', 'IS NOT NULL' => $this->compileElement($params[0]) . ' ' . $opName,
            'BETWEEN' => $this->compileElement($params[0]) . ' BETWEEN ' . $this->compileElement($params[1]) . ' AND ' . $this->compileElement($params[2]),
            'IN', 'NOT IN' => $this->compileElement($params[0]) . ' ' . $opName . ' (' . implode(', ', array_map(fn($p) => $this->compileElement($p), array_slice($params, 1))) . ')',
            'EXISTS', 'NOT EXISTS' => $opName . ' (' . $this->compileElement($params[0]) . ')',
            default => '(' . implode(' ' . $op['operator'] . ' ', array_map(fn($p) => $this->compileElement($p), $params)) . ')'
        };
    }

    private function compileSubquery(array $sub): string
    {
        if (!isset($sub['query'])) {
            throw new QueryValidationException("Subquery must have 'query'.");
        }

        $compiled = $this->compile($sub['query']);
        return '(' . $compiled->sql . ')';
    }

    private function quoteIdentifier(string $str): string
    {
        return '`' . str_replace('`', '``', $str) . '`';
    }

    private function getAliasForTable(string $table): ?string
    {
        foreach ($this->tableAliases as $alias => $mappedTable) {
            if ($mappedTable === $table) return $alias;
        }
        return null;
    }

    private function quoteLiteral(string|int|float|bool|null $value): string
    {
        if (is_null($value)) return 'NULL';
        if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
        return is_numeric($value) ? (string)$value : "'" . str_replace("'", "''", (string)$value) . "'";
    }
}

