<?php declare(strict_types=1);

namespace DataHawk\Test\Schema;

use DataHawk\Schema\FileQuerySchemaProvider;
use PHPUnit\Framework\TestCase;

final class FileQuerySchemaProviderTest extends TestCase {

	private string $schemaDir;

	protected function setUp(): void {
		parent::setUp();

		$this->schemaDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'datahawk_schema_' . bin2hex(random_bytes(6));
		mkdir($this->schemaDir, 0777, true);
	}

	protected function tearDown(): void {
		foreach(glob($this->schemaDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir($this->schemaDir);

		parent::tearDown();
	}

	public function testLoadsSchemaMetadataFromExplicitDirectory(): void {
		file_put_contents($this->schemaDir . DIRECTORY_SEPARATOR . 'usage.json', json_encode([
			'name' => 'usage',
			'label' => 'Usage',
			'domain' => 'missionbay',
			'category' => 'ai_usage',
			'tags' => ['ai', 'usage'],
			'fields' => [
				['name' => 'id', 'type' => 'integer', 'required' => true, 'primaryKey' => true],
				['name' => 'tokens', 'type' => 'integer', 'nullable' => true]
			],
			'position' => ['x' => 120, 'y' => 80]
		], JSON_UNESCAPED_SLASHES));

		$provider = new FileQuerySchemaProvider($this->schemaDir);
		$schema = $provider->getSchema();

		$this->assertCount(1, $schema);
		$this->assertSame('usage', $schema[0]->name);
		$this->assertSame('missionbay', $schema[0]->domain);
		$this->assertSame(['x' => 120, 'y' => 80], $schema[0]->position);
		$this->assertFalse($schema[0]->fields[0]->nullable);
		$this->assertTrue($schema[0]->fields[1]->nullable);
		$this->assertSame($schema[0], $provider->getTable('usage'));
	}

	public function testMissingDirectoryReturnsEmptySchema(): void {
		$provider = new FileQuerySchemaProvider($this->schemaDir . DIRECTORY_SEPARATOR . 'missing');

		$this->assertSame([], $provider->getSchema());
		$this->assertNull($provider->getTable('anything'));
	}

	public function testRejectsFieldLevelForeignKeyMetadata(): void {
		file_put_contents($this->schemaDir . DIRECTORY_SEPARATOR . 'foreign_key.json', json_encode([
			'name' => 'usage',
			'fields' => [[
				'name' => 'owner_id',
				'type' => 'integer',
				'foreignKey' => ['table' => 'owner', 'column' => 'id']
			]]
		], JSON_UNESCAPED_SLASHES));

		$provider = new FileQuerySchemaProvider($this->schemaDir);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Field-level foreignKey metadata is not available');
		$provider->getSchema();
	}

	public function testInvalidJsonThrows(): void {
		file_put_contents($this->schemaDir . DIRECTORY_SEPARATOR . 'broken.json', '{');

		$provider = new FileQuerySchemaProvider($this->schemaDir);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid schema JSON');
		$provider->getSchema();
	}

	public function testRejectsMissingTableNameEvenForValidJson(): void {
		file_put_contents($this->schemaDir . '/empty.json', '{"fields":[]}');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('non-empty table name');
		(new FileQuerySchemaProvider($this->schemaDir))->getSchema();
	}

	public function testRejectsFieldsWithoutNames(): void {
		file_put_contents($this->schemaDir . '/orders.json', '{"name":"orders","fields":[{"type":"integer"}]}');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('non-empty name');
		(new FileQuerySchemaProvider($this->schemaDir))->getSchema();
	}

	public function testSchemaRemainsCachedAfterFileIsRemoved(): void {
		file_put_contents($this->schemaDir . '/orders.json', '{"name":"orders"}');
		$provider = new FileQuerySchemaProvider($this->schemaDir);
		$schema = $provider->getSchema();
		unlink($this->schemaDir . '/orders.json');
		self::assertSame($schema, $provider->getSchema());
		self::assertSame($schema[0], $provider->getTable('orders'));
	}

	public function testLoadsExtendedFieldAndJoinMetadata(): void {
		file_put_contents($this->schemaDir . '/orders.json', json_encode([
			'name' => 'orders', 'description' => 'Order facts', 'sensitive' => true,
			'fields' => [[
				'name' => 'customer_id', 'label' => 'Customer', 'type' => 'integer',
				'nullable' => false, 'tags' => ['key'], 'alias' => 'customer', 'sensitive' => true
			]],
			'joins' => [[
				'targetTable' => 'customers', 'on' => [['id' => 'customer_id']],
				'type' => 'INNER', 'meta' => ['default' => true]
			]]
		], JSON_THROW_ON_ERROR));
		$table = (new FileQuerySchemaProvider($this->schemaDir))->getSchema()[0];
		self::assertSame('Order facts', $table->description);
		self::assertTrue($table->sensitive);
		self::assertSame('Customer', $table->fields[0]->description);
		self::assertSame('customer', $table->fields[0]->alias);
		self::assertSame(['key'], $table->fields[0]->tags);
		self::assertTrue($table->fields[0]->sensitive);
		self::assertFalse($table->fields[0]->nullable);
		self::assertSame('INNER', $table->joins[0]->type);
		self::assertSame(['default' => true], $table->joins[0]->meta);
	}

}
