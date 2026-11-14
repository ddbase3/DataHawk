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

use Base3\DataHawk\Compiler\AliasResolver;
use Base3\DataHawk\Compiler\ElementCompiler;
use PHPUnit\Framework\TestCase;
use Base3\ResourceFoundation\Exception\QueryValidationException;

final class ElementCompilerTestDouble1 {
                        public function compile(array $query): object {
                                $o = new \stdClass();
                                $o->sql = 'SELECT 1';
                                return $o;
                        }
                }
