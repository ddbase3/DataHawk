<?php declare(strict_types=1);

namespace DataHawk\Dto;

class QueryResult
{
    /**
     * @param array $columns Array of column metadata: [['name' => string, 'type' => string], ...]
     * @param array $rows Array of rows: each row is a list of values or associative array
     */
    public function __construct(
        public array $columns,
        public array $rows
    ) {}
}

