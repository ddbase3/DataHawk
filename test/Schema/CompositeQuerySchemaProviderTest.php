<?php declare(strict_types=1);

namespace DataHawk\Test\Schema;

use Base3\Api\IServiceRegistry;
use DataHawk\Schema\CompositeQuerySchemaProvider;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\TableMetadata;

final class CompositeQuerySchemaProviderTest extends TestCase {

	public function testListsSortedScopesAndAggregatesTheirSchemas(): void {
		$first = new TableMetadata('first');
		$second = new TableMetadata('second');
		$provider = $this->provider(['z' => [$second], 'a' => [$first]]);

		self::assertSame(['a', 'z'], $provider->getScopes());
		self::assertSame([$first, $second], $provider->getSchema());
	}

	public function testResolvesExplicitScopeUsingColonOrDot(): void {
		$first = new TableMetadata('orders', label: 'First');
		$second = new TableMetadata('orders', label: 'Second');
		$provider = $this->provider(['first' => [$first], 'second' => [$second]], 'first');

		self::assertSame($first, $provider->getTable('first:orders'));
		self::assertSame($second, $provider->getTable('second.orders'));
	}

	public function testUnqualifiedLookupPrefersDefaultScope(): void {
		$default = new TableMetadata('shared', label: 'Default');
		$other = new TableMetadata('shared', label: 'Other');
		$provider = $this->provider(['default' => [$default], 'other' => [$other]]);

		self::assertSame($default, $provider->getTable('shared'));
		self::assertSame('default', $provider->getDefaultScope());
	}

	public function testUnqualifiedLookupFindsUniqueNondefaultTable(): void {
		$other = new TableMetadata('unique');
		$provider = $this->provider(['default' => [], 'other' => [$other]]);
		self::assertSame($other, $provider->getTable('unique'));
	}

	public function testUnqualifiedLookupRejectsAmbiguousNondefaultTable(): void {
		$provider = $this->provider(['default' => [], 'a' => [new TableMetadata('shared')], 'b' => [new TableMetadata('shared')]]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Query table identifier is ambiguous');
		$provider->getTable('shared');
	}

	public function testUnknownScopeAndTableReturnNoMetadata(): void {
		$provider = $this->provider(['default' => []]);
		self::assertSame([], $provider->getSchemaForScope('missing'));
		self::assertSame([], $provider->getSchemaForScope(' '));
		self::assertNull($provider->getTableForScope('missing', 'x'));
		self::assertNull($provider->getTableForScope('default', ''));
		self::assertNull($provider->getTable('missing'));
	}

	public function testTrimsExplicitScopeAndTableName(): void {
		$table = new TableMetadata('orders');
		$provider = $this->provider(['reports' => [$table]], 'reports');
		self::assertSame($table, $provider->getTableForScope(' reports ', ' orders '));
	}


	public function testReturnsEmptyDefaultScopeWhenNoNameResolvesToDefaultService(): void {
		$primary = $this->createStub(IQuerySchemaProvider::class);
		$other = $this->createStub(IQuerySchemaProvider::class);
		$registry = $this->createStub(IServiceRegistry::class);
		$registry->method('listNames')->willReturn(['other']);
		$registry->method('get')->willReturn($other);
		$registry->method('getDefault')->willReturn($primary);

		self::assertSame('', (new CompositeQuerySchemaProvider($registry))->getDefaultScope());
	}

	public function testUnqualifiedLookupReturnsNullWithNoMatchingTable(): void {
		$provider = $this->provider(['default' => [], 'secondary' => [new TableMetadata('other')]]);
		self::assertNull($provider->getTable('unknown'));
	}

	public function testQualifiedNameDoesNotLeakTablesAcrossScopes(): void {
		$table = new TableMetadata('orders');
		$provider = $this->provider(['alpha' => [$table], 'beta' => []], 'alpha');
		self::assertNull($provider->getTable('beta:orders'));
		self::assertNull($provider->getTable('missing.orders'));
	}

	private function provider(array $tablesByScope, ?string $defaultScope = null): CompositeQuerySchemaProvider {
		$providers = [];
		foreach($tablesByScope as $scope => $tables) {
			$schema = $this->createStub(IQuerySchemaProvider::class);
			$schema->method('getSchema')->willReturn($tables);
			$schema->method('getTable')->willReturnCallback(static function(string $name) use($tables): ?TableMetadata {
				foreach($tables as $table) {
					if($table->name === $name) return $table;
				}
				return null;
			});
			$providers[$scope] = $schema;
		}

		$defaultScope ??= array_key_first($providers);
		$registry = $this->createStub(IServiceRegistry::class);
		$registry->method('listNames')->willReturn(array_keys($providers));
		$registry->method('has')->willReturnCallback(static fn(string $name): bool => isset($providers[$name]));
		$registry->method('get')->willReturnCallback(static fn(string $name): IQuerySchemaProvider => $providers[$name]);
		$registry->method('getDefault')->willReturn($providers[$defaultScope]);
		return new CompositeQuerySchemaProvider($registry);
	}
}
