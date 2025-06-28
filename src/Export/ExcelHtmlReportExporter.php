<?php declare(strict_types=1);

namespace DataHawk\Export;

use DataHawk\Api\IReportExporter;
use DataHawk\Api\IReportQueryService;
use DataHawk\Dto\QueryResult;

class ExcelHtmlReportExporter implements IReportExporter {

	private ?QueryResult $result = null;

	public function __construct(private readonly IReportQueryService $reportqueryservice) {}

	// Implementation of IBase
	
	public static function getName(): string {
		return 'excelhtmlreportexporter';
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

		$html = '<table border="1" cellpadding="5" cellspacing="0">';
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

		return <<<HTML
			<html xmlns:o="urn:schemas-microsoft-com:office:office"
			      xmlns:x="urn:schemas-microsoft-com:office:excel"
			      xmlns="http://www.w3.org/TR/REC-html40">
			<head>
				<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
				<meta name="ProgId" content="Excel.Sheet" />
				<meta name="Generator" content="BASE3 Framework DataHawk" />
			</head>
			<body>
				$html
			</body>
			</html>
		HTML;
	}

	public function toFile(string $filePath): self {
		$html = $this->toString();

		if (file_put_contents($filePath, $html) === false) {
			throw new \RuntimeException("Failed to write Excel HTML file: {$filePath}");
		}

		return $this;
	}

	public function getMimeType(): string {
		return 'application/vnd.ms-excel';
	}

	public function getFileExtension(): string {
		return 'xls';
	}
}

