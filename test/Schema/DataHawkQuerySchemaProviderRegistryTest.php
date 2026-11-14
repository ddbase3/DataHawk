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

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\DataHawk\Schema\DataHawkQuerySchemaProviderRegistry;
use Base3\DataHawk\Schema\DefinitionQuerySchemaProvider;
use Base3\DataHawk\Materialization\DefinitionMaterializationSchemaProvider;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Api\IMaterializationDefinitionProvider;
use Base3\ResourceFoundation\Api\IQuerySchemaDefinitionProvider;

final class DataHawkQuerySchemaProviderRegistryTest extends TestCase {

	public function testBuildsDefaultAndNamedProviderLazily(): void {
		$registry = $this->registry([
			new QuerySchemaDefinitionProviderFixture('default', ['orders' => ['definition' => ['name' => 'orders']]]),
			new QuerySchemaDefinitionProviderFixture('regional', ['users' => ['definition' => ['name' => 'users']]])
		]);
		self::assertSame(['default', 'regional'], $registry->listNames());
		self::assertTrue($registry->has('regional'));
		self::assertFalse($registry->has('missing'));
		self::assertInstanceOf(DefinitionQuerySchemaProvider::class, $registry->getDefault());
		self::assertSame($registry->getDefault(), $registry->get('default'));
		self::assertSame('orders', $registry->getDefault()->getTable('orders')->name);
		self::assertSame('users', $registry->get('regional')->getTable('users')->name);
		self::assertSame($registry->get('regional'), $registry->get('regional'));
	}

	public function testMaterializationDefinitionProvidesItsOwnSchemaScope(): void {
		$registry = $this->registry([
			new QuerySchemaDefinitionProviderFixture('default')
		], [
			new MaterializationDefinitionProviderFixture('materialized', ['orders' => [
				'definition' => ['id' => 'orders', 'targetSchema' => 'materialized', 'logicalTable' => 'orders']
			]])
		]);
		self::assertInstanceOf(DefinitionMaterializationSchemaProvider::class, $registry->get('materialized'));
		self::assertSame('orders', $registry->get('materialized')->getTable('orders')->name);
	}

	public function testMissingDefaultScopeIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('scope "default" is not available');
		$this->registry([new QuerySchemaDefinitionProviderFixture('regional')]);
	}

	public function testEmptyScopeIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('empty scope');
		$this->registry([new QuerySchemaDefinitionProviderFixture(' ')]);
	}

	public function testInvalidScopeIsRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid reporting definition scope');
		$this->registry([new QuerySchemaDefinitionProviderFixture('upperCase')]);
	}

	public function testDuplicateScopesAcrossProviderTypesAreRejected(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('not unique: default');
		$this->registry([new QuerySchemaDefinitionProviderFixture('default')], [new MaterializationDefinitionProviderFixture('default')]);
	}

	public function testTrimsWhitespaceAroundValidScope(): void {
		$registry = $this->registry([new QuerySchemaDefinitionProviderFixture(' default ')]);
		self::assertSame(['default'], $registry->listNames());
	}

	public function testRejectsLookupOfUnregisteredScope(): void {
		$registry = $this->registry([new QuerySchemaDefinitionProviderFixture('default')]);
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('unknown instance');
		$registry->get('missing');
	}


	public function testIgnoresDiscoveryObjectsWithoutExpectedProviderInterfaces(): void {
		$registry = $this->registry([
			new \stdClass(),
			new QuerySchemaDefinitionProviderFixture('default')
		], [new \stdClass()]);
		self::assertSame(['default'], $registry->listNames());
		self::assertInstanceOf(DefinitionQuerySchemaProvider::class, $registry->getDefault());
	}

	public function testRejectsDuplicateScopesAmongQueryProviders(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('scope is not unique: default');
		$this->registry([
			new QuerySchemaDefinitionProviderFixture('default'),
			new QuerySchemaDefinitionProviderFixture('default')
		]);
	}

	public function testRejectsInvalidMaterializationDefinitionScope(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid reporting definition scope');
		$this->registry([new QuerySchemaDefinitionProviderFixture('default')], [
			new MaterializationDefinitionProviderFixture('Bad Scope')
		]);
	}

	public function testAcceptsMaterializationDefinitionsAsTheDefaultScope(): void {
		$registry = $this->registry([], [new MaterializationDefinitionProviderFixture('default')]);
		self::assertSame(['default'], $registry->listNames());
		self::assertInstanceOf(DefinitionMaterializationSchemaProvider::class, $registry->getDefault());
	}

	public function testRejectsDuplicateScopesAmongMaterializationProviders(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('scope is not unique: materialized');
		$this->registry([new QuerySchemaDefinitionProviderFixture('default')], [
			new MaterializationDefinitionProviderFixture('materialized'),
			new MaterializationDefinitionProviderFixture('materialized')
		]);
	}

	private function registry(array $queryDefinitions, array $materializations = []): DataHawkQuerySchemaProviderRegistry {
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturnCallback(static fn(string $interface): array => match($interface) {
			IQuerySchemaDefinitionProvider::class => $queryDefinitions,
			IMaterializationDefinitionProvider::class => $materializations,
			default => []
		});
		return new DataHawkQuerySchemaProviderRegistry($classMap, $this->createStub(IDatabase::class));
	}
}

require_once __DIR__ . '/QuerySchemaDefinitionProviderFixture.php';
require_once __DIR__ . '/MaterializationDefinitionProviderFixture.php';
