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

use Base3\Microservice\Api\IMicroserviceConnector;
use Base3\Microservice\AbstractMicroservice;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\TableMetadata;

class DataHawkSchemaMicroservice extends AbstractMicroservice implements IQuerySchemaProvider {

	public function __construct(
		private readonly IQuerySchemaProvider|IMicroserviceConnector $service
	) {}

	// Implementation of IQuerySchemaProvider

	public function getSchema(): array {
		return $this->service->getSchema();
	}

	public function getTable(string $tableName): ?TableMetadata {
		return $this->service->getTable($tableName);
	}
}

