<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use DataHawk\Materialization\MaterializationTableNameResolver;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Dto\MaterializationGeneration;
use ResourceFoundation\Dto\TableNameResolutionContext;

final class MaterializationTableNameResolverTest extends TestCase {

	public function testReturnsPhysicalTableUnchangedWithoutConsultingRegistry(): void {
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->never())->method('getCurrentGeneration');

		self::assertSame('orders', (new MaterializationTableNameResolver($registry))->resolveTableName('orders', new TableNameResolutionContext(mode: 'physical')));
	}

	public function testPreservesAlreadyMaterializedTableNames(): void {
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->never())->method('getCurrentGeneration');

		self::assertSame('base3_mat_orders_20260101', (new MaterializationTableNameResolver($registry))->resolveTableName('base3_mat_orders_20260101'));
	}

	public function testResolvesCurrentGenerationUsingSchemaContext(): void {
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->once())->method('getCurrentGeneration')->with('analytics', 'orders')->willReturn(
			new MaterializationGeneration('analytics', 'orders', 'base3_mat_orders_1', '1')
		);

		self::assertSame('base3_mat_orders_1', (new MaterializationTableNameResolver($registry))->resolveTableName('orders', new TableNameResolutionContext(schema: 'analytics')));
	}

	public function testDefaultsToUnscopedRegistryLookup(): void {
		$registry = $this->createMock(IMaterializationRegistry::class);
		$registry->expects($this->once())->method('getCurrentGeneration')->with('', 'orders')->willReturn(null);

		self::assertSame('orders', (new MaterializationTableNameResolver($registry))->resolveTableName('orders'));
	}

	public function testReturnsOriginalNameWhenRegistryHasNoGeneration(): void {
		$registry = $this->createStub(IMaterializationRegistry::class);
		$registry->method('getCurrentGeneration')->willReturn(null);

		self::assertSame('orders', (new MaterializationTableNameResolver($registry))->resolveTableName('orders', new TableNameResolutionContext(schema: 'missing')));
	}

	public function testReturnsOriginalNameWhenGenerationHasNoPhysicalTable(): void {
		$registry = $this->createStub(IMaterializationRegistry::class);
		$registry->method('getCurrentGeneration')->willReturn(new MaterializationGeneration('', 'orders', '', '1'));

		self::assertSame('orders', (new MaterializationTableNameResolver($registry))->resolveTableName('orders'));
	}
}
