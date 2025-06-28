<?php declare(strict_types=1);

namespace DataHawk\Service;

use Base3\Microservice\AbstractMicroservice;
use DataHawk\Api\IReportQueryService;
use DataHawk\Dto\TableMetadata;
use DataHawk\Dto\QueryResult;

class DataHawkMicroservice extends AbstractMicroservice implements IReportQueryService {

	private $service;

        public function __construct() {
		$servicelocator = \Base3\ServiceLocator::getInstance();
		$this->service = $servicelocator->get(IReportQueryService::class);
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

