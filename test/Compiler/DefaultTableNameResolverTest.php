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

namespace Base3\DataHawk\Test\Compiler;

use Base3\DataHawk\Compiler\DefaultTableNameResolver;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Dto\TableNameResolutionContext;

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
