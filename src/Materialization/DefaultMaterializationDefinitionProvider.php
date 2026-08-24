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

use ResourceFoundation\Api\IMaterializationDefinitionProvider;

final class DefaultMaterializationDefinitionProvider implements IMaterializationDefinitionProvider {

	public static function getName(): string {
		return 'datahawkdefaultmaterializationdefinitionprovider';
	}

	public function getScope(): string {
		return 'datahawk_materialized';
	}

	public function getDefinitions(): array {
		$directory = dirname(__DIR__, 2) . '/local/DataHawk/materialized';
		$files = glob(rtrim($directory, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . '*.json') ?: [];
		sort($files);

		$definitions = [];
		foreach($files as $file) {
			$name = pathinfo($file, PATHINFO_FILENAME);
			if($name === '') {
				continue;
			}

			$definitions[$name] = [
				'enabled' => true,
				'definition' => $this->loadDefinition($file)
			];
		}

		return $definitions;
	}

	private function loadDefinition(string $file): array {
		$json = file_get_contents($file);
		if($json === false) {
			throw new \RuntimeException('Unable to read DataHawk materialization definition: ' . $file);
		}

		$definition = json_decode($json, true);
		if(!is_array($definition) || json_last_error() !== JSON_ERROR_NONE) {
			throw new \RuntimeException('Invalid DataHawk materialization JSON in ' . $file . ': ' . json_last_error_msg());
		}

		return $definition;
	}
}
