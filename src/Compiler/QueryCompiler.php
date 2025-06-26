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

    public function __construct(
        private ISchemaProvider $schemaprovider
    ) {
        $this->joinGraph = new Graph();

        foreach ($this->schemaprovider->getSchema() as $table) {
            $this->joinGraph->addNode($table->name);
            foreach ($table->joins as $join) {
                $this->joinGraph->addEdge(
                    $table->name,
                    $join->targetTable,
                    'default',
                    ['on' => $join->on, 'type' => $join->type]
                );
            }
        }
    }

    public function compile(array $query): SqlQuery
    {
        if (!isset($query['select'], $query['from'])) {
            throw new QueryValidationException("Query must contain 'select' and 'from'.");
        }

        $from = $query['from'];
        $tableMeta = $this->schemaprovider->getTable($from);
        if (!$tableMeta) {
            throw new QueryValidationException("Unknown table: $from");
        }

        // Collect JOIN dependencies
        $joinRequests = [];
        $elementSources = array_merge(
            $query['select'] ?? [],
            $query['group_by'] ?? [],
            $query['order_by'] ?? [],
            isset($query['where']) ? [$query['where']] : [],
            isset($query['having']) ? [$query['having']] : []
        );
        $this->collectJoinDependencies($elementSources, $joinRequests);

        // SELECT clause
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

        // JOINs
        $sql .= $this->compileJoins($from, $joinRequests);

        // WHERE
        if (isset($query['where'])) {
            $sql .= ' WHERE ' . $this->compileElement($query['where']);
        }

        // GROUP BY
        if (isset($query['group_by']) && is_array($query['group_by'])) {
            $groupParts = array_map(fn($el) => $this->compileElement($el), $query['group_by']);
            $sql .= ' GROUP BY ' . implode(', ', $groupParts);
        }

        // HAVING
        if (isset($query['having'])) {
            $sql .= ' HAVING ' . $this->compileElement($query['having']);
        }

        // ORDER BY
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

        // LIMIT / OFFSET
        if (isset($query['limit'])) {
            $sql .= ' LIMIT ' . (int)$query['limit'];
        }
        if (isset($query['offset'])) {
            $sql .= ' OFFSET ' . (int)$query['offset'];
        }

        return new SqlQuery($sql);
    }

    private function collectJoinDependencies(array $nodes, array &$tables): void
    {
        foreach ($nodes as $node) {
            if (is_array($node)) {
                if (isset($node['type']) && $node['type'] === 'fld' && !empty($node['variant'])) {
                    $tables[$node['table']] = $node['variant'];
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

            $path = $paths[0];
            foreach ($path as $step) {
                if (in_array($step['to'], $visited, true)) continue;
                $visited[] = $step['to'];

                $joinType = strtoupper($variant) === 'OPTIONAL' ? 'LEFT JOIN' : 'INNER JOIN';
                $onParts = [];
                foreach ($step['meta']['on'] as $left => $right) {
                    $onParts[] = $this->quoteTableField($left) . ' = ' . $this->quoteTableField($right);
                }
                $sql .= " $joinType " . $this->quoteIdentifier($step['to']) . " ON " . implode(" AND ", $onParts);
            }
        }

        return $sql;
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
            default => throw new QueryValidationException("Unsupported element type: " . $element['type'])
        };
    }

    private function compileField(array $fld): string
    {
        $alias = $fld['tablealias'] ?? $fld['table'];
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
        if (!isset($op['operator'], $op['params'])) {
            throw new QueryValidationException("Operation must have 'operator' and 'params'.");
        }

        $params = array_map(fn($p) => $this->compileElement($p), $op['params']);
        return '(' . implode(' ' . $op['operator'] . ' ', $params) . ')';
    }

    private function quoteIdentifier(string $str): string
    {
        return '`' . str_replace('`', '``', $str) . '`';
    }

    private function quoteTableField(string $str): string
    {
        if (str_contains($str, '.')) {
            [$table, $field] = explode('.', $str, 2);
            return $this->quoteIdentifier($table) . '.' . $this->quoteIdentifier($field);
        }
        return $this->quoteIdentifier($str);
    }

    private function quoteLiteral(string|int|float|bool $value): string
    {
        if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
        return is_numeric($value) ? (string)$value : "'" . str_replace("'", "''", (string)$value) . "'";
    }
}

