<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use DataHawk\Materialization\ManifestMaterializationSchemaProvider;
use PHPUnit\Framework\TestCase;

final class ManifestMaterializationSchemaProviderTest extends TestCase {

	private string $directory;

	protected function setUp(): void {
		parent::setUp();
		$this->directory = sys_get_temp_dir() . '/datahawk_manifests_' . bin2hex(random_bytes(6));
		mkdir($this->directory, 0777, true);
	}

	protected function tearDown(): void {
		foreach(glob($this->directory . '/*') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir($this->directory);
		parent::tearDown();
	}

	public function testMissingDirectoryProducesNoSchema(): void {
		$provider = new ManifestMaterializationSchemaProvider($this->directory . '/missing');
		self::assertSame([], $provider->getManifests());
		self::assertSame([], $provider->getSchema());
		self::assertNull($provider->getTable('missing'));
	}

	public function testReadsManifestWithSchemaAndFieldMetadata(): void {
		$this->write('orders.json', [
			'id' => 'orders', 'logicalTable' => 'monthly_orders', 'targetSchema' => 'reports',
			'label' => 'Monthly Orders', 'columns' => [['name' => 'id', 'type' => 'integer', 'required' => true]]
		]);
		$provider = new ManifestMaterializationSchemaProvider($this->directory);
		self::assertSame('orders', $provider->getManifest('orders')->id);
		self::assertSame('orders', $provider->getManifest('monthly_orders')->id);
		self::assertSame('monthly_orders', $provider->getSchema()[0]->name);
		self::assertSame('Monthly Orders', $provider->getTable('reports:monthly_orders')->label);
		self::assertSame('reports', $provider->getTable('reports.monthly_orders')->domain);
		self::assertNull($provider->getTable('other.monthly_orders'));
		self::assertFalse($provider->getSchema()[0]->fields[0]->nullable);
	}

	public function testDisabledManifestIsExcluded(): void {
		$this->write('disabled.json', ['id' => 'disabled', 'logicalTable' => 'disabled', 'enabled' => 'false']);
		self::assertSame([], (new ManifestMaterializationSchemaProvider($this->directory))->getManifests());
	}

	public function testConditionalMissingTableAllowsActivationWithoutDatabase(): void {
		$this->write('orders.json', ['id' => 'orders', 'logicalTable' => 'orders', 'conditions' => ['missingTables' => ['missing_table']]]);
		self::assertCount(1, (new ManifestMaterializationSchemaProvider($this->directory))->getManifests());
	}

	public function testConditionalRequiredTableBlocksActivationWithoutDatabase(): void {
		$this->write('orders.json', ['id' => 'orders', 'logicalTable' => 'orders', 'conditions' => ['requiresTables' => ['missing_table']]]);
		self::assertSame([], (new ManifestMaterializationSchemaProvider($this->directory))->getManifests());
	}

	public function testSelectsHigherPriorityVariantOfSameManifest(): void {
		$this->write('a.json', ['id' => 'orders', 'logicalTable' => 'orders', 'label' => 'Low', 'variantPriority' => 1]);
		$this->write('b.json', ['id' => 'orders', 'logicalTable' => 'orders', 'label' => 'High', 'variantPriority' => 3]);
		$provider = new ManifestMaterializationSchemaProvider($this->directory);
		self::assertCount(1, $provider->getManifests());
		self::assertSame('High', $provider->getSchema()[0]->label);
	}

	public function testInvalidJsonIsRejected(): void {
		file_put_contents($this->directory . '/invalid.json', '{');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid materialization manifest JSON');
		(new ManifestMaterializationSchemaProvider($this->directory))->getManifests();
	}

	public function testManifestWithoutIdIsRejected(): void {
		$this->write('invalid.json', ['logicalTable' => 'orders']);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('non-empty id');
		(new ManifestMaterializationSchemaProvider($this->directory))->getManifests();
	}

	public function testManifestWithoutLogicalTableIsRejected(): void {
		$this->write('invalid.json', ['id' => 'orders']);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('logicalTable');
		(new ManifestMaterializationSchemaProvider($this->directory))->getManifests();
	}


	public function testActiveManifestCanRequireAnExistingDatabaseTable(): void {
		$this->write('orders.json', [
			'id' => 'orders', 'logicalTable' => 'orders',
			'conditions' => ['requires_tables' => ['source_table']]
		]);
		$database = new DataHawkDatabaseFixture();
		$database->listRows = [['Tables_in_database' => 'source_table']];
		$provider = new ManifestMaterializationSchemaProvider($this->directory, $database);
		self::assertSame('orders', $provider->getManifests()[0]->id);
		self::assertSame(1, $database->connectCalls);
	}

	public function testExistingTableDisablesMissingTableManifest(): void {
		$this->write('orders.json', [
			'id' => 'orders', 'logicalTable' => 'orders',
			'conditions' => ['missing_tables' => ['source_table']]
		]);
		$database = new DataHawkDatabaseFixture();
		$database->listRows = [['Tables_in_database' => 'source_table']];
		self::assertSame([], (new ManifestMaterializationSchemaProvider($this->directory, $database))->getManifests());
	}

	public function testManifestDataIsCachedAfterItsOriginalFileDisappears(): void {
		$this->write('orders.json', ['id' => 'orders', 'logicalTable' => 'orders']);
		$provider = new ManifestMaterializationSchemaProvider($this->directory);
		self::assertSame('orders', $provider->getManifests()[0]->id);
		unlink($this->directory . '/orders.json');
		self::assertSame('orders', $provider->getManifest('orders')->id);
		self::assertSame('orders', $provider->getSchema()[0]->name);
	}

	public function testLowerPriorityVariantCannotOverwriteHigherPriorityVariant(): void {
		$this->write('a.json', ['id' => 'orders', 'logicalTable' => 'orders', 'label' => 'High', 'variant_priority' => 9]);
		$this->write('z.json', ['id' => 'orders', 'logicalTable' => 'orders', 'label' => 'Low', 'variant_priority' => 1]);
		self::assertSame('High', (new ManifestMaterializationSchemaProvider($this->directory))->getSchema()[0]->label);
	}

	public function testSingleConditionalWhereAndDependenciesAreIncluded(): void {
		$this->write('orders.json', [
			'id' => 'orders', 'logicalTable' => 'orders',
			'conditional_where' => [
				'where' => ['field' => 'active', 'value' => true],
				'depends_on' => 'first, second, first'
			]
		]);
		$manifest = (new ManifestMaterializationSchemaProvider($this->directory))->getManifest('orders');
		self::assertSame(['field' => 'active', 'value' => true], $manifest->query['where']);
		self::assertSame(['first', 'second'], $manifest->dependsOn);
	}

	public function testNestedTableAndFieldMetadataAreReturnedWithoutDroppingRelations(): void {
		$this->write('orders.json', [
			'id' => 'orders', 'logicalTable' => 'orders',
			'table' => ['label' => 'Order facts', 'tags' => ['sales'], 'sensitive' => true],
			'fields' => [[
				'name' => 'customer_id', 'type' => 'integer',
				'foreignKey' => ['table' => 'customers', 'column' => 'id']
			]],
			'joins' => [['targetTable' => 'customers', 'type' => 'INNER', 'meta' => ['default' => true]]]
		]);
		$table = (new ManifestMaterializationSchemaProvider($this->directory))->getSchema()[0];
		self::assertSame('Order facts', $table->label);
		self::assertTrue($table->sensitive);
		self::assertContains('sales', $table->tags);
		self::assertSame('customers', $table->fields[0]->foreignKey->table);
		self::assertSame('id', $table->fields[0]->foreignKey->column);
		self::assertSame('INNER', $table->joins[0]->type);
		self::assertSame(['default' => true], $table->joins[0]->meta);
	}

	public function testMalformedJoinIsRejectedWhenBuildingSchema(): void {
		$this->write('orders.json', [
			'id' => 'orders', 'logicalTable' => 'orders',
			'joins' => [['type' => 'LEFT']]
		]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Materialization join must define a non-empty targetTable');
		(new ManifestMaterializationSchemaProvider($this->directory))->getSchema();
	}

	private function write(string $file, array $data): void {
		file_put_contents($this->directory . '/' . $file, json_encode($data, JSON_THROW_ON_ERROR));
	}
}

require_once __DIR__ . '/DataHawkDatabaseFixture.php';
