<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\AlterQueryCompiler;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Dto\QueryStatement;

class AlterQueryCompilerTest extends TestCase {

        private function extractSql(QueryStatement $stmt): string {
                // Versuche verschiedene wahrscheinliche Varianten, um an das SQL zu kommen,
                // damit der Test flexibel gegenüber deiner QueryStatement-Implementierung ist.
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

        public function testCompileAddColumnNotNullWithNumericDefault(): void {
                $compiler = new AlterQueryCompiler();

                $query = [
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action'   => 'ADD_COLUMN',
                                        'name'     => 'age',
                                        'type'     => 'INT',
                                        'nullable' => false,
                                        'default'  => 0,
                                ],
                        ],
                ];

                $stmt = $compiler->compile($query);
                $sql  = $this->extractSql($stmt);

                $expected = "ALTER TABLE `users`\n  ADD COLUMN `age` INT NOT NULL DEFAULT 0";
                $this->assertSame($expected, $sql);
        }

        public function testCompileAddColumnNullableWithoutDefault(): void {
                $compiler = new AlterQueryCompiler();

                $query = [
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action'   => 'ADD_COLUMN',
                                        'name'     => 'nickname',
                                        'type'     => 'VARCHAR(50)',
                                        // nullable nicht gesetzt -> default true -> kein "NOT NULL"
                                ],
                        ],
                ];

                $stmt = $compiler->compile($query);
                $sql  = $this->extractSql($stmt);

                $expected = "ALTER TABLE `users`\n  ADD COLUMN `nickname` VARCHAR(50)";
                $this->assertSame($expected, $sql);
        }

        public function testCompileModifyColumnWithStringDefaultAndNotNull(): void {
                $compiler = new AlterQueryCompiler();

                $query = [
                        'table' => 'accounts',
                        'actions' => [
                                [
                                        'action'   => 'MODIFY_COLUMN',
                                        'name'     => 'status',
                                        'type'     => 'VARCHAR(20)',
                                        'nullable' => false,
                                        'default'  => "active",
                                ],
                        ],
                ];

                $stmt = $compiler->compile($query);
                $sql  = $this->extractSql($stmt);

                $expected = "ALTER TABLE `accounts`\n  MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active'";
                $this->assertSame($expected, $sql);
        }

        public function testCompileRenameAndChangeAndDropColumnCombined(): void {
                $compiler = new AlterQueryCompiler();

                $query = [
                        'table' => 'orders',
                        'actions' => [
                                [
                                        'action' => 'RENAME_COLUMN',
                                        'from'   => 'old_name',
                                        'to'     => 'new_name',
                                ],
                                [
                                        'action'   => 'CHANGE_COLUMN',
                                        'from'     => 'amount_old',
                                        'to'       => 'amount',
                                        'type'     => 'DECIMAL(10,2)',
                                        'nullable' => true,      // explizit true -> kein NOT NULL
                                        // kein 'default' gesetzt -> keine DEFAULT-Klausel (entspricht Implementierung mit isset)
                                ],
                                [
                                        'action' => 'DROP_COLUMN',
                                        'name'   => 'legacy_flag',
                                ],
                        ],
                ];

                $stmt = $compiler->compile($query);
                $sql  = $this->extractSql($stmt);

                // Exakte Reihenfolge und Formatierung prüfen
                $expected = "ALTER TABLE `orders`\n"
                        . "  RENAME COLUMN `old_name` TO `new_name`,\n"
                        . "  CHANGE COLUMN `amount_old` `amount` DECIMAL(10,2),\n"
                        . "  DROP COLUMN `legacy_flag`";

                $this->assertSame($expected, $sql);
        }

        public function testCompileQuotesIdentifiersAndLiteralsCorrectly(): void {
                $compiler = new AlterQueryCompiler();

                $query = [
                        'table' => 'my`table', // Backtick im Tabellennamen
                        'actions' => [
                                [
                                        'action'   => 'ADD_COLUMN',
                                        'name'     => "weird`col",
                                        'type'     => 'VARCHAR(100)',
                                        'nullable' => false,
                                        'default'  => "O'Brien", // Apostroph im String
                                ],
                                [
                                        'action'   => 'ADD_COLUMN',
                                        'name'     => 'flag',
                                        'type'     => 'BOOLEAN',
                                        'default'  => true,
                                ],
                                [
                                        'action'   => 'ADD_COLUMN',
                                        'name'     => 'is_deleted',
                                        'type'     => 'BOOLEAN',
                                        'default'  => false,
                                ],
                        ],
                ];

                $stmt = $compiler->compile($query);
                $sql  = $this->extractSql($stmt);

                // Tabellename: my`table -> `my``table`
                $this->assertStringContainsString('ALTER TABLE `my``table`', $sql);

                // Spaltenname: weird`col -> `weird``col`
                $this->assertStringContainsString("ADD COLUMN `weird``col` VARCHAR(100) NOT NULL DEFAULT 'O''Brien'", $sql);

                // Bool-Literale TRUE/FALSE
                $this->assertStringContainsString("ADD COLUMN `flag` BOOLEAN DEFAULT TRUE", $sql);
                $this->assertStringContainsString("ADD COLUMN `is_deleted` BOOLEAN DEFAULT FALSE", $sql);
        }

        public function testCompileIsCaseInsensitiveForActionNames(): void {
                $compiler = new AlterQueryCompiler();

                $query = [
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action'   => 'add_column',   // klein geschrieben
                                        'name'     => 'age',
                                        'type'     => 'INT',
                                        'nullable' => false,
                                        'default'  => 1,
                                ],
                        ],
                ];

                $stmt = $compiler->compile($query);
                $sql  = $this->extractSql($stmt);

                $expected = "ALTER TABLE `users`\n  ADD COLUMN `age` INT NOT NULL DEFAULT 1";
                $this->assertSame($expected, $sql);
        }

        public function testCompileThrowsOnUnsupportedAction(): void {
                $compiler = new AlterQueryCompiler();

                $query = [
                        'table' => 'users',
                        'actions' => [
                                [
                                        'action' => 'SOMETHING_WEIRD',
                                        'name'   => 'x',
                                ],
                        ],
                ];

                $this->expectException(\InvalidArgumentException::class);
                $this->expectExceptionMessage('Unsupported ALTER action: SOMETHING_WEIRD');

                $compiler->compile($query);
        }
}
