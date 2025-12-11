<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\AliasResolver;
use DataHawk\Compiler\ElementCompiler;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

class ElementCompilerTest extends TestCase {

        private function makeCompiler(?AliasResolver &$resolverRef = null): ElementCompiler {
                $resolver = new AliasResolver();
                $resolverRef = $resolver;

                // Dummy-Compiler, der nur ein Objekt mit sql-Eigenschaft zurückgibt
                $dummyCompiler = new class {
                        public function compile(array $query): object {
                                $o = new \stdClass();
                                $o->sql = 'SELECT 1';
                                return $o;
                        }
                };

                return new ElementCompiler($resolver, $dummyCompiler);
        }

        // ---------------------------------------------------------------------
        //  quoteIdentifier / quoteLiteral
        // ---------------------------------------------------------------------

        public function testQuoteIdentifierEscapesBackticks(): void {
                $compiler = $this->makeCompiler();

                $ref = new \ReflectionClass($compiler);
                $method = $ref->getMethod('quoteIdentifier');
                $method->setAccessible(true);

                $result = $method->invoke($compiler, 'my`table');
                $this->assertSame('`my``table`', $result);
        }

        public function testQuoteLiteralHandlesNullBooleanNumericAndString(): void {
            $compiler = $this->makeCompiler();

            $ref = new \ReflectionClass($compiler);
            $method = $ref->getMethod('quoteLiteral');
            $method->setAccessible(true);

            $this->assertSame('NULL', $method->invoke($compiler, null));
            $this->assertSame('TRUE', $method->invoke($compiler, true));
            $this->assertSame('FALSE', $method->invoke($compiler, false));
            $this->assertSame('123', $method->invoke($compiler, 123));
            $this->assertSame('3.14', $method->invoke($compiler, 3.14));
            $this->assertSame("'test'", $method->invoke($compiler, 'test'));
            $this->assertSame("'O''Reilly'", $method->invoke($compiler, "O'Reilly"));
        }

        // ---------------------------------------------------------------------
        //  compileElement – Skalare & Listen
        // ---------------------------------------------------------------------

        public function testCompileElementWithScalarsUsesQuoteLiteral(): void {
                $compiler = $this->makeCompiler();

                $this->assertSame("'foo'", $compiler->compileElement('foo'));
                $this->assertSame('42', $compiler->compileElement(42));
                $this->assertSame('TRUE', $compiler->compileElement(true));
                $this->assertSame('NULL', $compiler->compileElement(null));
        }

        public function testCompileElementWithSimpleListArrayReturnsCommaSeparatedWithoutParens(): void {
                $compiler = $this->makeCompiler();

                $list = ['a', 'b', 1];
                $sql = $compiler->compileElement($list);

                $this->assertSame("'a', 'b', 1", $sql);
        }

        public function testCompileElementWithStructuredListArrayIsWrappedInParens(): void {
                $compiler = $this->makeCompiler();

                $list = [
                        'a',
                        ['type' => 'fld', 'field' => 'col'],
                        2,
                ];

                $sql = $compiler->compileElement($list);

                $this->assertSame("'a', `col`, 2", trim($this->stripOuterParens($sql)));
                $this->assertStringStartsWith('(', $sql);
                $this->assertStringEndsWith(')', $sql);
        }

        private function stripOuterParens(string $s): string {
                if (str_starts_with($s, '(') && str_ends_with($s, ')')) {
                        return substr($s, 1, -1);
                }
                return $s;
        }

        public function testCompileElementWithInvalidAssocArrayWithoutTypeThrows(): void {
                $compiler = $this->makeCompiler();

                $invalid = ['foo' => 'bar'];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Invalid array element structure:");

                $compiler->compileElement($invalid);
        }

        public function testCompileElementWithCompletelyInvalidElementThrows(): void {
                $compiler = $this->makeCompiler();

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Invalid element structure:");

                $compiler->compileElement(new \stdClass());
        }

        public function testCompileElementWithUnsupportedTypeThrows(): void {
                $compiler = $this->makeCompiler();

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Unsupported element type: weird");

                $compiler->compileElement(['type' => 'weird']);
        }

        // ---------------------------------------------------------------------
        //  compileField
        // ---------------------------------------------------------------------

        public function testCompileFieldWithoutTable(): void {
                $compiler = $this->makeCompiler();

                $sql = $compiler->compileElement([
                        'type'  => 'fld',
                        'field' => 'name',
                ]);

                $this->assertSame('`name`', $sql);
        }

        public function testCompileFieldWildcardWithoutTable(): void {
                $compiler = $this->makeCompiler();

                $sql = $compiler->compileElement([
                        'type'  => 'fld',
                        'field' => '*',
                ]);

                $this->assertSame('*', $sql);
        }

        public function testCompileFieldWithTableUsesAliasResolverIfAvailable(): void {
                $compiler = $this->makeCompiler($resolver);

                // Registriere Alias u -> users
                $resolver->registerAlias('u', 'users');

                $sql = $compiler->compileElement([
                        'type'  => 'fld',
                        'field' => 'name',
                        'table' => 'users',
                ]);

                $this->assertSame('`u`.`name`', $sql);
        }

