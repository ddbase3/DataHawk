<?php declare(strict_types=1);

namespace DataHawk\Export;

use DataHawk\Api\IReportExporter;
use DataHawk\Api\IReportQueryService;
use DataHawk\Dto\QueryResult;

class DataTableReportExporter implements IReportExporter {

	private ?QueryResult $result = null;

	public function __construct(private readonly IReportQueryService $reportqueryservice) {}

	public static function getName(): string {
		return 'datatablereportexporter';
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

		$cols = [];
		foreach ($this->result->columns as $c) $cols[] = ['key' => $c['name'], 'label' => $c['name']];

		$uniqueid = 'dt' . uniqid();

		$html = '<div id="' . $uniqueid . '"></div>';
		$html .= '<script>';
		$html .= '(async () => {';
		$html .= 'await AssetLoader.loadScriptAsync("plugin/ClientStack/assets/jquerydatatable/jquerydatatable.js");';
		$html .= 'await AssetLoader.loadCssAsync("plugin/ClientStack/assets/jquerydatatable/jquerydatatable.css");';
		$html .= 'console.log("JqueryDataTable loaded");';
		$html .= 'var data = ' . json_encode($this->result->rows) . ';';
		$html .= 'var cols = ' . json_encode($cols) . ';';
		$html .= '$("#' . $uniqueid . '").jqueryDataTable({';
		$html .= 'columns: cols, data: data,';
		$html .= 'layoutTargets: {';
		$html .= 'pager: "header.right",';
		$html .= 'pageSize: "footer.right",';
		$html .= 'info: "footer.center",';
		$html .= 'resetButton: "footer.left",';
		$html .= 'columnSelector: "header.left"';
		$html .= '},';
		$html .= 'pageSize: 5, pageSizeOptions: [5, 10, 20]';
		$html .= '});';
		$html .= '})();';
		$html .= '</script>';
		$html .= '<style>';
		$html .= '#' . $uniqueid . ' * { line-height:1.2; }';
		$html .= '#' . $uniqueid . ' td, #' . $uniqueid . ' th { padding:5px; }';
		$html .= '#' . $uniqueid . ' td { background:#fff; }';
		$html .= '</style>';

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

