<?php declare(strict_types=1);

namespace DataHawk\Export;

use DataHawk\Api\IReportExporter;
use DataHawk\Api\IReportQueryService;
use DataHawk\Dto\QueryResult;

class HtmlTableReportExporter implements IReportExporter {

	private ?QueryResult $result = null;

	public function __construct(private readonly IReportQueryService $reportqueryservice) {}

	public static function getName(): string {
		return 'htmltablereportexporter';
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

		$html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 13px;">';
		$html .= '<thead><tr>';

		foreach ($this->result->columns as $col) {
			$html .= '<th style="background-color: #f0f0f0; font-weight: bold; border: 1px solid #ccc;">' . htmlspecialchars((string)$col['name']) . '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ($this->result->rows as $row) {
			$html .= '<tr>';

			$values = is_array($row) && array_keys($row) !== range(0, count($row) - 1)
				? array_values($row)
				: $row;

			foreach ($values as $value) {
				$html .= '<td style="border: 1px solid #ccc;">' . htmlspecialchars((string)$value) . '</td>';
			}

			$html .= '</tr>';
		}

		$html .= '</tbody></table>';

		return $html;
	}

	public function toFile(string $filePath): self {
		$html = $this->toString();

		if (file_put_contents($filePath, $html) === false) {
			throw new \RuntimeException("Failed to write HTML table export: {$filePath}");
		}

		return $this;
	}

	public function toSql(): string {
		return $this->result->debugSql ?? '';
	}

	public function getMimeType(): string {
		return 'text/html';
	}

	public function getFileExtension(): string {
		return 'html';
	}
}

