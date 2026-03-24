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

class JsonReportExporter implements IReportExporter {

	private ?QueryResult $result = null;

	public function __construct(private readonly IQueryService $reportqueryservice) {}

	public static function getName(): string {
		return 'jsonreportexporter';
	}

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

		$data = [
			'columns' => $this->result->columns,
			'rows'    => $this->result->rows,
		];

		if ($this->result->debugSql !== null) {
			$data['debugSql'] = $this->result->debugSql;
		}

		return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	}

	public function toFile(string $filePath): self {
		$json = $this->toString();

		if (file_put_contents($filePath, $json) === false) {
			throw new \RuntimeException("Failed to write JSON file: {$filePath}");
		}

		return $this;
	}

	public function toSql(): string {
		return $this->result->debugSql ?? '';
	}

	public function getMimeType(): string {
		return 'application/json';
	}

	public function getFileExtension(): string {
		return 'json';
	}
}

