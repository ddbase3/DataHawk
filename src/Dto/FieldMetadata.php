<?php declare(strict_types=1);

namespace DataHawk\Dto;

class FieldMetadata
{
    public function __construct(
        public string $name,
        public string $type,
        public ?string $description = null,
        public bool $primaryKey = false,
        public ?ForeignKeyReference $foreignKey = null,
        public bool $nullable = true,
        public array $tags = [],
        public ?string $alias = null,
	public bool $sensitive = false
    ) {}
}

