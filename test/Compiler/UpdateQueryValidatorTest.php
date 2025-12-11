<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

class UpdateQueryValidatorTest extends TestCase {

	private UpdateQueryValidator $validator;

	protected function setUp(): void {
		$this->validator = new UpdateQueryValidator();
	}

	public function testThrowsWhenTypeIsNotUpdate(): void {
		$query = [
			'type' => 'select',
			'table' => 'users',
			'set' => [
				'name' => 'Alice',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Unsupported query type: select");

		$this->validator->validate($query);
	}

	public function testThrowsWhenTypeMissing(): void {
		$query = [
			'table' => 'users',
			'set' => [
				'name' => 'Alice',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Unsupported query type: [not defined]");

		$this->validator->validate($query);
	}

	public function testThrowsWhenTableMissing(): void {
		$query = [
			'type' => 'update',
			'set' => [
				'name' => 'Alice',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UPDATE query must contain a valid 'table' name.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenTableIsEmptyString(): void {
		$query = [
			'type' => 'update',
			'table' => '',
			'set' => [
				'name' => 'Alice',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UPDATE query must contain a valid 'table' name.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenTableIsNotString(): void {
		$query = [
			'type' => 'update',
			'table' => ['users'],
			'set' => [
				'name' => 'Alice',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UPDATE query must contain a valid 'table' name.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenSetMissing(): void {
		$query = [
			'type' => 'update',
			'table' => 'users',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UPDATE query must contain a non-empty 'set' object.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenSetIsNotArray(): void {
		$query = [
			'type' => 'update',
			'table' => 'users',
			'set' => 'not-an-array',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UPDATE query must contain a non-empty 'set' object.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenSetIsEmptyArray(): void {
		$query = [
			'type' => 'update',
			'table' => 'users',
			'set' => [],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UPDATE query must contain a non-empty 'set' object.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenSetKeyIsEmptyString(): void {
		$query = [
			'type' => 'update',
			'table' => 'users',
			'set' => [
				'   ' => 'value',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Each 'set' key must be a non-empty string.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenSetKeyIsNotString(): void {
		$query = [
			'type' => 'update',
			'table' => 'users',
			'set' => [
				0 => 'value',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Each 'set' key must be a non-empty string.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenSetValueArrayIsMissingType(): void {
		$query = [
			'type' => 'update',
			'table' => 'users',
			'set' => [
				'name' => [
					'foo' => 'bar',
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Invalid 'set' value for 'name': missing 'type' in expression.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenWhereIsNotArray(): void {
		$query = [
			'type' => 'update',
			'table' => 'users',
			'set' => [
				'name' => 'Alice',
			],
			'where' => 'invalid-where',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("'where' must be a valid expression object.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenDisallowedRootFieldPresent(): void {
		$disallowed = ['fields', 'values', 'group_by', 'having'];

		foreach ($disallowed as $key) {
			$query = [
				'type' => 'update',
				'table' => 'users',
				'set' => [
					'name' => 'Alice',
				],
				$key => 'something',
			];

			try {
				$this->validator->validate($query);
				$this->fail("Expected exception for disallowed key '$key'");
			} catch (QueryValidationException $e) {
				$this->assertSame(
					"UPDATE query must not contain '$key'.",
					$e->getMessage(),
					"Wrong exception message for key '$key'"
				);
			}
		}
	}

	public function testPassesForValidUpdateWithLiteralsAndExpressionAndWhere(): void {
		$query = [
			'type' => 'update',
			'table' => 'users',
			'set' => [
				'name' => 'Alice',
				'age' => 30,
				'last_login' => [
					'type' => 'lit',
					'value' => '2024-01-01',
				],
			],
			'where' => [
				'type' => 'op',
				'op' => '=',
				'left' => [
					'type' => 'fld',
					'table' => 'users',
					'field' => 'id',
				],
				'right' => [
					'type' => 'lit',
					'value' => 123,
				],
			],
		];

		$this->validator->validate($query);

		$this->assertTrue(true);
	}
}
