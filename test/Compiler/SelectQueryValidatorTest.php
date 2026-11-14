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

use Base3\DataHawk\Compiler\SelectQueryValidator;

use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Exception\QueryValidationException;

class SelectQueryValidatorTest extends TestCase {

	private SelectQueryValidator $validator;

	protected function setUp(): void {
		$this->validator = new SelectQueryValidator();
	}

	public function testValidateThrowsWhenTypeIsNotSelect(): void {
		$query = [
			'type' => 'insert',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Unsupported query type: insert");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenTypeMissing(): void {
		$query = [];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Unsupported query type: [not defined]");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenFieldsMissingForStandardSelect(): void {
		$query = [
			'type' => 'select',
			// no 'fields'
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("SELECT query must contain a non-empty 'fields' array.");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenFieldsIsNotArrayForStandardSelect(): void {
		$query = [
			'type' => 'select',
			'fields' => 'not-an-array',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("SELECT query must contain a non-empty 'fields' array.");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenFieldsArrayIsEmptyForStandardSelect(): void {
		$query = [
			'type' => 'select',
			'fields' => [],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("SELECT query must contain a non-empty 'fields' array.");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenOrderByMissingElement(): void {
		$query = [
			'type' => 'select',
			'fields' => [
				['element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id']],
			],
			'order_by' => [
				[
					// missing 'element'
					'direction' => 'ASC',
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Missing element in order_by clause.");

		$this->validator->validate($query);
	}

	public function testValidateThrowsWhenOrderByDirectionInvalid(): void {
		$query = [
			'type' => 'select',
			'fields' => [
				['element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id']],
			],
			'order_by' => [
				[
					'element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id'],
					'direction' => 'SIDEWAYS',
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Invalid order direction: SIDEWAYS");

		$this->validator->validate($query);
	}

	public function testValidatePassesForValidStandardSelect(): void {
		$query = [
			'type' => 'select',
			'fields' => [
				['element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id']],
			],
			'order_by' => [
				[
					'element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id'],
					'direction' => 'DESC',
				],
			],
		];

		// Should not throw
		$this->validator->validate($query);

		$this->assertTrue(true);
	}

	public function testValidateUnionThrowsWhenQueriesMissing(): void {
		$query = [
			'type' => 'select',
			'union' => [
				// no 'queries' key
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UNION must contain a 'queries' array with at least two SELECTs.");

		$this->validator->validate($query);
	}

	public function testValidateUnionThrowsWhenQueriesIsNotArray(): void {
		$query = [
			'type' => 'select',
			'union' => [
				'queries' => 'not-an-array',
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UNION must contain a 'queries' array with at least two SELECTs.");

		$this->validator->validate($query);
	}

	public function testValidateUnionThrowsWhenLessThanTwoQueries(): void {
		$query = [
			'type' => 'select',
			'union' => [
				'queries' => [
					[
						'type' => 'select',
						'fields' => [
							['element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id']],
						],
					],
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UNION must contain a 'queries' array with at least two SELECTs.");

		$this->validator->validate($query);
	}

	public function testValidateUnionThrowsWhenSubqueryIsNotArray(): void {
		$query = [
			'type' => 'select',
			'union' => [
				'queries' => [
					'not-an-array',
					[
						'type' => 'select',
						'fields' => [
							['element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id']],
						],
					],
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UNION subquery #0 must be a valid SELECT query.");

		$this->validator->validate($query);
	}

	public function testValidateUnionThrowsWhenSubqueryTypeIsNotSelect(): void {
		$query = [
			'type' => 'select',
			'union' => [
				'queries' => [
					[
						'type' => 'insert',
						'fields' => [
							['element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id']],
						],
					],
					[
						'type' => 'select',
						'fields' => [
							['element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id']],
						],
					],
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Each UNION subquery must be of type 'select'.");

		$this->validator->validate($query);
	}

	public function testValidateUnionThrowsWhenSubqueryFieldsMissingOrEmpty(): void {
		$query = [
			'type' => 'select',
			'union' => [
				'queries' => [
					[
						'type' => 'select',
						// missing 'fields'
					],
					[
						'type' => 'select',
						'fields' => [
							['element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id']],
						],
					],
				],
			],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("UNION subquery #0 must contain a non-empty 'fields' array.");

		$this->validator->validate($query);
	}

	public function testValidatePassesForValidUnion(): void {
		$query = [
			'type' => 'select',
			'union' => [
				'queries' => [
					[
						'type' => 'select',
						'fields' => [
							['element' => ['type' => 'fld', 'table' => 'users', 'field' => 'id']],
						],
					],
					[
						'type' => 'select',
						'fields' => [
							['element' => ['type' => 'fld', 'table' => 'users', 'field' => 'name']],
						],
					],
				],
			],
		];

		// Should not throw
		$this->validator->validate($query);

		$this->assertTrue(true);
	}
}
