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

use Base3\Api\IAssetResolver;
use DataHawk\Api\IReportExporter;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;

class PieChartReportExporter implements IReportExporter {

	private ?QueryResult $result = null;

	public function __construct(
		private readonly IQueryService $reportqueryservice,
		private readonly IAssetResolver $assetResolver
	) {}

	public static function getName(): string {
		return 'piechartreportexporter';
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

		$col0 = $this->result->columns[0]['name'];
		$col1 = $this->result->columns[1]['name'];

		$uniqueid = 'dt' . uniqid();

		$html = '<div style="height:300px;"><canvas id="' . $uniqueid . '"></canvas></div>';
		$html .= '<script>';
		$html .= '(async () => {';
		$html .= 'await AssetLoader.loadScriptAsync("' . $this->assetResolver->resolve('plugin/ClientStack/assets/chart/chart.js') . '");';
		$html .= 'console.log("Chart.js loaded");';
		$html .= 'var result = ' . json_encode($this->result->rows) . ';';
		$html .= 'var labels = []; var data = [];';
		$html .= 'for (let i in result) { labels.push(result[i]["' . $col0 . '"]); data.push(result[i]["' . $col1 . '"]); }';
		$html .= 'var ctx = document.getElementById("' . $uniqueid . '");';
		$html .= 'new Chart(ctx, {';
		$html .= 'type: "doughnut", options: { responsive: true, maintainAspectRatio: false },';
		$html .= 'data: { labels: labels, datasets: [{ label: "' . $col1 . '", data: data, hoverOffset: 4 }] }';
		$html .= '});';
		$html .= '})();';
		$html .= '</script>';

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

