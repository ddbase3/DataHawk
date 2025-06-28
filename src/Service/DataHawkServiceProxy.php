<?php declare(strict_types=1);

namespace DataHawk\Service;

use DataHawk\Api\IReportQueryService;

class DataHawkServiceProxy implements IReportQueryService {

	// here: MicroserviceConnector
	private $service;

	public function __construct($service) {
		$this->service = $service;
	}

        // Implementation of IReportQueryService

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
