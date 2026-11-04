<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use DataHawk\Materialization\MaterializationPhysicalTableNameGenerator;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Dto\MaterializationManifest;

final class MaterializationPhysicalTableNameGeneratorTest extends TestCase {

	public function testUsesExplicitPhysicalPrefixAndNormalizesIt(): void {
		$manifest = new MaterializationManifest('orders', physicalPrefix: '  Custom--Orders___ ');
		$generator = new MaterializationPhysicalTableNameGenerator();

		self::assertSame('custom_orders', $generator->getPhysicalPrefix($manifest));
		self::assertSame('custom_orders_20261104', $generator->getGenerationTableName($manifest, '20261104'));
	}

	public function testBuildsPrefixFromTargetSchemaAndLogicalTable(): void {
		$manifest = new MaterializationManifest('ignored', targetSchema: ' Reports-West ', logicalTable: 'Monthly Revenue');
		self::assertSame('base3_mat_reports_west_monthly_revenue', (new MaterializationPhysicalTableNameGenerator())->getPhysicalPrefix($manifest));
	}

	public function testFallsBackToManifestIdWithoutLogicalTable(): void {
		self::assertSame('base3_mat_orders', (new MaterializationPhysicalTableNameGenerator())->getPhysicalPrefix(new MaterializationManifest('Orders')));
	}

	public function testRespectsCustomDefaultPrefix(): void {
		$generator = new MaterializationPhysicalTableNameGenerator('Custom--');
		self::assertSame('custom_orders', $generator->getPhysicalPrefix(new MaterializationManifest('orders')));
	}

	public function testGenerationIdentifiersAreNormalized(): void {
		$generator = new MaterializationPhysicalTableNameGenerator();
		self::assertSame('base3_mat_orders_x_y_z', $generator->getGenerationTableName(new MaterializationManifest('orders'), ' X---Y Z '));
	}

	public function testAutomaticallyGeneratedNameHasTimestampSuffix(): void {
		$name = (new MaterializationPhysicalTableNameGenerator())->getGenerationTableName(new MaterializationManifest('orders'));
		self::assertMatchesRegularExpression('/^base3_mat_orders_\d{14}$/', $name);
	}

	public function testRejectsEmptyExplicitPrefixAfterNormalization(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('must not be empty');
		(new MaterializationPhysicalTableNameGenerator())->getPhysicalPrefix(new MaterializationManifest('orders', physicalPrefix: '---'));
	}

	public function testRejectsEmptyGenerationAfterNormalization(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('must not be empty');
		(new MaterializationPhysicalTableNameGenerator())->getGenerationTableName(new MaterializationManifest('orders'), '---');
	}

	public function testRejectsEmptyFallbackIdentifier(): void {
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('must not be empty');
		(new MaterializationPhysicalTableNameGenerator('---'))->getPhysicalPrefix(new MaterializationManifest(''));
	}
}
