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
		$this->aliasResolver = $this->createStub(AliasResolver::class);
		$this->elementCompiler = $this->createStub(ElementCompiler::class);
		$this->joinGraph = $this->createStub(Graph::class);

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
			['users::u' => ['table' => 'users', 'alias' => 'u', 'variant' => 'OPTIONAL', 'joinOn' => []]],
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
				'orders::orders' => ['table' => 'orders', 'alias' => 'orders', 'variant' => null, 'joinOn' => []],
				'users::users' => ['table' => 'users', 'alias' => 'users', 'variant' => 'HARD', 'joinOn' => []],
			],
			$result,
			'Should recurse into nested nodes and collect both tables'
		);
	}

	public function testCompileJoinsThrowsWhenNoPathFound(): void {
		$from = 'users';
		$joinRequests = ['orders' => null];

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
		$joinRequests = [['table' => 'orders', 'alias' => 'o', 'variant' => null]];

		$paths = [
			[
				[
					'from' => 'users',
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
				'orders' => ['o' => true],
			]);

		$sql = $this->joinPlanner->compileJoins($from, $joinRequests);

		$expected = " LEFT JOIN `orders` AS `o` ON `users`.`id` = `o`.`user_id`";
		$this->assertSame($expected, $sql);
	}

	public function testCompileJoinsUsesLeftJoinWhenVariantOptional(): void {
		$from = 'users';
		$joinRequests = ['profiles' => 'OPTIONAL'];

		$paths = [
			[
				[
					'from' => 'users',
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
				'profiles' => ['profiles' => true],
			]);

		$sql = $this->joinPlanner->compileJoins($from, $joinRequests);

		$expected = " LEFT JOIN `profiles` ON `users`.`id` = `profiles`.`user_id`";
		$this->assertSame($expected, $sql);
	}

	public function testCollectDependenciesIgnoresSubqueriesAndNonArrayNodes(): void {
		self::assertSame([], $this->joinPlanner->collectJoinDependencies([
			'ignored',
			['type' => 'subquery', 'element' => ['type' => 'fld', 'table' => 'private_table']],
			['type' => 'fld', 'field' => 'id']
		]));
	}

	public function testDuplicateAliasWithoutConflictingPredicatesIsMerged(): void {
		$joins = $this->joinPlanner->collectJoinDependencies([
			['type' => 'fld', 'table' => 'users', 'tablealias' => 'u', 'variant' => 'OPTIONAL'],
			['type' => 'fld', 'table' => 'users', 'tablealias' => 'u', 'variant' => 'HARD', 'joinOn' => ['active' => 1]],
			['type' => 'fld', 'table' => 'users', 'tablealias' => 'u', 'variant' => 'HARD', 'joinOn' => ['active' => 1]]
		]);
		self::assertCount(1, $joins);
		self::assertSame('HARD', $joins['users::u']['variant']);
		self::assertSame(['active' => 1], $joins['users::u']['joinOn']);
	}

	public function testInvalidJoinPredicateValueIsRejectedBeforeSqlCompilation(): void {
		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage('scalar or null');
		$this->joinPlanner->collectJoinDependencies([[
			'type' => 'fld', 'table' => 'users', 'joinOn' => ['invalid' => ['nested' => true]]
		]]);
	}

	public function testListShapedJoinPredicateMapIsRejected(): void {
		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage('field-to-value map');
		$this->joinPlanner->collectJoinDependencies([[
			'type' => 'fld', 'table' => 'users', 'joinOn' => [1, 2]
		]]);
	}

}
