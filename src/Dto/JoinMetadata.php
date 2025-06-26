<?php declare(strict_types=1);

namespace DataHawk\Dto;

class JoinMetadata
{
    public function __construct(
        public string $targetTable,
        public array $on,
        public string $type = 'INNER',
        public array $meta = [] // e.g. ['default' => true]
    ) {}
}

