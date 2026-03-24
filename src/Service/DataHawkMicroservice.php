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

namespace DataHawk\Service;

use Base3\Microservice\Api\IMicroserviceConnector;
use Base3\Microservice\AbstractMicroservice;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use ResourceFoundation\Dto\TableMetadata;

class DataHawkMicroservice extends AbstractMicroservice implements IQueryService {

	public function __construct(
		private readonly IQueryService|IMicroserviceConnector $service
	) {}

	// Implementation of IQueryService

	public function listTables(): array {
		return $this->service->listTables();
	}

	public function getTable(string $tableName): ?TableMetadata {
		return $this->service->getTable($tableName);
	}

	public function executeQuery(array $queryJson): QueryResult {
		return $this->service->executeQuery($queryJson);
	}

	public function listDomains(): array {
		return $this->service->listDomains();
	}

	public function listCategories(): array {
		return $this->service->listCategories();
	}

	public function listTags(): array {
		return $this->service->listTags();
	}
}

