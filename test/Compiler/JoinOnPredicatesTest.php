<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Util\Graph;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

final class JoinOnPredicatesTest extends TestCase {

	private function planner(): JoinPlanner {
		$aliases = new AliasResolver();
		$graph = new Graph();
		$graph->addNode('orders');
		$graph->addNode('attributes');
		$graph->addEdge('orders', 'attributes', 'default', [
			'default' => true,
			'type' => 'LEFT',
			'on' => ['orders.customer_id' => 'attributes.customer_id']
		]);
		return new JoinPlanner($aliases, new ElementCompiler($aliases, new \stdClass()), $graph);
	}

	public function testTwoAliasesCanJoinDifferentAttributesWithoutChangingBaseRows(): void {
		$planner = $this->planner();
		$nodes = [
			['type' => 'fld', 'table' => 'attributes', 'tablealias' => 'a1', 'field' => 'value',
				'variant' => 'OPTIONAL', 'joinOn' => ['attribute_id' => '17']],
			['type' => 'fld', 'table' => 'attributes', 'tablealias' => 'a2', 'field' => 'value',
				'variant' => 'OPTIONAL', 'joinOn' => ['attribute_id' => 'uuid-23']],
		];
		$joins = $planner->collectJoinDependencies($nodes);
		$this->assertCount(2, $joins);
		$sql = $planner->compileJoins('orders', $joins);
		$this->assertStringContainsString('LEFT JOIN `attributes` AS `a1` ON `orders`.`customer_id` = `a1`.`customer_id` AND `a1`.`attribute_id` = \'17\'', $sql);
		$this->assertStringContainsString('LEFT JOIN `attributes` AS `a2` ON `orders`.`customer_id` = `a2`.`customer_id` AND `a2`.`attribute_id` = \'uuid-23\'', $sql);
		$this->assertStringNotContainsString(' WHERE ', $sql);
	}

	public function testUnsafeIdentifierAndAmbiguousAliasAreRejected(): void {
		$planner = $this->planner();
		$this->expectException(QueryValidationException::class);
		$planner->collectJoinDependencies([['type' => 'fld', 'table' => 'attributes',
			'field' => 'value', 'joinOn' => ['id` = 1 OR 1=1' => 1]]]);
	}

	public function testConflictingAliasPredicatesAreRejected(): void {
		$planner = $this->planner();
		$this->expectException(QueryValidationException::class);
		$planner->collectJoinDependencies([
			['type' => 'fld', 'table' => 'attributes', 'tablealias' => 'a', 'field' => 'value', 'joinOn' => ['attribute_id' => 'one']],
			['type' => 'fld', 'table' => 'attributes', 'tablealias' => 'a', 'field' => 'value', 'joinOn' => ['attribute_id' => 'two']]
		]);
	}

	public function testPredicateStringEscapingAndNull(): void {
		$planner = $this->planner();
		$sql = $planner->compileJoins('orders', $planner->collectJoinDependencies([
			['type' => 'fld', 'table' => 'attributes', 'tablealias' => 'a', 'field' => 'value',
				'joinOn' => ['attribute_id' => "O'Reilly", 'deleted_at' => null]]
		]));
		$this->assertStringContainsString("`a`.`attribute_id` = 'O''Reilly'", $sql);
		$this->assertStringContainsString('`a`.`deleted_at` IS NULL', $sql);
	}
}
