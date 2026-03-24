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

namespace DataHawk\Export;

use DataHawk\Api\IReportExporter;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;

class CsvReportExporter implements IReportExporter {

	private ?QueryResult $result = null;

	public function __construct(private readonly IQueryService $reportqueryservice) {}

	// Implementation of IBase

	public static function getName(): string {
		return 'csvreportexporter';
	}

	// Implementation of IReportExporter

	public function setExportQuery(array $queryJson): self {
		$this->result = $this->reportqueryservice->executeQuery($queryJson);
		return $this;
	}

	public function setResult(QueryResult $result): self {
		$this->result = $result;
		return $this;
	}

	public function getResult(): ?QueryResult {
		return $this->result;
	}

	public function toString(): string {
		if (!$this->result) {
			throw new \RuntimeException('No query result set for export.');
		}

		$stream = fopen('php://temp', 'r+');
		if ($stream === false) {
			throw new \RuntimeException('Failed to open temporary stream.');
		}

		// Write header
		$headers = array_map(fn($col) => $col['name'], $this->result->columns);
		fputcsv($stream, $headers);

		// Write rows
		foreach ($this->result->rows as $row) {
			// Supports both associative and indexed arrays
			if (is_array($row) && array_keys($row) !== range(0, count($row) - 1)) {
				$row = array_values($row);
			}
			fputcsv($stream, $row);
		}

		rewind($stream);
		$csv = stream_get_contents($stream);
		fclose($stream);

		if ($csv === false) {
			throw new \RuntimeException('Failed to read CSV stream.');
		}

		return $csv;
	}

	public function toFile(string $filePath): self {
		$csv = $this->toString();

		if (file_put_contents($filePath, $csv) === false) {
			throw new \RuntimeException("Failed to write CSV file: {$filePath}");
		}

		return $this;
	}

	public function toSql(): string {
		return $this->result->debugSql ?? '';
	}

	public function getMimeType(): string {
		return 'text/csv';
	}

	public function getFileExtension(): string {
		return 'csv';
	}
}

