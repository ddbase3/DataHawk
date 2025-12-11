<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

class RenameQueryValidatorTest extends TestCase {

	private RenameQueryValidator $validator;

	protected function setUp(): void {
		$this->validator = new RenameQueryValidator();
	}

	public function testValidateThrowsWhenTypeIsNotRename(): void {
		$query = [
			'type' => 'select',
			'tables' => [
				'from' => 'old_table',
				'to' => 'new_table',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Unsupported query type: select");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenTypeMissing(): void {
		$query = [
			'tables' => [
				'from' => 'old_table',
				'to' => 'new_table',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Unsupported query type: [not defined]");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenTablesMissing(): void {
		$query = [
			'type' => 'rename',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("RENAME query must contain a 'tables' object with 'from' and 'to'.");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenTablesIsNotArray(): void {
		$query = [
			'type' => 'rename',
			'tables' => 'not-an-array',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("RENAME query must contain a 'tables' object with 'from' and 'to'.");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenFromIsInvalid(): void {
		$query = [
			'type' => 'rename',
			'tables' => [
				'from' => '',
				'to' => 'new_table',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("RENAME query requires a non-empty 'from' table name.");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenToIsInvalid(): void {
		$query = [
			'type' => 'rename',
			'tables' => [
				'from' => 'old_table',
				'to' => '',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("RENAME query requires a non-empty 'to' table name.");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenDisallowedFieldsPresent(): void {
		$disallowed = ['fields', 'where', 'order_by', 'group_by', 'limit', 'having'];

		foreach ($disallowed as $key) {
			$query = [
				'type' => 'rename',
				'tables' => [
					'from' => 'old_table',
					'to' => 'new_table',
				],
				$key => 'something',
			];

			try {
				$this->validator->validate($query);
				$this->fail("Expected exception for disallowed key '$key'");
			} catch (QueryValidationException $e) {
				$this->assertSame(
					"RENAME query must not contain '$key'.",
					$e->getMessage(),
					"Wrong exception message for key '$key'"
				);
			}
		}
	}

	public function testValidatePassesForValidRenameQuery(): void {
		$query = [
			'type' => 'rename',
			'tables' => [
				'from' => 'old_table',
				'to' => 'new_table',
			],
		];

		$this->validator->validate($query);

		$this->assertTrue(true);
	}
}
