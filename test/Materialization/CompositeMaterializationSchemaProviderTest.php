<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use DataHawk\Materialization\CompositeMaterializationSchemaProvider;
use DataHawk\Schema\DataHawkQuerySchemaProviderRegistry;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IMaterializationSchemaProvider;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\TableMetadata;

final class CompositeMaterializationSchemaProviderTest extends TestCase {

	public function testAggregatesSchemasInRegistryOrderAndReturnsSortedScopes(): void {
		$a = new TableMetadata('a');
		$b = new TableMetadata('b');
		$provider = $this->provider(['z' => [[$b], []], 'a' => [[$a], []]]);
		self::assertSame(['a', 'z'], $provider->getScopes());
		self::assertSame([$b, $a], $provider->getSchema());
	}

	public function testResolvesQualifiedTablesWithColonOrDot(): void {
		$a = new TableMetadata('orders', label: 'Alpha');
		$b = new TableMetadata('orders', label: 'Beta');
		$provider = $this->provider(['alpha' => [[$a], []], 'beta' => [[$b], []]]);
		self::assertSame($a, $provider->getTable('alpha:orders'));
		self::assertSame($b, $provider->getTable('beta.orders'));
	}

	public function testRejectsAmbiguousUnqualifiedTables(): void {
		$provider = $this->provider([
			'a' => [[new TableMetadata('orders')], []],
			'b' => [[new TableMetadata('orders')], []]
		]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Materialized table identifier is ambiguous');
		$provider->getTable('orders');
	}

	public function testReturnsNullForUnknownQualifiedAndUnqualifiedTables(): void {
		$provider = $this->provider(['alpha' => [[], []]]);
		self::assertNull($provider->getTable('missing'));
		self::assertNull($provider->getTable('missing:orders'));
	}

	public function testAggregatesManifestsAndResolvesScopedIdentifiers(): void {
		$a = new MaterializationManifest('orders', targetSchema: 'a');
		$b = new MaterializationManifest('orders', targetSchema: 'b');
		$provider = $this->provider(['a' => [[], [$a]], 'b' => [[], [$b]]]);
		self::assertSame([$a, $b], $provider->getManifests());
		self::assertSame($a, $provider->getManifest('a:orders'));
		self::assertSame($b, $provider->getManifestForScope(' b ', 'orders'));
		self::assertSame([$b], $provider->getManifestsForScope('b'));
	}

	public function testRejectsAmbiguousUnqualifiedManifestId(): void {
		$a = new MaterializationManifest('orders');
		$b = new MaterializationManifest('orders');
		$provider = $this->provider(['a' => [[], [$a]], 'b' => [[], [$b]]]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Materialization identifier is ambiguous');
		$provider->getManifest('orders');
	}

	public function testUnknownManifestAndScopeReturnEmptyResults(): void {
		$provider = $this->provider(['a' => [[], []]]);
		self::assertNull($provider->getManifest('missing'));
		self::assertNull($provider->getManifestForScope('missing', 'orders'));
		self::assertSame([], $provider->getManifestsForScope('missing'));
	}


	public function testExcludesPlainQueryProvidersFromMaterializationScopes(): void {
		$regular = $this->createStub(IQuerySchemaProvider::class);
		$registry = $this->createStub(DataHawkQuerySchemaProviderRegistry::class);
		$registry->method('listNames')->willReturn(['regular']);
		$registry->method('get')->with('regular')->willReturn($regular);
		$provider = new CompositeMaterializationSchemaProvider($registry);

		self::assertSame([], $provider->getScopes());
		self::assertSame([], $provider->getSchema());
		self::assertSame([], $provider->getManifests());
		self::assertNull($provider->getTable('regular:orders'));
		self::assertNull($provider->getManifest('regular:orders'));
	}

	public function testUniqueUnqualifiedTableAndManifestResolveAcrossScopes(): void {
		$table = new TableMetadata('orders');
		$manifest = new MaterializationManifest('orders');
		$provider = $this->provider(['empty' => [[], []], 'reports' => [[$table], [$manifest]]]);

		self::assertSame($table, $provider->getTable('orders'));
		self::assertSame($manifest, $provider->getManifest('orders'));
		self::assertSame([$manifest], $provider->getManifestsForScope(' reports '));
	}

	public function testMalformedQualifiersDoNotSelectAnotherMaterializationScope(): void {
		$table = new TableMetadata('orders');
		$manifest = new MaterializationManifest('orders');
		$provider = $this->provider(['reports' => [[$table], [$manifest]]]);
		foreach([':orders', '.orders', 'reports:', 'reports.'] as $identifier) {
			self::assertNull($provider->getTable($identifier));
		}
		foreach([':orders', 'reports:', 'reports.orders'] as $identifier) {
			self::assertNull($provider->getManifest($identifier));
		}
	}

	public function testManifestQualificationNeverFallsBackToAnotherScope(): void {
		$manifest = new MaterializationManifest('orders');
		$provider = $this->provider(['reports' => [[], [$manifest]], 'other' => [[], []]]);
		self::assertNull($provider->getManifest('other:orders'));
		self::assertSame($manifest, $provider->getManifest('reports:orders'));
	}

	private function provider(array $definitions): CompositeMaterializationSchemaProvider {
		$providers = [];
		foreach($definitions as $scope => [$tables, $manifests]) {
			$provider = $this->createStub(IMaterializationSchemaProvider::class);
			$provider->method('getSchema')->willReturn($tables);
			$provider->method('getManifests')->willReturn($manifests);
			$provider->method('getTable')->willReturnCallback(static function(string $name) use($tables): ?TableMetadata {
				foreach($tables as $table) if($table->name === $name) return $table;
				return null;
			});
			$provider->method('getManifest')->willReturnCallback(static function(string $id) use($manifests): ?MaterializationManifest {
				foreach($manifests as $manifest) if($manifest->id === $id) return $manifest;
				return null;
			});
			$providers[$scope] = $provider;
		}

		$registry = $this->createStub(DataHawkQuerySchemaProviderRegistry::class);
		$registry->method('listNames')->willReturn(array_keys($providers));
		$registry->method('get')->willReturnCallback(static fn(string $scope) => $providers[$scope]);
		return new CompositeMaterializationSchemaProvider($registry);
	}
}
