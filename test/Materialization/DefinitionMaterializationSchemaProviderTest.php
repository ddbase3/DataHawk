<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use Base3\Database\Api\IDatabase;
use DataHawk\Materialization\DefinitionMaterializationSchemaProvider;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IMaterializationDefinitionProvider;

final class DefinitionMaterializationSchemaProviderTest extends TestCase {

	public function testCreatesMaterializedSchemaFromEnabledDefinition(): void {
		$provider = $this->provider([
			'orders' => ['definition' => [
				'id' => 'orders', 'targetSchema' => 'reports', 'logicalTable' => 'monthly_orders',
				'label' => 'Monthly Orders', 'tags' => ['monthly'],
				'columns' => [['name' => 'id', 'type' => 'integer', 'required' => true]],
				'joins' => [['targetTable' => 'customers', 'on' => [['id' => 'customer_id']]]]
			]]
		]);

		$schema = $provider->getSchema();
		self::assertCount(1, $schema);
		self::assertSame('monthly_orders', $schema[0]->name);
		self::assertSame('reports', $schema[0]->domain);
		self::assertSame('Monthly Orders', $schema[0]->label);
		self::assertContains('materialized', $schema[0]->tags);
		self::assertContains('monthly', $schema[0]->tags);
		self::assertFalse($schema[0]->fields[0]->nullable);
		self::assertSame('customers', $schema[0]->joins[0]->targetTable);
		self::assertSame($schema[0], $provider->getTable('reports:monthly_orders'));
		self::assertSame($schema[0], $provider->getTable('reports.monthly_orders'));
		self::assertNull($provider->getTable('other:monthly_orders'));
		self::assertSame('orders', $provider->getManifest('monthly_orders')->id);
	}

	public function testSkipsDisabledDatasetsAndDefinitions(): void {
		$provider = $this->provider([
			'a' => ['enabled' => false, 'definition' => $this->data('a')],
			'b' => ['definition' => array_merge($this->data('b'), ['enabled' => 'false'])],
			'c' => ['enabled' => 'yes', 'definition' => $this->data('c')]
		]);

		self::assertSame(['c'], array_map(fn($manifest) => $manifest->id, $provider->getManifests()));
		self::assertNull($provider->getManifest('a'));
	}

	public function testVariantsSelectHighestPriorityPerId(): void {
		$provider = $this->provider([
			'a' => ['definition' => array_merge($this->data('orders'), ['variantPriority' => 3, 'label' => 'Low'])],
			'b' => ['definition' => array_merge($this->data('orders'), ['variantPriority' => 9, 'label' => 'High'])]
		]);
		self::assertCount(1, $provider->getManifests());
		self::assertSame('High', $provider->getSchema()[0]->label);
	}

	public function testConditionalWhereAndDependenciesAreApplied(): void {
		$data = $this->data('orders');
		$data['query'] = ['where' => ['field' => 'id', 'value' => 1]];
		$data['dependsOn'] = ['original'];
		$data['conditionalWhere'] = [
			['where' => ['field' => 'active', 'value' => true], 'dependsOn' => ['new']]
		];
		$manifest = $this->provider(['orders' => ['definition' => $data]])->getManifest('orders');
		self::assertSame('AND', $manifest->query['where']['operator']);
		self::assertSame(['original', 'new'], $manifest->dependsOn);
	}

	public function testRequiresUnknownPhysicalTableIsNotActivatedWithoutDatabase(): void {
		$data = $this->data('orders');
		$data['conditions'] = ['requiresTables' => ['unavailable_table']];
		self::assertSame([], $this->provider(['orders' => ['definition' => $data]])->getManifests());
	}

	public function testMissingTableConditionRemainsActiveWithoutDatabase(): void {
		$data = $this->data('orders');
		$data['conditions'] = ['missingTables' => ['unavailable_table']];
		self::assertCount(1, $this->provider(['orders' => ['definition' => $data]])->getManifests());
	}

	public function testRejectsNonArrayDataset(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('setting must be an array');
		$this->provider(['bad' => 'invalid'])->getManifests();
	}

	public function testRejectsMissingDefinitionArray(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('definition array');
		$this->provider(['bad' => ['enabled' => true]])->getManifests();
	}

	public function testRejectsEmptyManifestId(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('non-empty id');
		$this->provider(['bad' => ['definition' => $this->data('')]])->getManifests();
	}

	public function testRejectsEmptyLogicalTable(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('logicalTable');
		$this->provider(['bad' => ['definition' => ['id' => 'orders', 'targetSchema' => 'reports']]])->getManifests();
	}

	public function testRejectsTargetSchemaMismatchingProviderScope(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('must match provider scope');
		$this->provider(['orders' => ['definition' => ['id' => 'orders', 'targetSchema' => 'other', 'logicalTable' => 'orders']]])->getManifests();
	}


	public function testEmptyDefinitionsExposeEmptyManifestAndSchema(): void {
		$provider = $this->provider([]);
		self::assertSame([], $provider->getManifests());
		self::assertSame([], $provider->getSchema());
		self::assertNull($provider->getManifest('orders'));
		self::assertNull($provider->getTable('orders'));
	}

	public function testRequiresTablesActivatesWhenDatabaseReportsExistingTable(): void {
		$database = new DataHawkDatabaseFixture();
		$database->listRows = [['Tables_in_database' => 'existing_table']];
		$data = $this->data('orders');
		$data['conditions'] = ['requires_tables' => ['existing_table']];
		$provider = $this->provider(['orders' => ['definition' => $data]], $database);

		self::assertCount(1, $provider->getManifests());
		self::assertSame(1, $database->connectCalls);
		self::assertStringContainsString('SHOW TABLES LIKE', $database->queries[0]);
	}

