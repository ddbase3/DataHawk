<?php declare(strict_types=1);

namespace DataHawk\Service;

use Base3\Api\IContainer;
use Base3\Database\Api\IDatabase;
use DataHawk\Api\IReportQueryService;
use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Api\IReportQueryCompiler;
use DataHawk\Dto\TableMetadata;
use DataHawk\Dto\QueryResult;
use DataHawk\Exception\AccessDeniedException;
use DataHawk\Exception\QueryValidationException;

class DefaultReportQueryService implements IReportQueryService
{
    private IDatabase $database;

    public function __construct(
        private IReportSchemaProvider $schemaProvider,
        private IReportQueryCompiler $querycompiler,
        private IContainer $container
    ) {
        $this->database = $container->get('database');
    }

    /**
     * @return TableMetadata[]
     */
    public function listTables(): array
    {
        return $this->schemaProvider->getSchema();
    }

    /**
     * @param string $tableName
     * @return TableMetadata|null
     */
    public function getTable(string $tableName): ?TableMetadata
    {
        return $this->schemaProvider->getTable($tableName);
    }

    /**
     * @param array $queryJson
     * @return QueryResult
     * @throws AccessDeniedException
     * @throws QueryValidationException
     */
    public function executeQuery(array $queryJson): QueryResult
    {
        // 1. Compile query into SQL string and metadata
        $sqlQuery = $this->querycompiler->compile($queryJson);

        // 2. Run query via IDatabase service
        try {
            $this->database->connect();
            $rows = $this->database->multiQuery($sqlQuery->sql, $sqlQuery->params);
        } catch (\Throwable $e) {
            return new QueryResult([], [], $sqlQuery->sql . "\n\n❌ DB Error: " . $e->getMessage());
        }

        // 3. check integrity
        if (count($rows)) {
            $compiledFields = $sqlQuery->fields;
            $firstRow = $rows[0] ?? [];
            $expectedKeys = array_map(fn($f) => $f['alias'] ?? $f['name'], $compiledFields);
            $actualKeys = array_keys($firstRow);
            if (count($expectedKeys) !== count($actualKeys) || array_diff($expectedKeys, $actualKeys)) {
                throw new \RuntimeException("Query result column mismatch: expected [" . implode(', ', $expectedKeys) . "], got [" . implode(', ', $actualKeys) . "]");
            }
        }

        // 4. Build columns using aliases from compiled fields
        $columns = [];
        $firstRow = $rows[0] ?? [];
        $compiledFields = $sqlQuery->fields;

        foreach (array_keys($firstRow) as $i => $colName) {
            $field = $compiledFields[$i] ?? null;

            $name = $field['alias'] ?? $field['name'] ?? $colName;
            $type = gettype($firstRow[$colName]);

            $columns[] = [
                'name' => $name,
                'type' => $type,
                'field' => $field['name'] ?? null,
                'alias' => $field['alias'] ?? null,
		'table' => $field['table'] ?? null,
		'sensitive' => $field['sensitive'] ?? false,
            ];
        }

	$isSensitive = in_array(true, array_column($columns, 'sensitive'), true);

        return new QueryResult($columns, $rows, $sqlQuery->sql, $isSensitive);
    }

    /**
     * @return string[]
     */
    public function listDomains(): array
    {
        $tables = $this->schemaProvider->getSchema();
        $domains = array_map(fn(TableMetadata $t) => $t->domain ?? '', $tables);
        return array_values(array_unique(array_filter($domains)));
    }

    /**
     * @return string[]
     */
    public function listCategories(): array
    {
        $tables = $this->schemaProvider->getSchema();
        $categories = array_map(fn(TableMetadata $t) => $t->category ?? '', $tables);
        return array_values(array_unique(array_filter($categories)));
    }

    /**
     * @return string[]
     */
    public function listTags(): array
    {
        $tables = $this->schemaProvider->getSchema();
        $allTags = [];

        foreach ($tables as $table) {
            $allTags = array_merge($allTags, $table->tags);
            foreach ($table->fields as $field) {
                $allTags = array_merge($allTags, $field->tags ?? []);
            }
        }

        return array_values(array_unique(array_filter($allTags)));
    }
}

