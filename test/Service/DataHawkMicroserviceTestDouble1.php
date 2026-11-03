<?php declare(strict_types=1);

namespace DataHawk\Test\Service;

use PHPUnit\Framework\TestCase;
use DataHawk\Service\DataHawkMicroservice;
use Base3\Microservice\Api\IMicroserviceConnector;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use ResourceFoundation\Dto\TableMetadata;

final class DataHawkMicroserviceTestDouble1 implements IQueryService, IMicroserviceConnector  {

			/** @var list<mixed> */
			public array $calls = [];

			public function getMicroserviceUrl() {
				return 'http://fake';
			}

			public function listTables(): array {
				$this->calls[] = 'listTables';
				return ['tables'];
			}

			public function getTable(string $tableName): ?TableMetadata {
				$this->calls[] = ['getTable', $tableName];
				return new TableMetadata(
					name: $tableName,
					label: null,
					description: null,
					domain: 'd',
					category: 'c',
					tags: [],
					fields: [],
					joins: [],
					defaultFilters: [],
					sensitive: false
				);
			}

			public function executeQuery(array $queryJson): QueryResult {
				$this->calls[] = ['executeQuery', $queryJson];
				return new QueryResult(columns: [], rows: [], debugSql: null, sensitive: false);
			}

			public function listDomains(): array {
				$this->calls[] = 'listDomains';
				return ['domains'];
			}

			public function listCategories(): array {
				$this->calls[] = 'listCategories';
				return ['categories'];
			}

			public function listTags(): array {
				$this->calls[] = 'listTags';
				return ['tags'];
			}
		}
