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

use Base3\DataHawk\Api\IReportQueryTypeCompiler;
use Base3\DataHawk\Api\IReportQueryValidator;
use Base3\DataHawk\Compiler\MysqlReportQueryCompiler;
use Base3\DataHawk\Compiler\QueryCompilerFactory;
use Base3\DataHawk\Compiler\QueryValidatorFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Api\IQuerySchemaProvider;
use Base3\ResourceFoundation\Dto\QueryStatement;
use Base3\ResourceFoundation\Exception\QueryValidationException;

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
