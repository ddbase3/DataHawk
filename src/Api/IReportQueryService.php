<?php declare(strict_types=1);

namespace DataHawk\Api;

use DataHawk\Dto\TableMetadata;
use DataHawk\Dto\QueryResult;

interface IReportQueryService
{
    /**
     * Returns the list of all visible tables for the current user.
     *
     * @return TableMetadata[]
     */
    public function listTables(): array;

    /**
     * Returns the metadata of a specific table if accessible by the current user.
     *
     * @param string $tableName
     * @return TableMetadata|null
     */
    public function getTable(string $tableName): ?TableMetadata;

    /**
     * Executes a structured JSON-based query and returns the result,
     * only if the current user has permission to access the requested data.
     *
     * @param array $queryJson
     * @return QueryResult
     *
     * @throws \DataHawk\Exception\AccessDeniedException
     * @throws \DataHawk\Exception\QueryValidationException
     */
    public function executeQuery(array $queryJson): QueryResult;

    /**
     * Returns the list of all known domains that occur
     * within the schema visible to the current user.
     *
     * @return string[]
     */
    public function listDomains(): array;

    /**
     * Returns the list of all known categories found
     * in the user's accessible schema.
     *
     * @return string[]
     */
    public function listCategories(): array;

    /**
     * Returns all tags used across visible tables and fields for the current user.
     *
     * @return string[]
     */
    public function listTags(): array;
}

