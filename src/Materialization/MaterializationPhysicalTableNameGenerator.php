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

use ResourceFoundation\Dto\MaterializationManifest;

class MaterializationPhysicalTableNameGenerator {

	public function __construct(
		private readonly string $defaultPrefix = 'base3_mat_'
	) {}

	public function getPhysicalPrefix(MaterializationManifest $manifest): string {
		if ($manifest->physicalPrefix !== '') {
			return $this->normalizePrefix($manifest->physicalPrefix);
		}

		$name = $manifest->logicalTable !== '' ? $manifest->logicalTable : $manifest->id;
		return $this->normalizePrefix($this->defaultPrefix . $name);
	}

	public function getGenerationTableName(MaterializationManifest $manifest, ?string $generation = null): string {
		$generation = $generation ?? date('YmdHis');
		$generation = $this->normalizeIdentifier($generation);

		return $this->getPhysicalPrefix($manifest) . '_' . $generation;
	}

	private function normalizePrefix(string $prefix): string {
		$prefix = $this->normalizeIdentifier($prefix);
		return rtrim($prefix, '_');
	}

	private function normalizeIdentifier(string $identifier): string {
		$identifier = strtolower(trim($identifier));
		$identifier = preg_replace('/[^a-z0-9_]+/', '_', $identifier) ?? '';
		$identifier = preg_replace('/_+/', '_', $identifier) ?? '';
		$identifier = trim($identifier, '_');

		if ($identifier === '') {
			throw new \RuntimeException('Materialization physical table identifier must not be empty.');
		}

		return $identifier;
	}
}
