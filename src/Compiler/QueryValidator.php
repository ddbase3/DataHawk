<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Exception\QueryValidationException;

class QueryValidator
{
    public function validate(array $query): void
    {
        if (($query['type'] ?? null) !== 'select') {
            throw new QueryValidationException("Unsupported query type: " . ($query['type'] ?? '[not defined]'));
        }

        if (empty($query['fields']) || !is_array($query['fields'])) {
            throw new QueryValidationException("Query must contain a non-empty 'fields' array.");
        }

        foreach ($query['order_by'] ?? [] as $order) {
            if (!isset($order['element'])) {
                throw new QueryValidationException("Missing element in order_by clause.");
            }

            $dir = strtoupper($order['direction'] ?? 'ASC');
            if (!in_array($dir, ['ASC', 'DESC'], true)) {
                throw new QueryValidationException("Invalid order direction: $dir");
            }
        }
    }
}

