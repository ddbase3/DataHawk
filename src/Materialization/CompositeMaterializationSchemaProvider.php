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

namespace DataHawk\Materialization;

use DataHawk\Schema\DataHawkQuerySchemaProviderRegistry;
use ResourceFoundation\Api\IMaterializationSchemaProvider;
use ResourceFoundation\Api\IScopedMaterializationManifestProvider;
use ResourceFoundation\Dto\MaterializationManifest;
use ResourceFoundation\Dto\TableMetadata;

final class CompositeMaterializationSchemaProvider implements IMaterializationSchemaProvider, IScopedMaterializationManifestProvider {

	public function __construct(
		private readonly DataHawkQuerySchemaProviderRegistry $registry
	) {}

	/**
	 * @return TableMetadata[]
	 */
	public function getSchema(): array {
		$schema = [];
		foreach($this->getProviders() as $provider) {
			$schema = array_merge($schema, $provider->getSchema());
		}

		return $schema;
	}

	public function getTable(string $tableName): ?TableMetadata {
		[$scope, $localTableName] = $this->splitQualifiedTableName($tableName);
		if($scope !== null) {
			$provider = $this->getProviders()[$scope] ?? null;
			return $provider?->getTable($localTableName);
		}

		$matches = [];
		foreach($this->getProviders() as $scopeName => $provider) {
			$table = $provider->getTable($localTableName);
			if($table !== null) {
				$matches[$scopeName] = $table;
			}
		}

		if(count($matches) > 1) {
			throw new \RuntimeException(
				'Materialized table identifier is ambiguous: ' . $localTableName . ' (' . implode(', ', array_keys($matches)) . ')'
			);
		}

		return $matches === [] ? null : reset($matches);
	}

	public function getManifest(string $id): ?MaterializationManifest {
		[$scope, $localId] = $this->splitQualifiedManifestId($id);
		if($scope !== null) {
			return $this->getManifestForScope($scope, $localId);
		}

		$matches = [];
		foreach($this->getProviders() as $scopeName => $provider) {
			$manifest = $provider->getManifest($localId);
			if($manifest !== null) {
				$matches[$scopeName] = $manifest;
			}
		}

		if(count($matches) > 1) {
			throw new \RuntimeException(
				'Materialization identifier is ambiguous: ' . $localId . ' (' . implode(', ', array_keys($matches)) . ')'
			);
		}

		return $matches === [] ? null : reset($matches);
	}

	/**
	 * @return MaterializationManifest[]
	 */
	public function getManifests(): array {
		$manifests = [];
		foreach($this->getProviders() as $provider) {
			foreach($provider->getManifests() as $manifest) {
				$manifests[] = $manifest;
			}
		}

		return $manifests;
	}

	public function getScopes(): array {
		$scopes = array_keys($this->getProviders());
		sort($scopes);
		return array_values($scopes);
	}

	public function getManifestForScope(string $scope, string $id): ?MaterializationManifest {
		$provider = $this->getProviders()[trim($scope)] ?? null;
		if($provider === null) {
			return null;
		}

		return $provider->getManifest($id);
	}

	/**
	 * @return MaterializationManifest[]
	 */
	public function getManifestsForScope(string $scope): array {
		$provider = $this->getProviders()[trim($scope)] ?? null;
		return $provider?->getManifests() ?? [];
	}

	/**
	 * @return array<string,IMaterializationSchemaProvider>
	 */
	private function getProviders(): array {
		$providers = [];
		foreach($this->registry->listNames() as $scope) {
			$provider = $this->registry->get($scope);
			if($provider instanceof IMaterializationSchemaProvider) {
				$providers[$scope] = $provider;
			}
		}

		return $providers;
	}

	/**
	 * @return array{0:?string,1:string}
	 */
	private function splitQualifiedTableName(string $tableName): array {
		foreach([':', '.'] as $separator) {
			if(!str_contains($tableName, $separator)) {
				continue;
			}

			[$scope, $localTableName] = explode($separator, $tableName, 2);
			if($scope !== '' && $localTableName !== '') {
				return [$scope, $localTableName];
			}
		}

		return [null, $tableName];
	}

	/**
	 * @return array{0:?string,1:string}
	 */
	private function splitQualifiedManifestId(string $id): array {
		if(!str_contains($id, ':')) {
			return [null, $id];
		}

		[$scope, $localId] = explode(':', $id, 2);
		if($scope === '' || $localId === '') {
			return [null, $id];
		}

		return [$scope, $localId];
	}
}
