<?php declare(strict_types=1);

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

