<?php declare(strict_types=1);

namespace DataHawk\Test\Service;

use PHPUnit\Framework\TestCase;
use DataHawk\Service\DataHawkMicroservice;
use Base3\Microservice\Api\IMicroserviceConnector;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;
use ResourceFoundation\Dto\TableMetadata;

class DataHawkMicroserviceTest extends TestCase {

	public function testDelegatesAllMethodsToUnderlyingService(): void {
		$service = new class implements IQueryService, IMicroserviceConnector {

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
		};

		$ms = new DataHawkMicroservice($service);

		$tables = $ms->listTables();
		$this->assertSame(['tables'], $tables);
		$this->assertSame(['listTables'], $service->calls);

		$service->calls = [];
		$table = $ms->getTable('users');
		$this->assertInstanceOf(TableMetadata::class, $table);
		$this->assertSame('users', $table->name);
		$this->assertSame([['getTable', 'users']], $service->calls);

		$service->calls = [];
		$result = $ms->executeQuery(['q' => 1]);
		$this->assertInstanceOf(QueryResult::class, $result);
		$this->assertSame([['executeQuery', ['q' => 1]]], $service->calls);

		$service->calls = [];
		$domains = $ms->listDomains();
		$this->assertSame(['domains'], $domains);
		$this->assertSame(['listDomains'], $service->calls);

		$service->calls = [];
		$cats = $ms->listCategories();
		$this->assertSame(['categories'], $cats);
		$this->assertSame(['listCategories'], $service->calls);

		$service->calls = [];
		$tags = $ms->listTags();
		$this->assertSame(['tags'], $tags);
		$this->assertSame(['listTags'], $service->calls);
	}
}
