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

namespace DataHawk\Api;

/**
 * Factory interface for creating report exporter instances.
 */
interface IReportExporterFactory {

	/**
	 * Creates a report exporter by name (e.g., "csvreportexporter", "htmlpageexporter").
	 *
	 * @param string $type
	 * @return IReportExporter
	 */
	public function createExporter(string $name): IReportExporter;
}

