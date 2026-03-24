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

namespace DataHawk\Service;

use Base3\Api\IClassMap;
use DataHawk\Api\IReportExporter;
use DataHawk\Api\IReportExporterFactory;

class ReportExporterFactory implements IReportExporterFactory {

	public function __construct(private readonly IClassMap $classmap) {}

	public function createExporter(string $name): IReportExporter {
		$exporter = $this->classmap->getInstanceByInterfaceName(IReportExporter::class, $name);

		if (!$exporter instanceof IReportExporter) {
			throw new \RuntimeException("Report exporter '$name' could not be instantiated or is invalid");
		}

		return $exporter;
	}
}

