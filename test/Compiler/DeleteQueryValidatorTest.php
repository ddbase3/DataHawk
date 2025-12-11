<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\DeleteQueryValidator;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

class DeleteQueryValidatorTest extends TestCase {

        public function testValidDeleteQueryPasses(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => 'users',
                        'where' => [
                                'type'  => 'op',
                                'op'    => '=',
                                'left'  => ['type' => 'fld', 'name' => 'id'],
                                'right' => ['type' => 'const', 'value' => 1],
                        ],
                        'order_by' => [
                                [
                                        'element'   => ['type' => 'fld', 'name' => 'id'],
                                        'direction' => 'DESC',
                                ],
                        ],
                        'limit' => 10,
                ];

                // Erwartung: keine Exception
                $validator->validate($query);
                $this->expectNotToPerformAssertions();
        }

        public function testValidDeleteQueryWithoutOrderByAndLimitPasses(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => 'log',
                        'where' => [
                                'type'  => 'const',
                                'value' => 1,
                        ],
                ];

                // Erwartung: keine Exception
                $validator->validate($query);
                $this->expectNotToPerformAssertions();
        }

        public function testInvalidTypeThrowsException(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'select',
                        'table' => 'users',
                        'where' => ['type' => 'const', 'value' => 1],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Unsupported query type: select");

                $validator->validate($query);
        }

        public function testMissingTypeThrowsException(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'table' => 'users',
                        'where' => ['type' => 'const', 'value' => 1],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Unsupported query type: [not defined]");

                $validator->validate($query);
        }

        public function testMissingOrInvalidTableThrowsException(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => '',  // leer
                        'where' => ['type' => 'const', 'value' => 1],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("DELETE query must define a valid 'table'.");

                $validator->validate($query);
        }

        public function testNonStringTableThrowsException(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => ['not', 'a', 'string'],
                        'where' => ['type' => 'const', 'value' => 1],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("DELETE query must define a valid 'table'.");

                $validator->validate($query);
        }

        public function testMissingWhereClauseThrowsException(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => 'users',
                        // kein where
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("DELETE query must contain a 'where' clause to avoid full deletions.");

                $validator->validate($query);
        }

        public function testWhereNotArrayThrowsException(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => 'users',
                        'where' => 'id = 1', // kein Array
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("DELETE query must contain a 'where' clause to avoid full deletions.");

                $validator->validate($query);
        }

        public function testEmptyWhereArrayThrowsException(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => 'users',
                        'where' => [], // leer
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("DELETE query must contain a 'where' clause to avoid full deletions.");

                $validator->validate($query);
        }

        public function testMissingElementInOrderByThrowsException(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => 'users',
                        'where' => ['type' => 'const', 'value' => 1],
                        'order_by' => [
                                [
                                        // 'element' fehlt
                                        'direction' => 'ASC',
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Missing element in order_by clause.");

                $validator->validate($query);
        }

        public function testInvalidOrderDirectionThrowsException(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => 'users',
                        'where' => ['type' => 'const', 'value' => 1],
                        'order_by' => [
                                [
                                        'element'   => ['type' => 'fld', 'name' => 'id'],
                                        'direction' => 'UP', // ungültig
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Invalid order direction: UP");

                $validator->validate($query);
        }

        public function testOrderDirectionDefaultAscIsAccepted(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => 'users',
                        'where' => ['type' => 'const', 'value' => 1],
                        'order_by' => [
                                [
                                        'element' => ['type' => 'fld', 'name' => 'id'],
                                        // direction fehlt -> default ASC -> gültig
                                ],
                        ],
                ];

                // Erwartung: keine Exception
                $validator->validate($query);
                $this->expectNotToPerformAssertions();
        }

        public function testLimitMustBeIntegerIfSet(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => 'users',
                        'where' => ['type' => 'const', 'value' => 1],
                        'limit' => '10', // string, nicht int
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("LIMIT must be an integer.");

                $validator->validate($query);
        }

        public function testIntegerLimitIsAccepted(): void {
                $validator = new DeleteQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => 'users',
                        'where' => ['type' => 'const', 'value' => 1],
                        'limit' => 10,
                ];

                // Erwartung: keine Exception
                $validator->validate($query);
                $this->expectNotToPerformAssertions();
        }
}
