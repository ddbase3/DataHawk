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

use Base3\Configuration\Api\IConfiguration;

class DefaultReportSchemaProvider extends FileQuerySchemaProvider {

	public function __construct(IConfiguration $configuration) {
		$directories = $configuration->get('directories');
		$dataDir = is_array($directories) && isset($directories['data'])
			? rtrim((string) $directories['data'], DIRECTORY_SEPARATOR . '/\\')
			: '';

		parent::__construct(
			$dataDir !== ''
				? $dataDir . DIRECTORY_SEPARATOR . 'datahawk'
				: ''
		);
	}
}
