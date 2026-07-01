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

namespace DataHawk\Schema;

use Base3\Api\IServiceRegistry;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\TableMetadata;

class CompositeQuerySchemaProvider implements IQuerySchemaProvider {

	public function __construct(
		private readonly IServiceRegistry $registry
	) {}

	/**
	 * @return TableMetadata[]
	 */
	public function getSchema(): array {
		$schema = [];

		foreach ($this->registry->listNames() as $name) {
			$schema = array_merge($schema, $this->registry->get($name)->getSchema());
		}

		return $schema;
	}

	public function getTable(string $tableName): ?TableMetadata {
		[$providerName, $localTableName] = $this->splitQualifiedTableName($tableName);
		if ($providerName !== null) {
			if (!$this->registry->has($providerName)) {
				return null;
			}

			return $this->registry->get($providerName)->getTable($localTableName);
		}

		$defaultTable = $this->registry->getDefault()->getTable($tableName);
		if ($defaultTable !== null) {
			return $defaultTable;
		}

		foreach ($this->registry->listNames() as $name) {
			$table = $this->registry->get($name)->getTable($tableName);
			if ($table !== null) {
				return $table;
			}
		}

		return null;
	}

	private function splitQualifiedTableName(string $tableName): array {
		foreach ([':', '.'] as $separator) {
			if (!str_contains($tableName, $separator)) {
				continue;
			}

			[$providerName, $localTableName] = explode($separator, $tableName, 2);
			if ($providerName !== '' && $localTableName !== '') {
				return [$providerName, $localTableName];
			}
		}

		return [null, $tableName];
	}
}
