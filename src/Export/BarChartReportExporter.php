<?php declare(strict_types=1);

namespace DataHawk\Export;

use DataHawk\Api\IReportExporter;
use DataHawk\Api\IReportQueryService;
use DataHawk\Dto\QueryResult;

class BarChartReportExporter implements IReportExporter {

    private ?QueryResult $result = null;

    public function __construct(private readonly IReportQueryService $reportqueryservice) {}

    public static function getName(): string {
        return 'barchartreportexporter';
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

        $col0 = $this->result->columns[0]['name']; // X-Achse
        $col1 = $this->result->columns[1]['name']; // Y-Achse

        $uniqueid = 'bar' . uniqid();

        $html = '<div style="height:300px;"><canvas id="' . $uniqueid . '"></canvas></div>';
        $html .= '<script>';
        $html .= 'var result = ' . json_encode($this->result->rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ';';
        $html .= 'var labels = []; var data = [];';
        $html .= 'for (let i in result) { labels.push(result[i]["' . $col0 . '"]); data.push(result[i]["' . $col1 . '"]); }';
        $html .= 'var ctx = document.getElementById("' . $uniqueid . '").getContext("2d");';
        $html .= 'new Chart(ctx, {';
        $html .= 'type: "bar",';
        $html .= 'data: { labels: labels, datasets: [{ label: "' . $col1 . '", data: data }] },';
        $html .= 'options: { responsive: true, maintainAspectRatio: false }';
        $html .= '});';
        $html .= '</script>';

        return $html;
    }

    public function toFile(string $filePath): self {
        $html = $this->toString();

        if (file_put_contents($filePath, $html) === false) {
            throw new \RuntimeException("Failed to write HTML bar chart export: {$filePath}");
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

