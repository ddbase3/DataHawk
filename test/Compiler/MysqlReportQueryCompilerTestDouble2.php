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

final class MysqlReportQueryCompilerTestDouble2 extends QueryCompilerFactory {
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
		}
