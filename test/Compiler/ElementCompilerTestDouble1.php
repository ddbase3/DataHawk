<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\AliasResolver;
use DataHawk\Compiler\ElementCompiler;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

final class ElementCompilerTestDouble1 {
                        public function compile(array $query): object {
                                $o = new \stdClass();
                                $o->sql = 'SELECT 1';
                                return $o;
                        }
                }
