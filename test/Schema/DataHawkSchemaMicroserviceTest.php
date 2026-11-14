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

namespace Base3\DataHawk\Test\Schema;

use Base3\CoreFoundation\Microservice\Api\IMicroserviceConnector;
use Base3\DataHawk\Schema\DataHawkSchemaMicroservice;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Api\IQuerySchemaProvider;
use Base3\ResourceFoundation\Dto\TableMetadata;

class DataHawkSchemaMicroserviceTest extends TestCase {

	public function testGetSchemaDelegatesToService(): void {
		$service = $this->createMock(IQuerySchemaProvider::class);

		$service->expects($this->once())
			->method('getSchema')
			->willReturn(['FAKE_SCHEMA']);

		$ms = new DataHawkSchemaMicroservice($service);

		$this->assertSame(['FAKE_SCHEMA'], $ms->getSchema());
	}

	public function testGetTableDelegatesToServiceWithCorrectArgument(): void {
		$service = $this->createMock(IQuerySchemaProvider::class);

		$service->expects($this->once())
			->method('getTable')
			->with('users')
			->willReturn(new TableMetadata(
				name: 'users',
				label: null,
				description: null,
				domain: '',
				category: '',
				tags: [],
				fields: [],
				joins: [],
				defaultFilters: [],
				sensitive: false,
				position: []
			));

		$ms = new DataHawkSchemaMicroservice($service);

		$table = $ms->getTable('users');

		$this->assertInstanceOf(TableMetadata::class, $table);
		$this->assertSame('users', $table->name);
	}

	public function testAcceptsMicroserviceConnectorTypeHintAsWell(): void {
		// The microservice constructor accepts IQuerySchemaProvider|IMicroserviceConnector,
		// so we create a mock that implements both interfaces.
		$service = $this->createMockForIntersectionOfInterfaces([
			IQuerySchemaProvider::class,
			IMicroserviceConnector::class
		]);

		$service->expects($this->once())
			->method('getSchema')
			->willReturn(['FAKE_SCHEMA']);

		$service->expects($this->once())
			->method('getTable')
			->with('users')
			->willReturn(new TableMetadata(
				name: 'users',
				label: null,
				description: null,
				domain: '',
				category: '',
				tags: [],
				fields: [],
				joins: [],
				defaultFilters: [],
				sensitive: false,
				position: []
			));

		$service->expects($this->once())
			->method('getMicroserviceUrl')
			->willReturn('https://example.test/ms');

		$ms = new DataHawkSchemaMicroservice($service);

		$this->assertSame(['FAKE_SCHEMA'], $ms->getSchema());
		$this->assertInstanceOf(TableMetadata::class, $ms->getTable('users'));
		$this->assertSame('https://example.test/ms', $service->getMicroserviceUrl());
	}
}
