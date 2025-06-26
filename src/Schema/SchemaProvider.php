<?php declare(strict_types=1);

namespace DataHawk\Schema;

use DataHawk\Api\ISchemaProvider;
use DataHawk\Dto\TableMetadata;
use DataHawk\Dto\FieldMetadata;
use DataHawk\Dto\JoinMetadata;

class SchemaProvider implements ISchemaProvider
{
    /**
     * Returns all defined tables.
     *
     * @return TableMetadata[]
     */
    public function getSchema(): array
    {
        return [
            $this->getPackagistHandleTable(),
            $this->getPackagistPackageTable()
        ];
    }

    /**
     * Returns a specific table by name, or null if not found.
     *
     * @param string $tableName
     * @return TableMetadata|null
     */
    public function getTable(string $tableName): ?TableMetadata
    {
        foreach ($this->getSchema() as $table) {
            if ($table->name === $tableName) {
                return $table;
            }
        }
        return null;
    }

    private function getPackagistHandleTable(): TableMetadata
    {
        return new TableMetadata(
            name: 'packagist_handle',
            label: 'Packagist Handle',
            description: 'Information about vendor handles in Packagist',
            domain: 'packagist',
            category: 'metadata',
            tags: ['handle', 'vendor', 'namespace'],
            fields: [
                new FieldMetadata('id', 'integer', 'Primary key', true),
                new FieldMetadata('name', 'string', 'Vendor name'),
                new FieldMetadata('lastcall', 'datetime', 'Last API call timestamp')
            ],
            joins: [],
            defaultFilters: []
        );
    }

    private function getPackagistPackageTable(): TableMetadata
    {
        return new TableMetadata(
            name: 'packagist_package',
            label: 'Packagist Package',
            description: 'Individual packages listed in Packagist under a handle',
            domain: 'packagist',
            category: 'metrics',
            tags: ['package', 'downloads', 'repository'],
            fields: [
                new FieldMetadata('id', 'integer', 'Primary key', true),
                new FieldMetadata('handle_id', 'integer', 'Foreign key to handle'),
                new FieldMetadata('name', 'string', 'Package name'),
                new FieldMetadata('url', 'string', 'Package URL'),
                new FieldMetadata('description', 'text', 'Package description'),
                new FieldMetadata('downloads', 'integer', 'Total downloads'),
                new FieldMetadata('downloads_monthly', 'integer', 'Downloads this month'),
                new FieldMetadata('downloads_daily', 'integer', 'Downloads today'),
                new FieldMetadata('favers', 'integer', 'Number of favorites'),
                new FieldMetadata('repository', 'string', 'Repository URL')
            ],
            joins: [
                new JoinMetadata(
                    targetTable: 'packagist_handle',
                    on: ['packagist_package.handle_id' => 'packagist_handle.id'],
                    type: 'INNER'
                ),
                new JoinMetadata(
                    targetTable: 'packagist_handle',
                    on: ['packagist_package.handle_id' => 'packagist_handle.id'],
                    type: 'LEFT',
                    meta: ['default' => true]
                )
            ],
            defaultFilters: []
        );
    }
}

