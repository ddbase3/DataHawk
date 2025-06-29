<?php declare(strict_types=1);

namespace DataHawk\Dto;

class QueryResult
{
    /**
     * @param array $columns Array of column metadata, e.g.:
     *   [
     *     [
     *       'name' => string,      // preferred display name (alias or field)
     *       'type' => string,      // PHP type from result set
     *       'field' => ?string,    // original field name (optional)
     *       'alias' => ?string,    // SQL alias if used
     *       'table' => ?string     // table source if known
     *     ],
     *     ...
     *   ]
     * @param array $rows Array of result rows: associative arrays
     * @param string|null $debugSql Optional debug SQL string
     * @param bool $sensitive Result contains sensitive data
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public ?string $debugSql = null,
        public bool $sensitive = false
    ) {}
}

