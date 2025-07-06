<?php declare(strict_types=1);

namespace DataHawk\Schema;

use Base3\Microservice\Api\IMicroserviceConnector;
use Base3\Microservice\AbstractMicroservice;
use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Dto\TableMetadata;

class DataHawkSchemaMicroservice extends AbstractMicroservice implements IReportSchemaProvider {

	public function __construct(
		private readonly IReportSchemaProvider|IMicroserviceConnector $service
	) {}

	// Implementation of IReportSchemaProvider

	public function getSchema(): array {
		return $this->service->getSchema();
	}

	public function getTable(string $tableName): ?TableMetadata {
		return $this->service->getTable($tableName);
	}
}

