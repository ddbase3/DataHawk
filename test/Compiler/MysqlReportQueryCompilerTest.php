<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Api\IReportQueryTypeCompiler;
use DataHawk\Api\IReportQueryValidator;
use DataHawk\Compiler\MysqlReportQueryCompiler;
use DataHawk\Compiler\QueryCompilerFactory;
use DataHawk\Compiler\QueryValidatorFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

class MysqlReportQueryCompilerTest extends TestCase {

	/**
	 * Builds a MysqlReportQueryCompiler with custom validator/compiler factories
	 * injected via reflection.
	 *
	 * @param IReportQueryValidator&MockObject $validator
	 * @param IReportQueryTypeCompiler&MockObject $typeCompiler
	 * @param QueryValidatorFactory|null $validatorFactoryOut
	 * @param QueryCompilerFactory|null $compilerFactoryOut
	 */
	private function makeCompilerWithInjectedFactories(
		IReportQueryValidator $validator,
		IReportQueryTypeCompiler $typeCompiler,
		?QueryValidatorFactory &$validatorFactoryOut = null,
		?QueryCompilerFactory &$compilerFactoryOut = null
	): MysqlReportQueryCompiler {
		$schemaProvider = $this->createStub(IQuerySchemaProvider::class);

		$compiler = new MysqlReportQueryCompiler($schemaProvider);

		// Anonymous subclass of QueryValidatorFactory that records last type.
		$validatorFactory = new class($validator) extends QueryValidatorFactory {
			public string $lastType = '';
			private IReportQueryValidator $validator;

			public function __construct(IReportQueryValidator $validator) {
				$this->validator = $validator;
			}

			public function getValidator(string $type): IReportQueryValidator {
				$this->lastType = $type;
				return $this->validator;
			}
		};

		// Anonymous subclass of QueryCompilerFactory that records last type.
		$compilerFactory = new class($typeCompiler) extends QueryCompilerFactory {
			public string $lastType = '';
			private IReportQueryTypeCompiler $typeCompiler;

			public function __construct(IReportQueryTypeCompiler $typeCompiler) {
				// We don't need parent constructor behavior for these tests,
				// we just override getCompiler() completely.
				$this->typeCompiler = $typeCompiler;
			}

			public function getCompiler(string $type): IReportQueryTypeCompiler {
				$this->lastType = $type;
				return $this->typeCompiler;
			}
		};

		$ref = new \ReflectionClass($compiler);

		$vfProp = $ref->getProperty('validatorFactory');
		$vfProp->setAccessible(true);
		$vfProp->setValue($compiler, $validatorFactory);

		$cfProp = $ref->getProperty('compilerFactory');
		$cfProp->setAccessible(true);
		$cfProp->setValue($compiler, $compilerFactory);

		$validatorFactoryOut = $validatorFactory;
		$compilerFactoryOut = $compilerFactory;

		return $compiler;
	}

	private function extractSql(QueryStatement $stmt): string {
		if (method_exists($stmt, '__toString')) {
			return (string)$stmt;
		}

		if (method_exists($stmt, 'getSql')) {
			return $stmt->getSql();
		}

		if (property_exists($stmt, 'sql')) {
			/** @var mixed $stmt */
			return $stmt->sql;
		}

		$this->fail('QueryStatement has no accessible SQL representation.');
	}

	public function testCompileUsesSelectAsDefaultTypeWhenMissing(): void {
		/** @var IReportQueryValidator&MockObject $validatorMock */
		$validatorMock = $this->createMock(IReportQueryValidator::class);
		/** @var IReportQueryTypeCompiler&MockObject $typeCompilerMock */
		$typeCompilerMock = $this->createMock(IReportQueryTypeCompiler::class);

		$query = [
			// no 'type' key -> default 'select'
			'table' => 'users',
		];

		$validatorMock
			->expects($this->once())
			->method('validate')
			->with($this->equalTo($query));

		$typeCompilerMock
			->expects($this->once())
			->method('compile')
			->with($this->equalTo($query))
			->willReturn(new QueryStatement('SELECT 1', [], [], false));

		$validatorFactory = null;
		$compilerFactory = null;

		$compiler = $this->makeCompilerWithInjectedFactories(
			$validatorMock,
			$typeCompilerMock,
			$validatorFactory,
			$compilerFactory
		);

		$result = $compiler->compile($query);
		$sql = $this->extractSql($result);

		$this->assertSame('SELECT 1', $sql);
		$this->assertInstanceOf(QueryValidatorFactory::class, $validatorFactory);
		$this->assertInstanceOf(QueryCompilerFactory::class, $compilerFactory);
		$this->assertSame('select', $validatorFactory->lastType);
		$this->assertSame('select', $compilerFactory->lastType);
	}

	public function testCompileUsesExplicitTypeAndDelegatesToValidatorAndCompiler(): void {
		/** @var IReportQueryValidator&MockObject $validatorMock */
		$validatorMock = $this->createMock(IReportQueryValidator::class);
		/** @var IReportQueryTypeCompiler&MockObject $typeCompilerMock */
		$typeCompilerMock = $this->createMock(IReportQueryTypeCompiler::class);

		$query = [
			'type' => 'insert',
			'table' => 'users',
			'values' => [
				['id' => 1, 'name' => 'Alice'],
			],
		];

		$validatorMock
			->expects($this->once())
			->method('validate')
			->with($this->equalTo($query));

		$typeCompilerMock
			->expects($this->once())
			->method('compile')
			->with($this->equalTo($query))
			->willReturn(new QueryStatement('INSERT SQL', [], [], false));

		$validatorFactory = null;
		$compilerFactory = null;

		$compiler = $this->makeCompilerWithInjectedFactories(
			$validatorMock,
			$typeCompilerMock,
			$validatorFactory,
			$compilerFactory
		);

		$result = $compiler->compile($query);
		$sql = $this->extractSql($result);

		$this->assertSame('INSERT SQL', $sql);
		$this->assertSame('insert', $validatorFactory->lastType);
		$this->assertSame('insert', $compilerFactory->lastType);
	}

	public function testValidationExceptionIsPropagated(): void {
		/** @var IReportQueryValidator&MockObject $validatorMock */
		$validatorMock = $this->createMock(IReportQueryValidator::class);
		/** @var IReportQueryTypeCompiler&MockObject $typeCompilerMock */
		$typeCompilerMock = $this->createMock(IReportQueryTypeCompiler::class);

		$query = [
			'type' => 'delete',
			'table' => 'users',
		];

		$validatorMock
			->expects($this->once())
			->method('validate')
			->with($this->equalTo($query))
			->willThrowException(new QueryValidationException('invalid delete'));

		$typeCompilerMock
			->expects($this->never())
			->method('compile');

		$validatorFactory = null;
		$compilerFactory = null;

		$compiler = $this->makeCompilerWithInjectedFactories(
			$validatorMock,
			$typeCompilerMock,
			$validatorFactory,
			$compilerFactory
		);

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage('invalid delete');

		$compiler->compile($query);
	}
}
