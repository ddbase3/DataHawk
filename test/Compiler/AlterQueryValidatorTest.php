<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\AlterQueryValidator;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

class AlterQueryValidatorTest extends TestCase {

        public function testValidAlterQueryWithAllActionTypesPasses(): void {
                $validator = new AlterQueryValidator();

                $query = [
                        'type' => 'alter',
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action' => 'ADD_COLUMN',
                                        'name'   => 'age',
                                        'type'   => 'INT',
                                ],
                                [
                                        'action' => 'modify_column',
                                        'name'   => 'status',
                                        'type'   => 'VARCHAR(20)',
                                ],
                                [
                                        'action' => 'DROP_COLUMN',
                                        'name'   => 'old_field',
                                ],
                                [
                                        'action' => 'RENAME_COLUMN',
                                        'from'   => 'old_name',
                                        'to'     => 'new_name',
                                ],
                                [
                                        'action' => 'CHANGE_COLUMN',
                                        'from'   => 'old_amount',
                                        'to'     => 'amount',
                                        'type'   => 'DECIMAL(10,2)',
                                ],
                        ],
                ];

                // Erwartung: keine Exception
                $validator->validate($query);

                $this->expectNotToPerformAssertions();
        }

        public function testInvalidTypeThrowsException(): void {
                $validator = new AlterQueryValidator();

                $query = [
                        'type'   => 'select',
                        'table'  => 'users',
                        'actions'=> [],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Invalid query type for ALTER.");

                $validator->validate($query);
        }

        public function testMissingTypeThrowsException(): void {
            $validator = new AlterQueryValidator();

            $query = [
                    // kein type
                    'table'  => 'users',
                    'actions'=> [],
            ];

            $this->expectException(QueryValidationException::class);
            $this->expectExceptionMessage("Invalid query type for ALTER.");

            $validator->validate($query);
        }

        public function testMissingOrInvalidTableThrowsException(): void {
                $validator = new AlterQueryValidator();

                // leeres table
                $query1 = [
                        'type'    => 'alter',
                        'table'   => '',
                        'actions' => [],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("ALTER query must include a 'table' name.");
                $validator->validate($query1);
        }

        public function testNonStringTableThrowsException(): void {
                $validator = new AlterQueryValidator();

                $query = [
                        'type'    => 'alter',
                        'table'   => ['not', 'a', 'string'],
                        'actions' => [],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("ALTER query must include a 'table' name.");

                $validator->validate($query);
        }

        public function testMissingOrEmptyActionsThrowsException(): void {
                $validator = new AlterQueryValidator();

                // actions fehlt
                $query1 = [
                        'type'  => 'alter',
                        'table' => 'users',
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("ALTER query must include a non-empty 'actions' array.");
                $validator->validate($query1);
        }

        public function testActionsNotArrayThrowsException(): void {
                $validator = new AlterQueryValidator();

                $query = [
                        'type'    => 'alter',
                        'table'   => 'users',
                        'actions' => 'not-an-array',
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("ALTER query must include a non-empty 'actions' array.");

                $validator->validate($query);
        }

        public function testActionWithoutActionFieldThrowsException(): void {
                $validator = new AlterQueryValidator();

                $query = [
                        'type'  => 'alter',
                        'table' => 'users',
                        'actions' => [
                                [],                         // index 0
                                ['name' => 'foo'],          // index 1
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Action #0 must contain an 'action' field.");

                $validator->validate($query);
        }

        public function testAddOrModifyColumnRequiresNameAndType(): void {
                $validator = new AlterQueryValidator();

                // ADD_COLUMN ohne name
                $query1 = [
                        'type'  => 'alter',
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action' => 'add_column',
                                        'type'   => 'INT',
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Action #0 (add_column) requires 'name' and 'type'.");
                $validator->validate($query1);
        }

        public function testAddColumnMissingTypeThrowsException(): void {
                $validator = new AlterQueryValidator();

                // ADD_COLUMN ohne type
                $query2 = [
                        'type'  => 'alter',
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action' => 'ADD_COLUMN',
                                        'name'   => 'age',
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Action #0 (add_column) requires 'name' and 'type'.");
                $validator->validate($query2);
        }

        public function testModifyColumnMissingFieldsThrowException(): void {
                $validator = new AlterQueryValidator();

                $query = [
                        'type'  => 'alter',
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action' => 'MODIFY_COLUMN',
                                        'name'   => '',
                                        'type'   => '',
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Action #0 (modify_column) requires 'name' and 'type'.");
                $validator->validate($query);
        }

        public function testDropColumnRequiresName(): void {
                $validator = new AlterQueryValidator();

                $query = [
                        'type'  => 'alter',
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action' => 'DROP_COLUMN',
                                        // kein name
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Action #0 (drop_column) requires 'name'.");
                $validator->validate($query);
        }

        public function testRenameColumnRequiresFromAndTo(): void {
                $validator = new AlterQueryValidator();

                $query = [
                        'type'  => 'alter',
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action' => 'RENAME_COLUMN',
                                        'from'   => 'old_name',
                                        // 'to' fehlt
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Action #0 (rename_column) requires 'from' and 'to'.");
                $validator->validate($query);
        }

        public function testChangeColumnRequiresFromToAndType(): void {
                $validator = new AlterQueryValidator();

                $query = [
                        'type'  => 'alter',
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action' => 'CHANGE_COLUMN',
                                        'from'   => 'old_col',
                                        'to'     => 'new_col',
                                        // 'type' fehlt
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Action #0 (change_column) requires 'from', 'to', and 'type'.");
                $validator->validate($query);
        }

        public function testUnsupportedActionThrowsException(): void {
                $validator = new AlterQueryValidator();

                $query = [
                        'type'  => 'alter',
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action' => 'SOMETHING_WEIRD',
                                        'name'   => 'x',
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Unsupported ALTER action: 'SOMETHING_WEIRD' in action #0.");

                $validator->validate($query);
        }
}
