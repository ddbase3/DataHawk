<?php declare(strict_types=1);

namespace DataHawk\Test\Reporting;

use Base3\Api\IClassMap;
use DataHawk\Reporting\ReportingScopeRegistry;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Dto\ReportingScopeDefinition;

final class ReportingScopeRegistryTest extends TestCase {

	public function testSortsScopesByDisplayLabelAndTrimsLookupId(): void {
		$alpha = new ReportingScopeDefinition('first', 'Alpha', ['query-a'], ['mat-a'], ['reports-a']);
		$zeta = new ReportingScopeDefinition('second', 'Zeta', ['query-z']);
		$registry = $this->registry([$zeta, $alpha]);

		self::assertSame([$alpha, $zeta], $registry->getScopes());
		self::assertSame($alpha, $registry->get(' first '));
		self::assertNull($registry->get(''));
		self::assertNull($registry->get('missing'));
	}

	public function testReturnsEmptyArrayWithNoProviders(): void {
		self::assertSame([], $this->registry([])->getScopes());
	}

	public function testRejectsDuplicateReportingScopeIds(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Reporting scope id is not unique');
		$this->registry([new ReportingScopeDefinition('a', 'One'), new ReportingScopeDefinition('a', 'Two')])->getScopes();
	}

	public function testRejectsInvalidReportingScopeId(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid reporting scope id');
		$this->registry([new ReportingScopeDefinition('Upper Case', 'One')])->getScopes();
	}

	public function testRejectsEmptyReportingScopeLabel(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('label must not be empty');
		$this->registry([new ReportingScopeDefinition('a', ' ')])->getScopes();
	}

	public function testRejectsInvalidTechnicalSchemaScope(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid query schema scope');
		$this->registry([new ReportingScopeDefinition('a', 'One', ['UPPER'])])->getScopes();
	}

	public function testRejectsDuplicateOwnershipOfQuerySchemaScope(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('assigned to multiple reporting scopes');
		$this->registry([
			new ReportingScopeDefinition('a', 'One', ['shared']),
			new ReportingScopeDefinition('b', 'Two', ['shared'])
		])->getScopes();
	}

	public function testRejectsDuplicateOwnershipOfMaterializationScope(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Technical materialization scope');
		$this->registry([
			new ReportingScopeDefinition('a', 'One', materializationScopes: ['shared']),
			new ReportingScopeDefinition('b', 'Two', materializationScopes: ['shared'])
		])->getScopes();
	}

	public function testRejectsDuplicateOwnershipOfReportScope(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Technical report scope');
		$this->registry([
			new ReportingScopeDefinition('a', 'One', reportScopes: ['shared']),
			new ReportingScopeDefinition('b', 'Two', reportScopes: ['shared'])
		])->getScopes();
	}

	public function testAllowsSameTechnicalNameInDifferentNamespaces(): void {
		$registry = $this->registry([
			new ReportingScopeDefinition('a', 'One', querySchemaScopes: ['shared']),
			new ReportingScopeDefinition('b', 'Two', materializationScopes: ['shared'])
		]);
		self::assertCount(2, $registry->getScopes());
	}


	public function testIgnoresDiscoveredObjectsThatDoNotImplementScopeDefinitionProvider(): void {
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn([new \stdClass()]);

		$registry = new ReportingScopeRegistry($classMap);
		self::assertSame([], $registry->getScopes());
		self::assertNull($registry->get('unknown'));
	}

	public function testScopeDefinitionsAreLoadedOnceAndCachedAcrossLookups(): void {
		$definition = new ReportingScopeDefinition('reports', 'Reports', ['analytics']);
		$classMap = $this->createMock(IClassMap::class);
		$classMap->expects($this->once())->method('getInstancesByInterface')->willReturn([new ReportingScopeDefinitionProviderFixture($definition)]);
		$registry = new ReportingScopeRegistry($classMap);

		self::assertSame([$definition], $registry->getScopes());
		self::assertSame($definition, $registry->get('reports'));
		self::assertSame([$definition], $registry->getScopes());
	}

	public function testRejectsInvalidMaterializationTechnicalScope(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid materialization scope');
		$this->registry([new ReportingScopeDefinition('reports', 'Reports', materializationScopes: ['Bad Name'])])->getScopes();
	}

	public function testRejectsInvalidReportTechnicalScope(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid report scope');
		$this->registry([new ReportingScopeDefinition('reports', 'Reports', reportScopes: ['Invalid.Name'])])->getScopes();
	}

	public function testRepeatedTechnicalNameWithinSameScopeDoesNotClaimAnotherOwner(): void {
		$definition = new ReportingScopeDefinition('reports', 'Reports', ['internal', 'internal'], ['internal'], ['internal']);
		self::assertSame([$definition], $this->registry([$definition])->getScopes());
	}

	private function registry(array $definitions): ReportingScopeRegistry {
		$providers = array_map(static fn(ReportingScopeDefinition $definition) => new ReportingScopeDefinitionProviderFixture($definition), $definitions);
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn($providers);
		return new ReportingScopeRegistry($classMap);
	}
}

require_once __DIR__ . '/ReportingScopeDefinitionProviderFixture.php';
