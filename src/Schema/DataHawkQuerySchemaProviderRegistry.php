<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of DataHawk for BASE3 Framework.
 *
 * DataHawk extends the BASE3 framework with a schema-driven query
 * engine for reporting and data access. Queries are defined as
 * structured JSON arrays, compiled into SQL, and executed through
 * the BASE3 IDatabase abstraction.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/datahawk
 * https://github.com/ddbase3/DataHawk
 **********************************************************************/

namespace DataHawk\Schema;

use Base3\Api\IClassMap;
use Base3\Core\DefaultServiceRegistry;
use Base3\Database\Api\IDatabase;
use DataHawk\Materialization\DefinitionMaterializationSchemaProvider;
use ResourceFoundation\Api\IMaterializationDefinitionProvider;
use ResourceFoundation\Api\IQuerySchemaDefinitionProvider;
use ResourceFoundation\Api\IQuerySchemaProvider;

class DataHawkQuerySchemaProviderRegistry extends DefaultServiceRegistry {

	private const DEFAULT_SCOPE = 'default';

	public function __construct(
		IClassMap $classMap,
		IDatabase $database
	) {
		$factories = [];

		foreach($classMap->getInstancesByInterface(IQuerySchemaDefinitionProvider::class) as $provider) {
			if(!$provider instanceof IQuerySchemaDefinitionProvider) {
				continue;
			}

			$scope = $this->normalizeScope($provider->getScope(), $provider::getName());
			$this->assertScopeAvailable($scope, $factories);
			$factories[$scope] = fn() => new DefinitionQuerySchemaProvider($provider);
		}

		foreach($classMap->getInstancesByInterface(IMaterializationDefinitionProvider::class) as $provider) {
			if(!$provider instanceof IMaterializationDefinitionProvider) {
				continue;
			}

			$scope = $this->normalizeScope($provider->getScope(), $provider::getName());
			$this->assertScopeAvailable($scope, $factories);
			$factories[$scope] = fn() => new DefinitionMaterializationSchemaProvider($provider, $database);
		}

		if(!array_key_exists(self::DEFAULT_SCOPE, $factories)) {
			throw new \RuntimeException('DataHawk query schema scope "default" is not available.');
		}

		parent::__construct(IQuerySchemaProvider::class, self::DEFAULT_SCOPE, $factories);
	}

	public function get(string $name): IQuerySchemaProvider {
		$provider = parent::get($name);
		if(!$provider instanceof IQuerySchemaProvider) {
			throw new \RuntimeException("Schema provider '{$name}' must implement " . IQuerySchemaProvider::class);
		}

		return $provider;
	}

	public function getDefault(): IQuerySchemaProvider {
		$provider = parent::getDefault();
		if(!$provider instanceof IQuerySchemaProvider) {
			throw new \RuntimeException('Default schema provider must implement ' . IQuerySchemaProvider::class);
		}

		return $provider;
	}

	private function normalizeScope(string $scope, string $providerName): string {
		$scope = trim($scope);
		if($scope === '') {
			throw new \RuntimeException('Reporting definition provider has an empty scope: ' . $providerName);
		}

		if(preg_match('/^[a-z0-9_-]+$/', $scope) !== 1) {
			throw new \RuntimeException('Invalid reporting definition scope "' . $scope . '" from ' . $providerName);
		}

		return $scope;
	}

	private function assertScopeAvailable(string $scope, array $factories): void {
		if(array_key_exists($scope, $factories)) {
			throw new \RuntimeException('Reporting query schema scope is not unique: ' . $scope);
		}
	}
}
