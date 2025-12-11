<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\DropQueryValidator;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Exception\QueryValidationException;

class DropQueryValidatorTest extends TestCase {

        public function testValidDropQueryPasses(): void {
                $validator = new DropQueryValidator();

                $query = [
                        'type'  => 'drop',
                        'table' => 'users',
                ];

                // Erwartung: keine Exception
                $validator->validate($query);
                $this->expectNotToPerformAssertions();
        }

        public function testInvalidTypeThrowsException(): void {
                $validator = new DropQueryValidator();

                $query = [
                        'type'  => 'delete',
                        'table' => 'users',
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Unsupported query type: delete");

                $validator->validate($query);
        }

        public function testMissingTypeThrowsException(): void {
                $validator = new DropQueryValidator();

                $query = [
                        'table' => 'users',
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("Unsupported query type: [not defined]");

                $validator->validate($query);
        }

        public function testEmptyTableThrowsException(): void {
                $validator = new DropQueryValidator();

                $query = [
                        'type'  => 'drop',
                        'table' => '', // leer
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("DROP query must define a non-empty 'table' as string.");

                $validator->validate($query);
        }

        public function testNonStringTableThrowsException(): void {
                $validator = new DropQueryValidator();

                $query = [
                        'type'  => 'drop',
                        'table' => ['not-a-string'],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("DROP query must define a non-empty 'table' as string.");

                $validator->validate($query);
        }

        public function testDisallowedWhereKeyThrowsException(): void {
                $validator = new DropQueryValidator();

                $query = [
                        'type'  => 'drop',
                        'table' => 'users',
                        'where' => ['something'],
                ];

                $this->expectException(QueryValidationException::class);
                $this->expectExceptionMessage("DROP query must not contain 'where'.");

                $validator->validate($query);
        }

        public function testDisallowedOtherKeysThrowExceptionIndividually(): void {
                $validator = new DropQueryValidator();

                $keys = ['fields', 'limit', 'order_by', 'group_by', 'having'];

                foreach ($keys as $key) {
                        $query = [
                                'type'  => 'drop',
                                'table' => 'users',
                                $key    => 'dummy',
                        ];

                        try {
                                $validator->validate($query);
                                $this->fail("Expected exception for disallowed key '$key'.");
                        } catch (QueryValidationException $e) {
                                $this->assertSame("DROP query must not contain '$key'.", $e->getMessage());
                        }
                }
        }
}
