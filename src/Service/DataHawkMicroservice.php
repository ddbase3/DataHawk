<?php declare(strict_types=1);

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

