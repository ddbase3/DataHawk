<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Exception\QueryValidationException;

class ElementCompiler
{
    public function __construct(
        private AliasResolver $aliasResolver,
        private MysqlReportQueryCompiler $compiler // rekursiver Zugriff für Subqueries
    ) {}

    public function compileElement(mixed $element): string
    {
        if (is_string($element) || is_numeric($element) || is_bool($element) || is_null($element)) {
            return $this->quoteLiteral($element);
        }

        if (!is_array($element) || !isset($element['type'])) {
            throw new QueryValidationException("Invalid element structure: " . print_r($element, true));
        }

        return match ($element['type']) {
            'fld'      => $this->compileField($element),
            'fn'       => $this->compileFunction($element),
            'op'       => $this->compileOperation($element),
            'subquery' => $this->compileSubquery($element),
            default    => throw new QueryValidationException("Unsupported element type: " . $element['type'])
        };
    }

    private function compileField(array $fld): string
    {
        $alias = $fld['tablealias'] ?? $this->aliasResolver->getAliasForTable($fld['table']) ?? $fld['table'];
        return $this->quoteIdentifier($alias) . '.' . $this->quoteIdentifier($fld['field']);
    }

    private function compileFunction(array $fn): string
    {
        if (!isset($fn['function'], $fn['params'])) {
            throw new QueryValidationException("Function must have 'function' and 'params'.");
        }

        $name = strtoupper($fn['function']);
        $args = array_map(fn($param) => $this->compileElement($param), $fn['params']);

        if ($name === 'GROUP_CONCAT') {
            if (count($args) === 0 || count($args) > 2) {
                throw new QueryValidationException("GROUP_CONCAT expects 1 or 2 parameters.");
            }

            $sql = 'GROUP_CONCAT(';

            if (!empty($fn['distinct'])) {
                $sql .= 'DISTINCT ';
            }

            $sql .= $args[0];

            if (!empty($fn['params'][1])) {
                $sepLiteral = $fn['params'][1];
                if (!is_string($sepLiteral)) {
                    throw new QueryValidationException("GROUP_CONCAT separator must be a string literal.");
                }
                $sql .= ' SEPARATOR ' . $this->quoteLiteral($sepLiteral);
            }

            $sql .= ')';
            return $sql;
        }

        if ($name === 'CAST') {
            if (count($args) !== 2 || !is_string($fn['params'][1])) {
                throw new QueryValidationException("CAST expects 2 parameters: value and type string.");
            }
            return "CAST(" . $args[0] . " AS " . strtoupper(trim($fn['params'][1])) . ")";
        }

        if ($name === 'CONVERT') {
            if (count($args) !== 2 || !is_string($fn['params'][1])) {
                throw new QueryValidationException("CONVERT expects 2 parameters: value and charset string.");
            }
            return "CONVERT(" . $args[0] . " USING " . strtoupper(trim($fn['params'][1])) . ")";
        }

        return $name . '(' . implode(', ', $args) . ')';
    }

    private function compileOperation(array $op): string
    {
        $opName = strtoupper($op['operator'] ?? '');
        $params = $op['params'] ?? [];

        return match ($opName) {
            'IS NULL', 'IS NOT NULL' =>
                $this->compileElement($params[0]) . ' ' . $opName,

            'BETWEEN' =>
                $this->compileElement($params[0]) . ' BETWEEN ' .
                $this->compileElement($params[1]) . ' AND ' .
                $this->compileElement($params[2]),

            'IN', 'NOT IN' =>
                $this->compileElement($params[0]) . ' ' . $opName . ' (' .
                implode(', ', array_map(fn($p) => $this->compileElement($p), array_slice($params, 1))) . ')',

            'EXISTS', 'NOT EXISTS' =>
                $opName . ' (' . $this->compileElement($params[0]) . ')',

            default =>
                '(' . implode(' ' . $op['operator'] . ' ', array_map(fn($p) => $this->compileElement($p), $params)) . ')'
        };
    }

    private function compileSubquery(array $sub): string
    {
        if (!isset($sub['query'])) {
            throw new QueryValidationException("Subquery must have 'query'.");
        }

        $compiled = $this->compiler->compile($sub['query']);
        return '(' . $compiled->sql . ')';
    }

    public function quoteIdentifier(string $str): string
    {
        return '`' . str_replace('`', '``', $str) . '`';
    }

    public function quoteLiteral(string|int|float|bool|null $value): string
    {
        if (is_null($value)) return 'NULL';
        if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
        return is_numeric($value) ? (string)$value : "'" . str_replace("'", "''", (string)$value) . "'";
    }
}

