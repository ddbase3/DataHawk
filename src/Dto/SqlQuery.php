<?php declare(strict_types=1);

namespace DataHawk\Dto;

class SqlQuery
{
    public function __construct(
        public string $sql,
        public array $params = []
    ) {}
}

