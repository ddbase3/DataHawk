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

use Base3\Api\IServiceRegistry;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Api\IScopedQuerySchemaProvider;
use ResourceFoundation\Dto\TableMetadata;

class CompositeQuerySchemaProvider implements IScopedQuerySchemaProvider {

	public function __construct(
		private readonly IServiceRegistry $registry
	) {}

	/**
	 * @return TableMetadata[]
	 */
	public function getSchema(): array {
		$schema = [];

		foreach($this->getScopes() as $scope) {
			$schema = array_merge($schema, $this->getSchemaForScope($scope));
		}

		return $schema;
	}

	public function getTable(string $tableName): ?TableMetadata {
		[$scope, $localTableName] = $this->splitQualifiedTableName($tableName);
		if($scope !== null) {
			return $this->getTableForScope($scope, $localTableName);
		}

		$defaultTable = $this->registry->getDefault()->getTable($tableName);
		if($defaultTable !== null) {
			return $defaultTable;
		}

		$matches = [];
		foreach($this->getScopes() as $scopeName) {
			$table = $this->getTableForScope($scopeName, $tableName);
			if($table !== null) {
				$matches[$scopeName] = $table;
			}
		}

		if(count($matches) > 1) {
			throw new \RuntimeException(
				'Query table identifier is ambiguous: ' . $tableName . ' (' . implode(', ', array_keys($matches)) . ')'
			);
		}

		return $matches === [] ? null : reset($matches);
	}

	public function getScopes(): array {
		$scopes = $this->registry->listNames();
		sort($scopes);
		return array_values($scopes);
	}

	public function getDefaultScope(): string {
		foreach($this->getScopes() as $scope) {
			if($this->registry->get($scope) === $this->registry->getDefault()) {
				return $scope;
			}
		}

		return '';
	}

	/**
	 * @return TableMetadata[]
	 */
	public function getSchemaForScope(string $scope): array {
		$scope = trim($scope);
		if($scope === '' || !$this->registry->has($scope)) {
			return [];
		}

		return $this->registry->get($scope)->getSchema();
	}

	public function getTableForScope(string $scope, string $tableName): ?TableMetadata {
		$scope = trim($scope);
		$tableName = trim($tableName);
		if($scope === '' || $tableName === '' || !$this->registry->has($scope)) {
			return null;
		}

		return $this->registry->get($scope)->getTable($tableName);
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
}
