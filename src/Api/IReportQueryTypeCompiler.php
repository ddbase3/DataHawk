<?php declare(strict_types=1);

namespace DataHawk\Api;

use DataHawk\Dto\SqlQuery;

/**
 * Interface for compilers that handle a specific report query type
 * (e.g. 'select', 'delete', 'create', etc.) and generate SQL.
 */
interface IReportQueryTypeCompiler {

	/**
	 * Compiles a structured report query into an SQL representation.
	 *
	 * @param array $query The structured query input
	 * @return SqlQuery The compiled SQL with metadata
	 */
	public function compile(array $query): SqlQuery;
}

