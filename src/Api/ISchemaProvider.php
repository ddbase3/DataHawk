<?php declare(strict_types=1);

namespace DataHawk\Api;

use DataHawk\Dto\TableMetadata;

interface ISchemaProvider
{
    /**
     * Returns the full schema definition.
     *
     * @return TableMetadata[]
     */
    public function getSchema(): array;

    /**
     * Returns a single table definition by name, or null if not found.
     *
     * @param string $tableName
     * @return TableMetadata|null
     */
    public function getTable(string $tableName): ?TableMetadata;
}

