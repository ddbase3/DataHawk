<?php declare(strict_types=1);

namespace DataHawk\Api;

use DataHawk\Dto\SqlQuery;

interface IReportQueryCompiler {
    public function compile(array $query): SqlQuery;
}

