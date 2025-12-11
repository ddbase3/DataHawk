<?php declare(strict_types=1);

namespace DataHawk\Test\Schema;

use PHPUnit\Framework\TestCase;
use DataHawk\Schema\DataHawkSchemaMicroservice;
use ResourceFoundation\Api\IQuerySchemaProvider;
use Base3\Microservice\Api\IMicroserviceConnector;
use ResourceFoundation\Dto\TableMetadata;

class DataHawkSchemaMicroserviceTest extends TestCase {

        public function testGetSchemaDelegatesToService(): void {
                $service = new FakeSchemaService();

                $ms = new DataHawkSchemaMicroservice($service);

                $schema = $ms->getSchema();

                $this->assertSame(1, $service->getSchemaCallCount);
                $this->assertSame(['FAKE_SCHEMA'], $schema);
        }

        public function testGetTableDelegatesToServiceWithCorrectArgument(): void {
                $service = new FakeSchemaService();

                $ms = new DataHawkSchemaMicroservice($service);

                $table = $ms->getTable('users');

                $this->assertSame(1, $service->getTableCallCount);
                $this->assertSame('users', $service->lastTableName);

                $this->assertInstanceOf(TableMetadata::class, $table);
                $this->assertSame('users', $table->name);
        }

        public function testAcceptsMicroserviceConnectorTypeHintAsWell(): void {
                $service = new FakeSchemaService(); // implements both interfaces

                $ms = new DataHawkSchemaMicroservice($service);

                $this->assertSame(['FAKE_SCHEMA'], $ms->getSchema());
                $this->assertInstanceOf(TableMetadata::class, $ms->getTable('users'));
                $this->assertSame('https://example.test/ms', $service->getMicroserviceUrl());
        }
}

/**
 * Fake service that implements both constructor-accepted types:
 * IQuerySchemaProvider|IMicroserviceConnector.
 */
class FakeSchemaService implements IQuerySchemaProvider, IMicroserviceConnector {

        public int $getSchemaCallCount = 0;
        public int $getTableCallCount = 0;
        public ?string $lastTableName = null;

        public function getSchema(): array {
                $this->getSchemaCallCount++;
                return ['FAKE_SCHEMA'];
        }

        public function getTable(string $tableName): ?TableMetadata {
                $this->getTableCallCount++;
                $this->lastTableName = $tableName;

                return new TableMetadata(
                        name: $tableName,
                        label: null,
                        description: null,
                        domain: '',
                        category: '',
                        tags: [],
                        fields: [],
                        joins: [],
                        defaultFilters: [],
                        sensitive: false
                );
        }

        public function getMicroserviceUrl() {
                return 'https://example.test/ms';
        }
}
