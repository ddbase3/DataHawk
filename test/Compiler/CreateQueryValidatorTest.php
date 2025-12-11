<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\CreateQueryValidator;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

class CreateQueryValidatorTest extends TestCase {

        public function testValidCreateQueryPasses(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type' => 'create',
                        'table' => 'users',
                        'columns' => [
                                [
                                        'name'          => 'id',
                                        'type'          => 'INT',
                                        'nullable'      => false,
                                        'auto_increment'=> true,
                                        'primary_key'   => true,
                                        'default'       => 1,
                                ],
                                [
                                        'name'    => 'username',
                                        'type'    => 'VARCHAR(50)',
                                        'nullable'=> true,
                                        'default' => 'guest',
                                ],
                                [
                                        'name'    => 'score',
                                        'type'    => 'INT',
                                        // nullable/auto_increment/primary_key nicht gesetzt -> ok
                                ],
                        ],
                ];

                // Erwartung: keine Exception
                $validator->validate($query);
                $this->expectNotToPerformAssertions();
        }

        public function testInvalidTypeThrowsException(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'   => 'select',
                        'table'  => 'users',
                        'columns'=> [],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Unsupported query type: select");

                $validator->validate($query);
        }

        public function testMissingTypeThrowsException(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        // kein type
                        'table'  => 'users',
                        'columns'=> [],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Unsupported query type: [not defined]");

                $validator->validate($query);
        }

        public function testInvalidTableNameEmptyOrWhitespaceThrows(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => '   ', // nur Spaces
                        'columns' => [
                                ['name' => 'id', 'type' => 'INT'],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("CREATE query must define a non-empty 'table' name.");

                $validator->validate($query);
        }

        public function testInvalidTableNameNonStringThrows(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => ['not-a-string'],
                        'columns' => [
                                ['name' => 'id', 'type' => 'INT'],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("CREATE query must define a non-empty 'table' name.");

                $validator->validate($query);
        }

        public function testMissingOrEmptyColumnsThrowsException(): void {
                $validator = new CreateQueryValidator();

                // columns fehlt
                $query1 = [
                        'type'  => 'create',
                        'table' => 'users',
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("CREATE query must contain a non-empty 'columns' array.");
                $validator->validate($query1);
        }

        public function testColumnsNotArrayThrowsException(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => 'not-an-array',
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("CREATE query must contain a non-empty 'columns' array.");

                $validator->validate($query);
        }

        public function testColumnEntryNotArrayThrowsException(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => [
                                'not-an-object',
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Each column entry must be an object.");

                $validator->validate($query);
        }

        public function testColumnMissingNameThrowsException(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => [
                                [
                                        // kein name
                                        'type' => 'INT',
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Column at index 0 is missing a valid 'name'.");

                $validator->validate($query);
        }

        public function testColumnWithEmptyNameThrowsException(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => [
                                [
                                        'name' => '   ', // nur Spaces
                                        'type' => 'INT',
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Column at index 0 is missing a valid 'name'.");

                $validator->validate($query);
        }

        public function testColumnMissingTypeThrowsException(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => [
                                [
                                        'name' => 'id',
                                        // kein type
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Column 'id' is missing a valid 'type'.");

                $validator->validate($query);
        }

        public function testColumnWithEmptyTypeThrowsException(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => [
                                [
                                        'name' => 'id',
                                        'type' => '   ',
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Column 'id' is missing a valid 'type'.");

                $validator->validate($query);
        }

        public function testNullableMustBeBooleanIfSet(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => [
                                [
                                        'name'     => 'id',
                                        'type'     => 'INT',
                                        'nullable' => 'yes', // falsch
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Column 'id': 'nullable' must be boolean.");

                $validator->validate($query);
        }

        public function testAutoIncrementMustBeBooleanIfSet(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => [
                                [
                                        'name'           => 'id',
                                        'type'           => 'INT',
                                        'auto_increment' => 1, // falsch
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Column 'id': 'auto_increment' must be boolean.");

                $validator->validate($query);
        }

        public function testPrimaryKeyMustBeBooleanIfSet(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => [
                                [
                                        'name'        => 'id',
                                        'type'        => 'INT',
                                        'primary_key' => 'yes', // falsch
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Column 'id': 'primary_key' must be boolean.");

                $validator->validate($query);
        }

        public function testDefaultMustBeScalarIfSet(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => [
                                [
                                        'name'    => 'meta',
                                        'type'    => 'TEXT',
                                        'default' => ['not', 'scalar'], // falsch
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Column 'meta': 'default' must be a scalar value.");

                $validator->validate($query);
        }

        public function testDefaultAllowsNullBecauseIssetCheck(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => [
                                [
                                        'name'    => 'meta',
                                        'type'    => 'TEXT',
                                        'default' => null, // isset() == false -> wird NICHT geprüft -> erlaubt
                                ],
                        ],
                ];

                // Erwartung: keine Exception
                $validator->validate($query);
                $this->expectNotToPerformAssertions();
        }

        public function testDisallowedTopLevelKeysCauseException(): void {
                $validator = new CreateQueryValidator();

                $query = [
                        'type'    => 'create',
                        'table'   => 'users',
                        'columns' => [
                                [
                                        'name' => 'id',
                                        'type' => 'INT',
                                ],
                        ],
                        'where' => ['something'], // disallowed key
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("CREATE query must not contain 'where'.");

                $validator->validate($query);
        }
}
