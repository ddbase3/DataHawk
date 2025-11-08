<?php declare(strict_types=1);

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

