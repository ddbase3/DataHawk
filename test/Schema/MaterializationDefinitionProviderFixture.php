<?php declare(strict_types=1);

namespace DataHawk\Test\Schema;

use ResourceFoundation\Api\IMaterializationDefinitionProvider;

final class MaterializationDefinitionProviderFixture implements IMaterializationDefinitionProvider {

	public function __construct(private readonly string $scope, private readonly array $definitions = []) {}

	public static function getName(): string {
		return 'datahawkmaterializationdefinitionproviderfixture';
	}

	public function getScope(): string {
		return $this->scope;
	}

	public function getDefinitions(): array {
		return $this->definitions;
	}
}
