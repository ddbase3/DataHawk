<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of DataHawk for BASE3 Framework.
 *
 * DataHawk extends BASE3 with schema-driven reporting and data queries.
 * Structured query definitions compile to backend-specific statements.
 * Execution uses the shared BASE3 database and resource contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/datahawk
 * https://github.com/ddbase3/DataHawk
 **********************************************************************/

namespace Base3\DataHawk\Test\Service;

use PHPUnit\Framework\TestCase;
use Base3\DataHawk\Service\DataHawkMicroservice;
use Base3\CoreFoundation\Microservice\Api\IMicroserviceConnector;
use Base3\ResourceFoundation\Api\IQueryService;
use Base3\ResourceFoundation\Dto\QueryResult;
use Base3\ResourceFoundation\Dto\TableMetadata;

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
