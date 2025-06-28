<?php declare(strict_types=1);

namespace DataHawk\Export;

use DataHawk\Api\IReportExporter;
use DataHawk\Api\IReportQueryService;
use DataHawk\Dto\QueryResult;

class HtmlPageReportExporter implements IReportExporter {

	private ?QueryResult $result = null;

	public function __construct(private readonly IReportQueryService $reportqueryservice) {}

	// Required by IBase
	public static function getName(): string {
		return 'htmlpagereportexporter';
	}

	// IReportExporter

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

		$html = '<!DOCTYPE html><html><head>';
		$html .= '<meta charset="utf-8">';
		$html .= '<title>Report</title>';
		$html .= '<style>
			table { border-collapse: collapse; width: 100%; }
			th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
			th { background-color: #f4f4f4; }
		</style>';
		$html .= '</head><body>';
		$html .= '<table>';
		$html .= '<thead><tr>';

		foreach ($this->result->columns as $col) {
			$html .= '<th>' . htmlspecialchars((string)$col['name']) . '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ($this->result->rows as $row) {
			$html .= '<tr>';
			$values = is_array($row) && array_keys($row) !== range(0, count($row) - 1)
				? array_values($row)
				: $row;

			foreach ($values as $value) {
				$html .= '<td>' . htmlspecialchars((string)$value) . '</td>';
			}

			$html .= '</tr>';
		}

		$html .= '</tbody></table>';
		$html .= '</body></html>';

		return $html;
	}

	public function toFile(string $filePath): self {
		$html = $this->toString();

		if (file_put_contents($filePath, $html) === false) {
			throw new \RuntimeException("Failed to write HTML report: {$filePath}");
		}

		return $this;
	}

	public function getMimeType(): string {
		return 'text/html';
	}

	public function getFileExtension(): string {
		return 'html';
	}
}

