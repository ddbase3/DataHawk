<?php declare(strict_types=1);

namespace DataHawk\Dto;

class ForeignKeyReference
{
    public function __construct(
        public string $table,
        public string $column
    ) {}
}

