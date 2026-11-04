<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\DefaultTableNameResolver;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Dto\TableNameResolutionContext;

final class DefaultTableNameResolverTest extends TestCase {

	public function testReturnsLogicalNameWithoutPhysicalRewriting(): void {
		$resolver = new DefaultTableNameResolver();
		self::assertSame('reporting_orders', $resolver->resolveTableName('reporting_orders'));
		self::assertSame('scoped.orders', $resolver->resolveTableName('scoped.orders'));
	}

	public function testContextDoesNotChangeDefaultResolution(): void {
		$context = new TableNameResolutionContext(schema: 'reports', alias: 'o', operation: 'select', mode: 'physical');
		$resolver = new DefaultTableNameResolver();
		self::assertSame('orders', $resolver->resolveTableName('orders', $context));
	}

	public function testEmptyNameRemainsEmptyWithoutImplicitFallback(): void {
		self::assertSame('', (new DefaultTableNameResolver())->resolveTableName(''));
	}
}
