<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of DataHawk for BASE3 Framework.
 *
 * DataHawk extends BASE3 with schema-driven reporting and data queries.
 * Structured query definitions compile to backend-specific statements.
 * Execution uses the shared BASE3 database and resource contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/datahawk
 * https://github.com/ddbase3/DataHawk
 **********************************************************************/

namespace Base3\DataHawk\Test\Schema;

use Base3\ResourceFoundation\Api\IQuerySchemaDefinitionProvider;

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
