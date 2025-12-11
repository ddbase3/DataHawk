<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Util\Graph;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

class JoinPlannerTest extends TestCase {

	private AliasResolver $aliasResolver;
	private ElementCompiler $elementCompiler;
	private Graph $joinGraph;
	private JoinPlanner $joinPlanner;

	protected function setUp(): void {
		// Alle Abhängigkeiten als Stubs, damit keine PHPUnit-Notices entstehen
		$this->aliasResolver = $this->createStub(AliasResolver::class);
		$this->elementCompiler = $this->createStub(ElementCompiler::class);
		$this->joinGraph = $this->createStub(Graph::class);

		// quoteIdentifier: einfache Backticks
		$this->elementCompiler
			->method('quoteIdentifier')
			->willReturnCallback(fn(string $id) => '`' . $id . '`');

		$this->joinPlanner = new JoinPlanner(
			$this->aliasResolver,
			$this->elementCompiler,
			$this->joinGraph
		);
	}

	public function testCollectJoinDependenciesRegistersAliasAndVariant(): void {
		$nodes = [
			[
				'type' => 'fld',
				'table' => 'users',
				'tablealias' => 'u',
				'variant' => 'OPTIONAL',
			],
		];

		$result = $this->joinPlanner->collectJoinDependencies($nodes);

		$this->assertSame(
			['users' => 'OPTIONAL'],
			$result,
			'Should collect table with variant'
		);
	}

	public function testCollectJoinDependenciesRecursesIntoNestedNodes(): void {
		$nodes = [
			[
				'type' => 'func',
				'element' => [
					'type' => 'fld',
					'table' => 'orders',
				],
				'params' => [
					[
						'type' => 'fld',
						'table' => 'users',
						'variant' => 'HARD',
					],
				],
			],
		];

		$result = $this->joinPlanner->collectJoinDependencies($nodes);

		$this->assertSame(
			[
				'orders' => null,
				'users'  => 'HARD',
			],
			$result,
			'Should recurse into nested nodes and collect both tables'
		);
	}

	public function testCompileJoinsThrowsWhenNoPathFound(): void {
		$from = 'users';
		$joinRequests = ['orders' => null];

		// Keine Pfade → Exception erwartet
		$this->joinGraph
			->method('findAllPaths')
			->with('users', 'orders')
			->willReturn([]);

		$this->aliasResolver
			->method('getAliasUsage')
			->willReturn([]);

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("No join path from 'users' to 'orders'");

		$this->joinPlanner->compileJoins($from, $joinRequests);
	}

	public function testCompileJoinsBuildsLeftJoinForDefaultLeftPathWithoutVariant(): void {
		$from = 'users';
		$joinRequests = ['orders' => null];

		$paths = [
			[
				[
					'to' => 'orders',
					'meta' => [
						'default' => true,
						'type' => 'LEFT',
						'on' => [
							'users.id' => 'orders.user_id',
						],
					],
				],
			],
		];

		$this->joinGraph
			->method('findAllPaths')
			->willReturn($paths);

		$this->aliasResolver
			->method('getAliasUsage')
			->willReturn([
				'orders' => ['o' => true]
			]);

		$this->aliasResolver
			->method('getAliasForTable')
			->willReturnMap([
				['users', 'u']
			]);

		$sql = $this->joinPlanner->compileJoins($from, $joinRequests);

		$expected = " LEFT JOIN `orders` AS `o` ON `u`.`id` = `o`.`user_id`";

		$this->assertSame($expected, $sql);
	}

	public function testCompileJoinsUsesLeftJoinWhenVariantOptional(): void {
		$from = 'users';
		$joinRequests = ['profiles' => 'OPTIONAL'];

		$paths = [
			[
				[
					'to' => 'profiles',
					'meta' => [
						'default' => false,
						'type' => 'INNER',
						'on' => [
							'users.id' => 'profiles.user_id',
						],
					],
				],
			],
		];

		$this->joinGraph
			->method('findAllPaths')
			->willReturn($paths);

		$this->aliasResolver
			->method('getAliasUsage')
			->willReturn([
				'profiles' => ['profiles' => true]
			]);

		$this->aliasResolver
			->method('getAliasForTable')
			->willReturnMap([
				['users', 'u']
			]);

		$sql = $this->joinPlanner->compileJoins($from, $joinRequests);

		$expected = " LEFT JOIN `profiles` ON `u`.`id` = `profiles`.`user_id`";

		$this->assertSame($expected, $sql);
	}
}
