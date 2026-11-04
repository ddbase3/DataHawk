<?php declare(strict_types=1);

namespace DataHawk\Test\Schema;

use ResourceFoundation\Api\IQuerySchemaDefinitionProvider;

final class QuerySchemaDefinitionProviderFixture implements IQuerySchemaDefinitionProvider {

	public function __construct(private readonly string $scope, private readonly array $definitions = []) {}

	public static function getName(): string {
		return 'datahawkqueryschemadefinitionproviderfixture';
	}

	public function getScope(): string {
		return $this->scope;
	}

	public function getDefinitions(): array {
		return $this->definitions;
	}
}
