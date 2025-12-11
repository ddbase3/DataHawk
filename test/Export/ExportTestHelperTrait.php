<?php declare(strict_types=1);

namespace DataHawk\Test\Export;

use ResourceFoundation\Dto\QueryResult;

trait ExportTestHelperTrait {

        private function makeQueryResult(array $columns, array $rows, ?string $debugSql = null, bool $sensitive = false): QueryResult {
                return new QueryResult(columns: $columns, rows: $rows, debugSql: $debugSql, sensitive: $sensitive);
        }

        private function tempFilePath(string $prefix): string {
                $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
                return $dir . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(6));
        }
}
