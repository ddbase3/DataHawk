<?php declare(strict_types=1);

namespace DataHawk\Dto;

class TableMetadata
{
    public function __construct(
        public string $name,
        public ?string $label = null,
        public ?string $description = null,
        public string $domain = '',
        public string $category = '',
        public array $tags = [],
        public array $fields = [],           // FieldMetadata[]
        public array $joins = [],            // JoinMetadata[]
        public array $defaultFilters = []    // FilterCondition[]
    ) {}
}

