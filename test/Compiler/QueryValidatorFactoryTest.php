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

use Base3\DataHawk\Compiler\AlterQueryValidator;
use Base3\DataHawk\Compiler\CreateQueryValidator;
use Base3\DataHawk\Compiler\DeleteQueryValidator;
use Base3\DataHawk\Compiler\DropQueryValidator;
use Base3\DataHawk\Compiler\InsertQueryValidator;
use Base3\DataHawk\Compiler\QueryValidatorFactory;
use Base3\DataHawk\Compiler\RenameQueryValidator;
use Base3\DataHawk\Compiler\SelectQueryValidator;
use Base3\DataHawk\Compiler\TruncateQueryValidator;
use Base3\DataHawk\Compiler\UpdateQueryValidator;

use Base3\DataHawk\Api\IReportQueryValidator;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Exception\QueryValidationException;

class QueryValidatorFactoryTest extends TestCase {

	private QueryValidatorFactory $factory;

	protected function setUp(): void {
		$this->factory = new QueryValidatorFactory();
	}

	public function testGetValidatorReturnsConcreteValidatorForKnownTypes(): void {
		$map = [
			'select'   => SelectQueryValidator::class,
			'insert'   => InsertQueryValidator::class,
			'update'   => UpdateQueryValidator::class,
			'delete'   => DeleteQueryValidator::class,
			'truncate' => TruncateQueryValidator::class,
			'drop'     => DropQueryValidator::class,
			'rename'   => RenameQueryValidator::class,
			'create'   => CreateQueryValidator::class,
			'alter'    => AlterQueryValidator::class,
		];

		foreach ($map as $type => $expectedClass) {
			$validator = $this->factory->getValidator($type);

			$this->assertInstanceOf(
				$expectedClass,
				$validator,
				"Factory should return $expectedClass for type '$type'"
			);

			$this->assertInstanceOf(
				IReportQueryValidator::class,
				$validator,
				"Validator for type '$type' should implement IReportQueryValidator"
			);
		}
	}

	public function testGetValidatorThrowsForUnsupportedType(): void {
		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("Unsupported query type: foo");

		$this->factory->getValidator('foo');
	}
}