	public function testMissingTablesDisablesDefinitionWhenDatabaseHasTable(): void {
		$database = new DataHawkDatabaseFixture();
		$database->listRows = [['Tables_in_database' => 'present']];
		$data = $this->data('orders');
		$data['conditions'] = ['missing_tables' => ['present']];
		self::assertSame([], $this->provider(['orders' => ['definition' => $data]], $database)->getManifests());
	}

	public function testDatabaseTableExistenceIsCachedAcrossDefinitions(): void {
		$database = new DataHawkDatabaseFixture();
		$database->listRows = [['Tables_in_database' => 'shared']];
		$first = array_merge($this->data('first'), ['conditions' => ['requiresTables' => ['shared']]]);
		$second = array_merge($this->data('second'), ['conditions' => ['requiresTables' => ['shared']]]);
		$provider = $this->provider([
			'first' => ['definition' => $first],
			'second' => ['definition' => $second]
		], $database);
		self::assertCount(2, $provider->getManifests());
		self::assertSame(1, $database->connectCalls);
		self::assertCount(1, $database->queries);
	}

	public function testUnavailableDatabaseDoesNotSatisfyRequiredTables(): void {
		$database = new DataHawkDatabaseFixture();
		$database->allowConnection = false;
		$data = $this->data('orders');
		$data['conditions'] = ['requiresTables' => ['required']];
		self::assertSame([], $this->provider(['orders' => ['definition' => $data]], $database)->getManifests());
	}

	public function testSingleConditionalEntryAddsWhereAndDeduplicatesDependencies(): void {
		$data = $this->data('orders');
		$data['conditionalWhere'] = [
			'where' => ['field' => 'active', 'value' => true],
			'dependsOn' => 'first, second, first'
		];
		$manifest = $this->provider(['orders' => ['definition' => $data]])->getManifest('orders');
		self::assertSame(['field' => 'active', 'value' => true], $manifest->query['where']);
		self::assertSame(['first', 'second'], $manifest->dependsOn);
	}

	public function testConditionalListIgnoresNonArrayAndInactiveEntries(): void {
		$data = $this->data('orders');
		$data['conditionalWhere'] = [
			'ignored',
			['where' => ['field' => 'enabled', 'value' => 1]],
			['conditions' => ['requiresTables' => ['not_present']], 'where' => ['field' => 'disabled', 'value' => 1]]
		];
		$manifest = $this->provider(['orders' => ['definition' => $data]])->getManifest('orders');
		self::assertSame(['field' => 'enabled', 'value' => 1], $manifest->query['where']);
	}

	public function testLowerPriorityVariantCannotOverwritePreviouslySelectedManifest(): void {
		$provider = $this->provider([
			'a' => ['definition' => array_merge($this->data('orders'), ['variant_priority' => 9, 'label' => 'High'])],
			'z' => ['definition' => array_merge($this->data('orders'), ['variant_priority' => 1, 'label' => 'Low'])]
		]);
		self::assertSame('High', $provider->getSchema()[0]->label);
	}

	public function testColumnsAndNestedTableMetadataArePreserved(): void {
		$data = $this->data('orders');
		$data['table'] = [
			'label' => 'Nested label', 'description' => 'Nested description',
			'category' => 'archive', 'tags' => ['private'],
			'sensitive' => true, 'position' => ['row' => 3],
			'defaultFilters' => [['field' => 'active', 'value' => true]]
		];
		$data['fields'] = [
			'ignored',
			['name' => 'customer_id', 'type' => 'integer', 'required' => true,
				'foreignKey' => ['table' => 'customers', 'column' => 'id'], 'tags' => ['key']]
		];
		$data['joins'] = [
			'ignored',
			['targetTable' => 'customers', 'type' => 'INNER', 'meta' => ['default' => true]]
		];
		$table = $this->provider(['orders' => ['definition' => $data]])->getSchema()[0];
		self::assertSame('Nested label', $table->label);
		self::assertSame('Nested description', $table->description);
		self::assertSame('archive', $table->category);
		self::assertTrue($table->sensitive);
		self::assertSame(['row' => 3], $table->position);
		self::assertSame([['field' => 'active', 'value' => true]], $table->defaultFilters);
		self::assertContains('private', $table->tags);
		self::assertSame('customers', $table->fields[0]->foreignKey->table);
		self::assertSame('id', $table->fields[0]->foreignKey->column);
		self::assertFalse($table->fields[0]->nullable);
		self::assertSame(['key'], $table->fields[0]->tags);
		self::assertSame('INNER', $table->joins[0]->type);
		self::assertSame(['default' => true], $table->joins[0]->meta);
	}

	public function testMalformedTableFieldAndJoinMetadataAreRejectedWhenSchemaIsBuilt(): void {
		foreach([
			['fields' => [['name' => '']]],
			['joins' => [['type' => 'LEFT']]]
		] as $invalid) {
			$provider = $this->provider(['invalid' => ['definition' => array_merge($this->data('orders'), $invalid)]]);
			try {
				$provider->getSchema();
				self::fail('Malformed field or join metadata must be rejected.');
			} catch (\RuntimeException $e) {
				self::assertStringContainsString('must define a non-empty', $e->getMessage());
			}
		}
	}

	private function data(string $id): array {
		return ['id' => $id, 'targetSchema' => 'reports', 'logicalTable' => 'monthly_orders'];
	}

	private function provider(array $data, ?IDatabase $database = null): DefinitionMaterializationSchemaProvider {
		$definitions = $this->createStub(IMaterializationDefinitionProvider::class);
		$definitions->method('getScope')->willReturn('reports');
		$definitions->method('getDefinitions')->willReturn($data);
		return new DefinitionMaterializationSchemaProvider($definitions, $database);
	}
}
