<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use ResourceFoundation\Exception\QueryValidationException;

class ElementCompiler {

	public function __construct(
		private AliasResolver $aliasResolver,
		private object $compiler	// recursive access for subqueries
	) {}

	public function compileElement(mixed $element): string {
		// --- literals and scalars ---
		if (is_string($element) || is_numeric($element) || is_bool($element) || is_null($element)) {
			return $this->quoteLiteral($element);
		}

		// --- arrays without 'type' (list syntax) ---
		if (is_array($element) && !isset($element['type'])) {
			if (array_keys($element) === range(0, count($element) - 1)) {
				$compiled = array_map(fn($e) => $this->compileElement($e), $element);

				// Detect if structured element present (fld, fn, op, etc.)
				$hasStructured = array_reduce($element, fn($carry, $e) => $carry || (is_array($e) && isset($e['type'])), false);

				return $hasStructured
					? '(' . implode(', ', $compiled) . ')'
					: implode(', ', $compiled);
			}
			throw new QueryValidationException("Invalid array element structure: " . print_r($element, true));
		}

		if (!is_array($element) || !isset($element['type'])) {
			throw new QueryValidationException("Invalid element structure: " . print_r($element, true));
		}

		return match ($element['type']) {
			'fld'      => $this->compileField($element),
			'fn'       => $this->compileFunction($element),
			'windowfn' => $this->compileWindowFunction($element),
			'op'       => $this->compileOperation($element),
			'subquery' => $this->compileSubquery($element),
			'case'     => $this->compileCase($element),
			default    => throw new QueryValidationException("Unsupported element type: " . $element['type'])
		};
	}

	private function compileField(array $fld): string {
		$fieldName = $fld['field'] ?? null;
		if (!$fieldName) {
			throw new QueryValidationException("Field reference must contain a 'field' key.");
		}

		$field = $fieldName === '*' ? '*' : $this->quoteIdentifier($fieldName);

		// Table is optional in UNION context or subqueries
		$table = $fld['table'] ?? null;
		if (!$table) {
			return $field;
		}

		$alias = $fld['tablealias'] ?? $this->aliasResolver->getAliasForTable($table) ?? $table;
		return $this->quoteIdentifier($alias) . '.' . $field;
	}

	private function compileFunction(array $fn): string {
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
			if (!empty($fn['distinct'])) $sql .= 'DISTINCT ';
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

		$sql = $name . '(';
		if (!empty($fn['distinct'])) $sql .= 'DISTINCT ';
		$sql .= implode(', ', $args) . ')';
		return $sql;
	}

	private function compileWindowFunction(array $fn): string {
		if (!isset($fn['function'], $fn['params'])) {
			throw new QueryValidationException("Window function must have 'function' and 'params'.");
		}

		$name = strtoupper($fn['function']);
		$args = array_map(fn($param) => $this->compileElement($param), $fn['params']);

		$sql = $name . '(' . implode(', ', $args) . ') OVER';

		if (empty($fn['over'])) {
			$sql .= ' ()';
			return $sql;
		}

		$over = $fn['over'];
		$parts = [];

		if (!empty($over['partition_by'])) {
			$partitionFields = array_map(fn($e) => $this->compileElement($e), $over['partition_by']);
			$parts[] = 'PARTITION BY ' . implode(', ', $partitionFields);
		}

		if (!empty($over['order_by'])) {
			$orderFields = array_map(function ($e) {
				if (!is_array($e) || !isset($e['expression'])) {
					throw new QueryValidationException("Each ORDER BY entry must have an 'expression'.");
				}
				$expr = $this->compileElement($e['expression']);
				$direction = strtoupper($e['direction'] ?? 'ASC');
				if (!in_array($direction, ['ASC', 'DESC'])) {
					throw new QueryValidationException("ORDER BY direction must be ASC or DESC.");
				}
				return $expr . ' ' . $direction;
			}, $over['order_by']);
			$parts[] = 'ORDER BY ' . implode(', ', $orderFields);
		}

		$sql .= ' (' . implode(' ', $parts) . ')';
		return $sql;
	}

	private function compileOperation(array $op): string {
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

	private function compileSubquery(array $sub): string {
		if (!isset($sub['query'])) {
			throw new QueryValidationException("Subquery must have 'query'.");
		}
		$compiled = $this->compiler->compile($sub['query']);
		return '(' . $compiled->sql . ')';
	}

	private function compileCase(array $element): string {
		$cases = $element['cases'] ?? null;
		if (!$cases || !is_array($cases)) {
			throw new QueryValidationException("CASE expression must have a 'cases' array.");
		}

		$sql = 'CASE';
		foreach ($cases as $c) {
			if (empty($c['when'])) {
				throw new QueryValidationException("CASE entry missing 'when'.");
			}
			$when = $this->compileElement($c['when']);
			$then = array_key_exists('then', $c) ? $this->compileElement($c['then']) : 'NULL';
			$sql .= ' WHEN ' . $when . ' THEN ' . $then;
		}

		if (array_key_exists('else', $element)) {
			$sql .= ' ELSE ' . $this->compileElement($element['else']);
		}

		return $sql . ' END';
	}

	public function quoteIdentifier(string $str): string {
		return '`' . str_replace('`', '``', $str) . '`';
	}

	public function quoteLiteral(string|int|float|bool|null $value): string {
		if (is_null($value)) return 'NULL';
		if (is_bool($value)) return $value ? 'TRUE' : 'FALSE';
		return is_numeric($value)
			? (string)$value
			: "'" . str_replace("'", "''", (string)$value) . "'";
	}
}

