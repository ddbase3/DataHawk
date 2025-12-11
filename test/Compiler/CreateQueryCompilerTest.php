<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\CreateQueryCompiler;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

class CreateQueryCompilerTest extends TestCase {

        private function makeCompiler(): CreateQueryCompiler {
                $schemaProvider = $this->createStub(IQuerySchemaProvider::class);
                return new CreateQueryCompiler($schemaProvider);
        }

        private function extractSql(QueryStatement $stmt): string {
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

        public function testCompileSimpleCreateWithPrimaryKeyAndAutoIncrement(): void {
                $compiler = $this->makeCompiler();

                $query = [
                        'table' => 'users',
                        'columns' => [
                                [
                                        'name'          => 'id',
                                        'type'          => 'INT',
                                        'nullable'      => false,
                                        'auto_increment'=> true,
                                        'primary_key'   => true,
                                ],
                        ],
                ];

                $stmt = $compiler->compile($query);
                $sql  = $this->extractSql($stmt);

                $expected = "CREATE TABLE `users` (`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY)";
                $this->assertSame($expected, $sql);
        }

        public function testCompileMultipleColumnsWithDefaultsAndNullability(): void {
                $compiler = $this->makeCompiler();

                $query = [
                        'table' => 'accounts',
                        'columns' => [
                                [
                                        'name'     => 'username',
                                        'type'     => 'VARCHAR(50)',
                                        // nullable nicht explizit -> true -> kein NOT NULL
                                        'default'  => 'anon',
                                ],
                                [
                                        'name'     => 'created_at',
                                        'type'     => 'DATETIME',
                                        'nullable' => false,
                                        'default'  => 'current_timestamp', // case-insensitive
                                ],
                                [
                                        'name'     => 'score',
                                        'type'     => 'INT',
                                        'nullable' => true,
                                        'default'  => 0, // numerisch -> ohne Quotes
                                ],
                        ],
                ];

                $stmt = $compiler->compile($query);
                $sql  = $this->extractSql($stmt);

                // Grundstruktur
                $this->assertStringStartsWith('CREATE TABLE `accounts` (', $sql);
                $this->assertStringEndsWith(')', $sql);

                // username-Spalte: DEFAULT 'anon' (gequotet, kein NOT NULL)
                $this->assertStringContainsString("`username` VARCHAR(50) DEFAULT 'anon'", $sql);

                // created_at: NOT NULL, DEFAULT CURRENT_TIMESTAMP (ohne Quotes)
                $this->assertStringContainsString("`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP", $sql);

                // score: DEFAULT 0 (numerisch, ohne Quotes, kein NOT NULL)
                $this->assertStringContainsString("`score` INT DEFAULT 0", $sql);
        }

        public function testCompileEscapesStringDefaultLiterals(): void {
            $compiler = $this->makeCompiler();

            $query = [
                    'table' => 'posts',
                    'columns' => [
                            [
                                    'name'     => 'title',
                                    'type'     => 'VARCHAR(255)',
                                    'nullable' => false,
                                    'default'  => "O'Reilly", // Apostroph im String
                            ],
                    ],
            ];

            $stmt = $compiler->compile($query);
            $sql  = $this->extractSql($stmt);

            // Apostroph muss zu O''Reilly werden
            $expected = "CREATE TABLE `posts` (`title` VARCHAR(255) NOT NULL DEFAULT 'O''Reilly')";
            $this->assertSame($expected, $sql);
        }

        public function testCompileQuotesIdentifiersCorrectly(): void {
                $compiler = $this->makeCompiler();

                $query = [
                        'table' => 'my`table',
                        'columns' => [
                                [
                                        'name' => 'col`name',
                                        'type' => 'INT',
                                ],
                        ],
                ];

                $stmt = $compiler->compile($query);
                $sql  = $this->extractSql($stmt);

                // Angenommen: ElementCompiler::quoteIdentifier nutzt Backticks und escapt Backticks mit doppeltem `
                $this->assertStringContainsString('CREATE TABLE `my``table`', $sql);
                $this->assertStringContainsString('`col``name` INT', $sql);
        }

        public function testCompileWithNullabilityTrueOrMissingDoesNotAddNotNull(): void {
                $compiler = $this->makeCompiler();

                $query = [
                        'table' => 't',
                        'columns' => [
                                [
                                        'name'     => 'a',
                                        'type'     => 'INT',
                                        'nullable' => true,
                                ],
                                [
                                        'name' => 'b',
                                        'type' => 'INT',
                                        // nullable fehlt -> default true
                                ],
                        ],
                ];

                $stmt = $compiler->compile($query);
                $sql  = $this->extractSql($stmt);

                // Weder a noch b sollten "NOT NULL" enthalten
                $this->assertStringContainsString('`a` INT', $sql);
                $this->assertStringContainsString('`b` INT', $sql);
                $this->assertStringNotContainsString('`a` INT NOT NULL', $sql);
                $this->assertStringNotContainsString('`b` INT NOT NULL', $sql);
        }

        public function testCompileWithNullableFalseAddsNotNull(): void {
                $compiler = $this->makeCompiler();

                $query = [
                        'table' => 't',
                        'columns' => [
                                [
                                        'name'     => 'a',
                                        'type'     => 'INT',
                                        'nullable' => false,
                                ],
                        ],
                ];

                $stmt = $compiler->compile($query);
                $sql  = $this->extractSql($stmt);

                $this->assertStringContainsString('`a` INT NOT NULL', $sql);
        }

        public function testInvalidTableNameEmptyOrWhitespaceThrows(): void {
                $compiler = $this->makeCompiler();

                $query = [
                        'table' => '   ', // nur Spaces
                        'columns' => [
                                [
                                        'name' => 'id',
                                        'type' => 'INT',
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage('CREATE query requires a valid table name.');

                $compiler->compile($query);
        }

        public function testInvalidTableNameNonStringThrows(): void {
                $compiler = $this->makeCompiler();

                $query = [
                        'table' => ['not-a-string'],
                        'columns' => [
                                [
                                        'name' => 'id',
                                        'type' => 'INT',
                                ],
                        ],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage('CREATE query requires a valid table name.');

                $compiler->compile($query);
        }
}
