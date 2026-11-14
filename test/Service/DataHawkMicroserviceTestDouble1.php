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

namespace Base3\DataHawk\Test\Service;

use PHPUnit\Framework\TestCase;
use Base3\DataHawk\Service\DataHawkMicroservice;
use Base3\CoreFoundation\Microservice\Api\IMicroserviceConnector;
use Base3\ResourceFoundation\Api\IQueryService;
use Base3\ResourceFoundation\Dto\QueryResult;
use Base3\ResourceFoundation\Dto\TableMetadata;

final class DataHawkMicroserviceTestDouble1 implements IQueryService, IMicroserviceConnector  {

			/** @var list<mixed> */
			public array $calls = [];

			public function getMicroserviceUrl() {
				return 'http://fake';
			}

			public function listTables(): array {
				$this->calls[] = 'listTables';
				return ['tables'];
			}

			public function getTable(string $tableName): ?TableMetadata {
				$this->calls[] = ['getTable', $tableName];
				return new TableMetadata(
					name: $tableName,
					label: null,
					description: null,
					domain: 'd',
					category: 'c',
					tags: [],
					fields: [],
					joins: [],
					defaultFilters: [],
					sensitive: false
				);
			}

			public function executeQuery(array $queryJson): QueryResult {
				$this->calls[] = ['executeQuery', $queryJson];
				return new QueryResult(columns: [], rows: [], debugSql: null, sensitive: false);
			}

			public function listDomains(): array {
				$this->calls[] = 'listDomains';
				return ['domains'];
			}

			public function listCategories(): array {
				$this->calls[] = 'listCategories';
				return ['categories'];
			}

			public function listTags(): array {
				$this->calls[] = 'listTags';
				return ['tags'];
			}
		}
