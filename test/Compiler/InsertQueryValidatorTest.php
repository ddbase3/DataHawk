<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\InsertQueryValidator;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

class InsertQueryValidatorTest extends TestCase {

	public function testValidInsertValuesWithColumnsPasses(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'columns' => ['id', 'name'],
			'values' => [
				[
					'id' => 1,
					'name' => 'Alice',
				],
				[
					'id' => 2,
					'name' => 'Bob',
				],
			],
		];

		$validator->validate($query);
		$this->expectNotToPerformAssertions();
	}

	public function testValidInsertValuesWithoutColumnsPasses(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'values' => [
				[
					'id' => 1,
					'name' => 'Alice',
				],
			],
		];

		$validator->validate($query);
		$this->expectNotToPerformAssertions();
	}

	public function testValidInsertFromSelectWithoutColumnsPasses(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'dest',
			'from' => [
				'type' => 'select',
				'table' => 'src',
			],
		];

		$validator->validate($query);
		$this->expectNotToPerformAssertions();
	}

	public function testValidInsertFromSelectWithColumnsPasses(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'dest',
			'columns' => ['id', 'name'],
			'from' => [
				'type' => 'select',
				'table' => 'src',
			],
		];

		$validator->validate($query);
		$this->expectNotToPerformAssertions();
	}

	public function testInvalidTypeThrowsException(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'select',
			'table' => 'users',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Unsupported query type: select");

		$validator->validate($query);
	}

	public function testMissingTypeThrowsException(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'table' => 'users',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Unsupported query type: [not defined]");

		$validator->validate($query);
	}

	public function testMissingOrInvalidTableThrowsException(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => '',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("INSERT query must contain a valid 'table' name.");

		$validator->validate($query);
	}

	public function testNonStringTableThrowsException(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => ['not-a-string'],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("INSERT query must contain a valid 'table' name.");

		$validator->validate($query);
	}

	public function testMissingValuesAndFromThrowsException(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			// neither 'values' nor 'from'
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("INSERT query must contain either 'values' or 'from'.");

		$validator->validate($query);
	}

	public function testBothValuesAndFromThrowsException(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'values' => [
				['id' => 1],
			],
			'from' => [
				'type' => 'select',
				'table' => 'users',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("INSERT query cannot contain both 'values' and 'from'.");

		$validator->validate($query);
	}

	public function testValuesMustBeNonEmptyArray(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'values' => [],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("'values' must be a non-empty array.");

		$validator->validate($query);
	}

	public function testValuesMustBeArray(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'values' => 'not-an-array',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("'values' must be a non-empty array.");

		$validator->validate($query);
	}

	public function testEachValuesEntryMustBeArray(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'values' => [
				['id' => 1],
				'not-a-row',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Each entry in 'values' must be an object (row). Entry at index 1 is invalid.");

		$validator->validate($query);
	}

	public function testValuesRowKeysMustBeNonEmptyStrings(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'values' => [
				[
					'' => 1,
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Each row must have named columns. Found invalid key in row 0.");

		$validator->validate($query);
	}

	public function testColumnsMustBeNonEmptyArrayOfStrings(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'columns' => [],
			'from' => [
				'type' => 'select',
				'table' => 'src',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("'columns' must be a non-empty array of strings.");

		$validator->validate($query);
	}

	public function testColumnsMustBeArray(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'columns' => 'id,name',
			'from' => [
				'type' => 'select',
				'table' => 'src',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("'columns' must be a non-empty array of strings.");

		$validator->validate($query);
	}

	public function testEachColumnMustBeNonEmptyString(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'columns' => ['id', '   '],
			'from' => [
				'type' => 'select',
				'table' => 'src',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Each entry in 'columns' must be a non-empty string.");

		$validator->validate($query);
	}

	public function testDisallowedTopLevelKeysCauseException(): void {
		$validator = new InsertQueryValidator();

		$keys = ['fields', 'where', 'group_by', 'order_by', 'having', 'limit'];

		foreach ($keys as $key) {
			$query = [
				'type' => 'insert',
				'table' => 'users',
				'values' => [
					['id' => 1],
				],
				$key => 'something',
			];

			try {
				$validator->validate($query);
				$this->fail("Expected exception for disallowed key '$key'.");
			} catch (QueryValidationException $e) {
				$this->assertSame("INSERT query must not contain '$key' at the top level.", $e->getMessage());
			}
		}
	}

	public function testAllowsOnDuplicateKeySection(): void {
		$validator = new InsertQueryValidator();

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'values' => [
				['id' => 1, 'name' => 'Alice'],
			],
			'on_duplicate' => [
				'name' => 'Bob',
			],
		];

		$validator->validate($query);
		$this->expectNotToPerformAssertions();
	}
}
