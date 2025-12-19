<?php declare(strict_types=1);

namespace DataHawk\Test\Schema;

use Base3\Microservice\Api\IMicroserviceConnector;
use DataHawk\Schema\DataHawkSchemaMicroservice;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\TableMetadata;

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