        public function testCompileFieldWithExplicitTableAliasOverridesResolver(): void {
                $compiler = $this->makeCompiler($resolver);

                $resolver->registerAlias('u', 'users');

                $sql = $compiler->compileElement([
                        'type'       => 'fld',
                        'field'      => 'name',
                        'table'      => 'users',
                        'tablealias' => 'usr',
                ]);

                $this->assertSame('`usr`.`name`', $sql);
        }

        public function testCompileFieldMissingFieldKeyThrows(): void {
                $compiler = $this->makeCompiler();

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Field reference must contain a 'field' key.");

                $compiler->compileElement([
                        'type'  => 'fld',
                        // 'field' fehlt
                ]);
        }

        // ---------------------------------------------------------------------
        //  compileFunction
        // ---------------------------------------------------------------------

        public function testCompileSimpleFunctionWithDistinct(): void {
                $compiler = $this->makeCompiler();

                $sql = $compiler->compileElement([
                        'type'     => 'fn',
                        'function' => 'sum',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'amount'],
                        ],
                        'distinct' => true,
                ]);

                $this->assertSame('SUM(DISTINCT `amount`)', $sql);
        }

        public function testCompileGroupConcatWithSeparator(): void {
                $compiler = $this->makeCompiler();

                $sql = $compiler->compileElement([
                        'type'     => 'fn',
                        'function' => 'GROUP_CONCAT',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'name'],
                                ', ',
                        ],
                ]);

                $this->assertSame("GROUP_CONCAT(`name` SEPARATOR ', ')", $sql);
        }

        public function testGroupConcatWithNonStringSeparatorThrows(): void {
                $compiler = $this->makeCompiler();

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("GROUP_CONCAT separator must be a string literal.");

                $compiler->compileElement([
                        'type'     => 'fn',
                        'function' => 'GROUP_CONCAT',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'name'],
                                123, // kein String
                        ],
                ]);
        }

        public function testCastFunction(): void {
                $compiler = $this->makeCompiler();

                $sql = $compiler->compileElement([
                        'type'     => 'fn',
                        'function' => 'cast',
                        'params'   => [
                                123,
                                'CHAR(10)',
                        ],
                ]);

                $this->assertSame("CAST(123 AS CHAR(10))", $sql);
        }

        public function testCastWithInvalidParamsThrows(): void {
                $compiler = $this->makeCompiler();

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("CAST expects 2 parameters: value and type string.");

                $compiler->compileElement([
                        'type'     => 'fn',
                        'function' => 'CAST',
                        'params'   => [
                                123,
                                ['not-a-string'],
                        ],
                ]);
        }

        public function testConvertFunction(): void {
                $compiler = $this->makeCompiler();

                $sql = $compiler->compileElement([
                        'type'     => 'fn',
                        'function' => 'convert',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'name'],
                                'utf8',
                        ],
                ]);

                $this->assertSame("CONVERT(`name` USING UTF8)", $sql);
        }

        public function testFunctionMissingFunctionOrParamsThrows(): void {
                $compiler = $this->makeCompiler();

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Function must have 'function' and 'params'.");

                $compiler->compileElement([
                        'type'   => 'fn',
                        'params' => [],
                ]);
        }

        // ---------------------------------------------------------------------
        //  compileWindowFunction
        // ---------------------------------------------------------------------

        public function testCompileSimpleWindowFunctionWithEmptyOver(): void {
                $compiler = $this->makeCompiler();

                $sql = $compiler->compileElement([
                        'type'     => 'windowfn',
                        'function' => 'ROW_NUMBER',
                        'params'   => [],
                        // kein 'over' -> treated as empty
                ]);

                $this->assertSame('ROW_NUMBER() OVER ()', $sql);
        }

        public function testCompileWindowFunctionWithPartitionAndOrderBy(): void {
                $compiler = $this->makeCompiler();

                $sql = $compiler->compileElement([
                        'type'     => 'windowfn',
                        'function' => 'sum',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'amount'],
                        ],
                        'over' => [
                                'partition_by' => [
                                        ['type' => 'fld', 'field' => 'category'],
                                ],
                                'order_by' => [
                                        [
                                                'expression' => ['type' => 'fld', 'field' => 'created_at'],
                                                'direction'  => 'DESC',
                                        ],
                                ],
                        ],
                ]);

                $this->assertSame('SUM(`amount`) OVER (PARTITION BY `category` ORDER BY `created_at` DESC)', $sql);
        }

        public function testWindowFunctionOrderByMissingExpressionThrows(): void {
                $compiler = $this->makeCompiler();

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Each ORDER BY entry must have an 'expression'.");

                $compiler->compileElement([
                        'type'     => 'windowfn',
                        'function' => 'sum',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'amount'],
                        ],
                        'over' => [
                                'order_by' => [
                                        [
                                                // 'expression' fehlt
                                                'direction' => 'ASC',
                                        ],
                                ],
                        ],
                ]);
        }

        public function testWindowFunctionOrderByInvalidDirectionThrows(): void {
                $compiler = $this->makeCompiler();

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("ORDER BY direction must be ASC or DESC.");

                $compiler->compileElement([
                        'type'     => 'windowfn',
                        'function' => 'sum',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'amount'],
                        ],
                        'over' => [
                                'order_by' => [
                                        [
                                                'expression' => ['type' => 'fld', 'field' => 'created_at'],
                                                'direction'  => 'UP', // ungültig
                                        ],
                                ],
                        ],
                ]);
        }

        // ---------------------------------------------------------------------
        //  compileOperation
        // ---------------------------------------------------------------------

        public function testOperationIsNullAndBetween(): void {
                $compiler = $this->makeCompiler();

                // IS NULL
                $sql1 = $compiler->compileElement([
                        'type'     => 'op',
                        'operator' => 'IS NULL',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'deleted_at'],
                        ],
                ]);

                $this->assertSame('`deleted_at` IS NULL', $sql1);

                // BETWEEN
                $sql2 = $compiler->compileElement([
                        'type'     => 'op',
                        'operator' => 'between',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'age'],
                                18,
                                30,
                        ],
                ]);

                $this->assertSame('`age` BETWEEN 18 AND 30', $sql2);
        }

        public function testOperationInAndNotInExistsAndNotExistsAndDefault(): void {
                $compiler = $this->makeCompiler();

                // IN
                $sqlIn = $compiler->compileElement([
                        'type'     => 'op',
                        'operator' => 'IN',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'id'],
                                1,
                                2,
                                3,
                        ],
                ]);

                $this->assertSame('`id` IN (1, 2, 3)', $sqlIn);

                // NOT IN
                $sqlNotIn = $compiler->compileElement([
                        'type'     => 'op',
                        'operator' => 'not in',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'id'],
                                1,
                        ],
                ]);

                $this->assertSame('`id` NOT IN (1)', $sqlNotIn);

                // EXISTS
                $sqlExists = $compiler->compileElement([
                        'type'     => 'op',
                        'operator' => 'EXISTS',
                        'params'   => [
                                ['type' => 'subquery', 'query' => ['dummy' => true]],
                        ],
                ]);

                $this->assertSame('EXISTS ((SELECT 1))', $sqlExists);

                // Default-Operator z.B. "=" mit 2 Parametern
                $sqlDefault = $compiler->compileElement([
                        'type'     => 'op',
                        'operator' => '=',
                        'params'   => [
                                ['type' => 'fld', 'field' => 'id'],
                                5,
                        ],
                ]);

                $this->assertSame('(`id` = 5)', $sqlDefault);
        }

        // ---------------------------------------------------------------------
        //  compileSubquery
        // ---------------------------------------------------------------------

        public function testCompileSubqueryUsesCompilerAndWrapsSqlInParens(): void {
                $compiler = $this->makeCompiler();

                $sql = $compiler->compileElement([
                        'type'  => 'subquery',
                        'query' => ['whatever' => true],
                ]);

                $this->assertSame('(SELECT 1)', $sql);
        }

        public function testSubqueryMissingQueryThrows(): void {
                $compiler = $this->makeCompiler();

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Subquery must have 'query'.");

                $compiler->compileElement([
                        'type' => 'subquery',
                ]);
        }

        // ---------------------------------------------------------------------
        //  compileCase
        // ---------------------------------------------------------------------

        public function testCompileCaseWithWhenThenElse(): void {
                $compiler = $this->makeCompiler();

                $sql = $compiler->compileElement([
                        'type'  => 'case',
                        'cases' => [
                                [
                                        'when' => ['type' => 'fld', 'field' => 'age'],
                                        'then' => 'young',
                                ],
                                [
                                        'when' => 30,
                                        'then' => 'old',
                                ],
                        ],
                        'else' => 'unknown',
                ]);

                $this->assertSame(
                        "CASE WHEN `age` THEN 'young' WHEN 30 THEN 'old' ELSE 'unknown' END",
                        $sql
                );
        }

        public function testCaseWithoutElseUsesNullAndValidatesCases(): void {
                $compiler = $this->makeCompiler();

                $sql = $compiler->compileElement([
                        'type'  => 'case',
                        'cases' => [
                                [
                                        'when' => 1,
                                        // then fehlt -> soll als NULL kompiliert werden
                                ],
                        ],
                ]);

                $this->assertSame("CASE WHEN 1 THEN NULL END", $sql);
        }

        public function testCaseWithoutCasesThrows(): void {
                $compiler = $this->makeCompiler();

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("CASE expression must have a 'cases' array.");

                $compiler->compileElement([
                        'type' => 'case',
                ]);
        }

        public function testCaseEntryMissingWhenThrows(): void {
                $compiler = $this->makeCompiler();

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("CASE entry missing 'when'.");

                $compiler->compileElement([
                        'type'  => 'case',
                        'cases' => [
                                [
                                        // 'when' fehlt
                                        'then' => 'x',
                                ],
                        ],
                ]);
        }
}
