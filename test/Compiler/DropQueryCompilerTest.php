<?php declare(strict_types=1);

namespace DataHawk\Test\Compiler;

use DataHawk\Compiler\DropQueryCompiler;
use DataHawk\Compiler\ElementCompiler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Dto\QueryStatement;
use ResourceFoundation\Exception\QueryValidationException;

class DropQueryCompilerTest extends TestCase {

	/**
	 * Creates a DropQueryCompiler and injects a stubbed ElementCompiler.
	 *
	 * @return array{0:DropQueryCompiler,1:ElementCompiler&MockObject}
	 */
	private function makeCompilerWithElementStub(): array {
		$schemaProvider = $this->createStub(IQuerySchemaProvider::class);

		$compiler = new DropQueryCompiler($schemaProvider);

		/** @var ElementCompiler&MockObject $elementStub */
		$elementStub = $this->createStub(ElementCompiler::class);

		$ref  = new \ReflectionClass($compiler);
		$prop = $ref->getProperty('elementCompiler');
		$prop->setAccessible(true);
		$prop->setValue($compiler, $elementStub);

		return [$compiler, $elementStub];
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

	public function testCompileValidDropTable(): void {
		[$compiler, $elementStub] = $this->makeCompilerWithElementStub();

		$elementStub->method('quoteIdentifier')
			->with('users')
			->willReturn('`users`');

		$query = [
			'table' => 'users',
		];

		$stmt = $compiler->compile($query);
		$sql  = $this->extractSql($stmt);

		$this->assertSame('DROP TABLE `users`', $sql);
	}

	public function testCompileUsesElementCompilerForIdentifierEscaping(): void {
		[$compiler, $elementStub] = $this->makeCompilerWithElementStub();

		$elementStub->method('quoteIdentifier')
			->with('my`table')
			->willReturn('`my``table`');

		$query = [
			'table' => 'my`table',
		];

		$stmt = $compiler->compile($query);
		$sql  = $this->extractSql($stmt);

		$this->assertSame('DROP TABLE `my``table`', $sql);
	}

	public function testMissingTableThrowsException(): void {
		[$compiler, $_] = $this->makeCompilerWithElementStub();

		$query = [
			// no 'table'
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("DROP query must define a valid 'table'.");

		$compiler->compile($query);
	}

	public function testEmptyTableThrowsException(): void {
		[$compiler, $_] = $this->makeCompilerWithElementStub();

		$query = [
			'table' => '',
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("DROP query must define a valid 'table'.");

		$compiler->compile($query);
	}

	public function testNonStringTableThrowsException(): void {
		[$compiler, $_] = $this->makeCompilerWithElementStub();

		$query = [
			'table' => ['not-a-string'],
		];

		$this->expectException(QueryValidationException::class);
		$this->expectExceptionMessage("DROP query must define a valid 'table'.");

		$compiler->compile($query);
	}
}
