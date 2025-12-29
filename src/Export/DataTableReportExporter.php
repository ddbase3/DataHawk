<?php declare(strict_types=1);

namespace DataHawk\Export;

use Base3\Api\IAssetResolver;
use DataHawk\Api\IReportExporter;
use ResourceFoundation\Api\IQueryService;
use ResourceFoundation\Dto\QueryResult;

class DataTableReportExporter implements IReportExporter {

	private ?QueryResult $result = null;

	public function __construct(
		private readonly IQueryService $reportqueryservice,
		private readonly IAssetResolver $assetResolver
	) {}

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
		$html .= 'await AssetLoader.loadScriptAsync("' . $this->assetResolver->resolve('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.js') . '");';
		$html .= 'await AssetLoader.loadCssAsync("' . $this->assetResolver->resolve('plugin/ClientStack/assets/jquerydatatable/jquery.datatable.min.css') . '");';
		$html .= 'console.log("JqueryDataTable loaded");';
		$html .= 'var data = ' . json_encode($this->result->rows) . ';';
		$html .= 'var cols = ' . json_encode($cols) . ';';
		$html .= '$("#' . $uniqueid . '").jqueryDataTable({';
		$html .= 'columns: cols, data: data,';
		$html .= 'layoutTargets: {';
		$html .= '".header-left": ["columnSelector"],';
		$html .= '".header-right": ["compactPager"],';
		$html .= '".footer-left": ["resetButton"],';
		$html .= '".footer-center": ["info"],';
		$html .= '".footer-right": ["pageSizeSelector"]';
		$html .= '},';
		$html .= 'pageSize: 10, pageSizeOptions: [5, 10, 20]';
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

