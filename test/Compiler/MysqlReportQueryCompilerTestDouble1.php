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

final class MysqlReportQueryCompilerTestDouble1 extends QueryValidatorFactory {
			public string $lastType = '';
			private IReportQueryValidator $validator;

			public function __construct(IReportQueryValidator $validator) {
				$this->validator = $validator;
			}

			public function getValidator(string $type): IReportQueryValidator {
				$this->lastType = $type;
				return $this->validator;
			}
		}
