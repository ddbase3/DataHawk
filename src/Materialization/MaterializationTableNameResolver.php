<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of DataHawk for BASE3 Framework.
 *
 * DataHawk extends the BASE3 framework with a schema-driven query
 * engine for reporting and data access. Queries are defined as
 * structured JSON arrays, compiled into SQL, and executed through
 * the BASE3 IDatabase abstraction.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/datahawk
 * https://github.com/ddbase3/DataHawk
 **********************************************************************/

namespace DataHawk\Materialization;

use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\ITableNameResolver;
use ResourceFoundation\Dto\TableNameResolutionContext;

class MaterializationTableNameResolver implements ITableNameResolver {

	public function __construct(
		private readonly IMaterializationRegistry $registry
	) {}

	public function resolveTableName(string $tableName, ?TableNameResolutionContext $context = null): string {
		if ($context?->mode === 'physical') {
			return $tableName;
		}

		if (str_starts_with($tableName, 'base3_mat_')) {
			return $tableName;
		}

		$generation = $this->registry->getCurrentGeneration($context?->schema ?? '', $tableName);
		if ($generation === null || $generation->physicalTable === '') {
			return $tableName;
		}

		return $generation->physicalTable;
	}
}
