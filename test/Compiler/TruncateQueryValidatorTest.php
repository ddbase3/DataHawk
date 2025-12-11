<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

class TruncateQueryValidatorTest extends TestCase {

	private TruncateQueryValidator $validator;

	protected function setUp(): void {
		$this->validator = new TruncateQueryValidator();
	}

	public function testThrowsWhenTypeIsNotTruncate(): void {
		$query = [
			'type' => 'delete',
			'table' => 'users'
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Unsupported query type: delete");

		$this->validator->validate($query);
	}

	public function testThrowsWhenTypeMissing(): void {
		$query = [
			'table' => 'users'
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Unsupported query type: [not defined]");

		$this->validator->validate($query);
	}

	public function testThrowsWhenTableMissing(): void {
		$query = [
			'type' => 'truncate'
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("TRUNCATE query must define a non-empty 'table' as string.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenTableIsEmptyString(): void {
		$query = [
			'type' => 'truncate',
			'table' => ''
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("TRUNCATE query must define a non-empty 'table' as string.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenTableIsNotString(): void {
		$query = [
			'type' => 'truncate',
			'table' => ['invalid']
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("TRUNCATE query must define a non-empty 'table' as string.");

		$this->validator->validate($query);
	}

	public function testThrowsWhenUnsupportedFieldsPresent(): void {
		$fields = ['where', 'order_by', 'limit', 'fields', 'group_by', 'having'];

		foreach ($fields as $field) {
			$query = [
				'type' => 'truncate',
				'table' => 'users',
				$field => 'something'
			];

			try {
				$this->validator->validate($query);
				$this->fail("Expected exception for unsupported field '$field'");
			} catch (QueryValidationException $e) {
				$this->assertSame(
					"TRUNCATE query must not contain '$field'.",
					$e->getMessage(),
					"Wrong exception message for field '$field'"
				);
			}
		}
	}

	public function testPassesForValidQuery(): void {
		$query = [
			'type' => 'truncate',
			'table' => 'users'
		];

		$this->validator->validate($query);

		$this->assertTrue(true);
	}
}
