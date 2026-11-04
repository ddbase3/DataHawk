<?php declare(strict_types=1);

namespace DataHawk\Test\Schema;

use DataHawk\Schema\DefinitionQuerySchemaProvider;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaDefinitionProvider;

final class DefinitionQuerySchemaProviderTest extends TestCase {

	public function testSortsEnabledDefinitionsAndBuildsFieldAndJoinMetadata(): void {
		$provider = $this->provider([
			'z' => ['definition' => ['name' => 'last']],
			'a' => ['enabled' => 'yes', 'definition' => [
				'name' => 'first', 'label' => 'First', 'domain' => 'reports', 'tags' => ['public'], 'sensitive' => true,
				'fields' => [
					['name' => 'id', 'type' => 'integer', 'required' => true, 'primaryKey' => true],
					['name' => 'text', 'alias' => 'caption', 'sensitive' => true]
				],
				'joins' => [['targetTable' => 'other', 'on' => [['id' => 'other_id']], 'type' => 'LEFT']]
			]]
		]);

		$schema = $provider->getSchema();
		self::assertSame(['first', 'last'], array_map(fn($table) => $table->name, $schema));
		self::assertSame('First', $schema[0]->label);
		self::assertSame('reports', $schema[0]->domain);
		self::assertTrue($schema[0]->sensitive);
		self::assertTrue($schema[0]->fields[0]->primaryKey);
		self::assertFalse($schema[0]->fields[0]->nullable);
		self::assertTrue($schema[0]->fields[1]->nullable);
		self::assertSame('caption', $schema[0]->fields[1]->alias);
		self::assertTrue($schema[0]->fields[1]->sensitive);
		self::assertSame('other', $schema[0]->joins[0]->targetTable);
		self::assertSame($schema[0], $provider->getTable('first'));
		self::assertNull($provider->getTable('unknown'));
	}

	public function testSkipsDisabledAndNonArrayDatasets(): void {
		$provider = $this->provider([
			'a' => ['enabled' => false, 'definition' => ['name' => 'a']],
			'b' => ['enabled' => 'off', 'definition' => ['name' => 'b']],
			'c' => ['enabled' => 0, 'definition' => ['name' => 'c']],
			'd' => ['enabled' => 'nonsense', 'definition' => ['name' => 'd']],
			'e' => 'not a dataset'
		]);

		self::assertSame(['d'], array_map(fn($table) => $table->name, $provider->getSchema()));
	}

	public function testSchemaIsCachedAndNotReloaded(): void {
		$definitions = $this->createMock(IQuerySchemaDefinitionProvider::class);
		$definitions->expects($this->once())->method('getDefinitions')->willReturn(['a' => ['definition' => ['name' => 'a']]]);
		$provider = new DefinitionQuerySchemaProvider($definitions);

		self::assertSame($provider->getSchema(), $provider->getSchema());
	}

	public function testRejectsDatasetWithoutDefinition(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('definition array');
		$this->provider(['a' => ['enabled' => true]])->getSchema();
	}

	public function testRejectsDefinitionWithoutName(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('non-empty table name');
		$this->provider(['a' => ['definition' => ['label' => 'No name']]])->getSchema();
	}

	public function testRejectsFieldWithoutName(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('non-empty name');
		$this->provider(['a' => ['definition' => ['name' => 'a', 'fields' => [['type' => 'string']]]]])->getSchema();
	}

	public function testRejectsFieldLevelForeignKeyInsteadOfInventingMapping(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Field-level foreignKey metadata is not available');
		$this->provider(['a' => ['definition' => ['name' => 'a', 'fields' => [['name' => 'parent_id', 'foreignKey' => ['table' => 'parent']]]]]])->getSchema();
	}

	public function testExplicitNullableOverridesRequiredFlag(): void {
		$provider = $this->provider(['a' => ['definition' => ['name' => 'a', 'fields' => [['name' => 'x', 'required' => true, 'nullable' => true]]]]]);
		self::assertTrue($provider->getSchema()[0]->fields[0]->nullable);
	}


	public function testEnabledFlagNormalizesBooleanNumericAndTextValues(): void {
		$flags = [
			'a' => true, 'b' => 1, 'c' => -1, 'd' => 'yes', 'e' => 'ON', 'f' => 'enabled',
			'g' => false, 'h' => 0, 'i' => '0', 'j' => 'no', 'k' => 'OFF', 'l' => 'disabled',
			'm' => 'unrecognized', 'n' => null
		];
		$settings = [];
		foreach($flags as $name => $flag) {
			$settings[$name] = ['enabled' => $flag, 'definition' => ['name' => $name]];
		}
		$names = array_map(static fn($table): string => $table->name, $this->provider($settings)->getSchema());
		self::assertSame(['a', 'b', 'c', 'd', 'e', 'f', 'm', 'n'], $names);
	}

	public function testUnspecifiedEnabledFlagDefaultsToEnabled(): void {
		$provider = $this->provider(['orders' => ['definition' => ['name' => 'orders']]]);
		self::assertSame('orders', $provider->getSchema()[0]->name);
	}

	public function testPreservesExtendedTableFieldAndJoinMetadata(): void {
		$provider = $this->provider(['orders' => ['definition' => [
			'name' => 'orders',
			'description' => 'Order facts',
			'category' => 'sales',
			'position' => ['row' => 2],
			'defaultFilters' => [['field' => 'active', 'value' => true]],
			'fields' => [[
				'name' => 'status', 'type' => 'text', 'label' => 'Status label',
				'required' => false, 'nullable' => false, 'tags' => ['filterable']
			]],
			'joins' => [[
				'targetTable' => 'customers', 'on' => [['id' => 'customer_id']],
				'type' => 'INNER', 'meta' => ['default' => true]
			]]
		]]]);
		$table = $provider->getSchema()[0];
		self::assertSame('orders', $table->label);
		self::assertSame('Order facts', $table->description);
		self::assertSame('sales', $table->category);
		self::assertSame(['row' => 2], $table->position);
		self::assertSame([['field' => 'active', 'value' => true]], $table->defaultFilters);
		self::assertSame('Status label', $table->fields[0]->description);
		self::assertSame('text', $table->fields[0]->type);
		self::assertFalse($table->fields[0]->nullable);
		self::assertSame(['filterable'], $table->fields[0]->tags);
		self::assertSame('INNER', $table->joins[0]->type);
		self::assertSame(['default' => true], $table->joins[0]->meta);
	}

	public function testRejectsFieldNameThatIsNotAString(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('non-empty name');
		$this->provider(['orders' => ['definition' => [
			'name' => 'orders', 'fields' => [['name' => 42]]
		]]])->getSchema();
	}

	private function provider(array $datasets): DefinitionQuerySchemaProvider {
		$definitions = $this->createStub(IQuerySchemaDefinitionProvider::class);
		$definitions->method('getDefinitions')->willReturn($datasets);
		return new DefinitionQuerySchemaProvider($definitions);
	}
}
