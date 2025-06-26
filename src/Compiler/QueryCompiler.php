<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\ISchemaProvider;
use DataHawk\Api\IQueryCompiler;
use DataHawk\Exception\QueryValidationException;
use DataHawk\Dto\SqlQuery;

class QueryCompiler implements IQueryCompiler
{
    public function __construct(
        private ISchemaProvider $schemaprovider
    ) {}

    public function compile(array $query): SqlQuery
    {
        // Validate required parts
        if (!isset($query['select'], $query['from'])) {
            throw new QueryValidationException("Query must contain 'select' and 'from'.");
        }

        $from = $query['from'];
        $tableMeta = $this->schemaprovider->getTable($from);
        if (!$tableMeta) {
            throw new QueryValidationException("Unknown table: $from");
        }

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

        // WHERE clause
        if (isset($query['where'])) {
            $where = $this->compileElement($query['where']);
            $sql .= ' WHERE ' . $where;
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

    private function quoteLiteral(string|int|float $value): string
    {
        return is_numeric($value) ? (string)$value : "'" . str_replace("'", "''", (string)$value) . "'";
    }
}

