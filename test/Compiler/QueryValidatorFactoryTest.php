<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryValidator;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

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
