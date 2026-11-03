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
		$service = new DataHawkMicroserviceTestDouble1();

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

require_once __DIR__ . '/DataHawkMicroserviceTestDouble1.php';
