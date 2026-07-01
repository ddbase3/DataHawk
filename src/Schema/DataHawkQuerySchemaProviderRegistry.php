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

use Base3\Core\DefaultServiceRegistry;
use ResourceFoundation\Api\IQuerySchemaProvider;

class DataHawkQuerySchemaProviderRegistry extends DefaultServiceRegistry {

	public function __construct(string $defaultName, array $factories) {
		parent::__construct(IQuerySchemaProvider::class, $defaultName, $factories);
	}

	public function get(string $name): IQuerySchemaProvider {
		$provider = parent::get($name);
		if (!$provider instanceof IQuerySchemaProvider) {
			throw new \RuntimeException("Schema provider '{$name}' must implement " . IQuerySchemaProvider::class);
		}

		return $provider;
	}

	public function getDefault(): IQuerySchemaProvider {
		$provider = parent::getDefault();
		if (!$provider instanceof IQuerySchemaProvider) {
			throw new \RuntimeException('Default schema provider must implement ' . IQuerySchemaProvider::class);
		}

		return $provider;
	}
}
