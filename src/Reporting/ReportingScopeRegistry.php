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

namespace DataHawk\Reporting;

use Base3\Api\IClassMap;
use ResourceFoundation\Api\IReportingScopeDefinitionProvider;
use ResourceFoundation\Api\IReportingScopeRegistry;
use ResourceFoundation\Dto\ReportingScopeDefinition;

final class ReportingScopeRegistry implements IReportingScopeRegistry {

	/** @var array<string,ReportingScopeDefinition>|null */
	private ?array $scopes = null;

	public function __construct(
		private readonly IClassMap $classMap
	) {}

	public function getScopes(): array {
		return array_values($this->loadScopes());
	}

	public function get(string $id): ?ReportingScopeDefinition {
		$id = trim($id);
		if($id === '') {
			return null;
		}

		return $this->loadScopes()[$id] ?? null;
	}

	/**
	 * @return array<string,ReportingScopeDefinition>
	 */
	private function loadScopes(): array {
		if($this->scopes !== null) {
			return $this->scopes;
		}

		$scopes = [];
		$querySchemaOwners = [];
		$materializationOwners = [];
		$reportOwners = [];

		foreach($this->classMap->getInstancesByInterface(IReportingScopeDefinitionProvider::class) as $provider) {
			if(!$provider instanceof IReportingScopeDefinitionProvider) {
				continue;
			}

			$definition = $provider->getReportingScopeDefinition();
			$this->validateDefinition($definition, $provider::getName());

			if(isset($scopes[$definition->id])) {
				throw new \RuntimeException('Reporting scope id is not unique: ' . $definition->id);
			}

			$this->claimTechnicalScopes($definition->querySchemaScopes, $definition->id, 'query schema', $querySchemaOwners);
			$this->claimTechnicalScopes($definition->materializationScopes, $definition->id, 'materialization', $materializationOwners);
			$this->claimTechnicalScopes($definition->reportScopes, $definition->id, 'report', $reportOwners);

			$scopes[$definition->id] = $definition;
		}

		uasort($scopes, fn(ReportingScopeDefinition $a, ReportingScopeDefinition $b) => strnatcasecmp($a->label, $b->label));
		return $this->scopes = $scopes;
	}

	private function validateDefinition(ReportingScopeDefinition $definition, string $providerName): void {
		if(preg_match('/^[a-z0-9_-]+$/', $definition->id) !== 1) {
			throw new \RuntimeException('Invalid reporting scope id "' . $definition->id . '" from ' . $providerName);
		}

		if(trim($definition->label) === '') {
			throw new \RuntimeException('Reporting scope label must not be empty: ' . $providerName);
		}

		$this->validateTechnicalScopes($definition->querySchemaScopes, 'query schema', $providerName);
		$this->validateTechnicalScopes($definition->materializationScopes, 'materialization', $providerName);
		$this->validateTechnicalScopes($definition->reportScopes, 'report', $providerName);
	}

	/**
	 * @param string[] $scopes
	 */
	private function validateTechnicalScopes(array $scopes, string $type, string $providerName): void {
		foreach($scopes as $scope) {
			if(!is_string($scope) || preg_match('/^[a-z0-9_-]+$/', $scope) !== 1) {
				throw new \RuntimeException('Invalid ' . $type . ' scope in ' . $providerName . ': ' . (string)$scope);
			}
		}
	}

	/**
	 * @param string[] $scopes
	 * @param array<string,string> $owners
	 */
	private function claimTechnicalScopes(array $scopes, string $reportingScopeId, string $type, array &$owners): void {
		foreach($scopes as $scope) {
			if(isset($owners[$scope]) && $owners[$scope] !== $reportingScopeId) {
				throw new \RuntimeException(
					'Technical ' . $type . ' scope "' . $scope . '" is assigned to multiple reporting scopes: ' .
					$owners[$scope] . ', ' . $reportingScopeId
				);
			}

			$owners[$scope] = $reportingScopeId;
		}
	}
}
