<?php declare(strict_types=1);

namespace DataHawk\Content;

use Base3\Api\IOutput;
use DataHawk\Api\IDataQueryService;

class TestOutput implements IOutput {

    public function __construct(
        private readonly IDataQueryService $dataqueryservice
    ) {}

    // Implementation of IBase

    public static function getName(): string {
        return 'testoutput';
    }

    // Implementation of IOutput

    public function getOutput($out = "html") {

        $out = '';

        // Tabellenübersicht
        $tables = $this->dataqueryservice->listTables();
        $out .= "<h2>📦 Available Tables</h2><ul>";
        foreach ($tables as $table) {
            $out .= "<li><strong>{$table->name}</strong> – {$table->description}</li>";
        }
        $out .= "</ul>";

        // Testabfrage über executeQuery()
        $out .= "<h2>🧪 Query: packagist_package (name, downloads)</h2>";

        try {

////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$query = [
    "select" => [
        [
            "element" => [
                "type" => "fld",
                "table" => "packagist_handle",
                "field" => "name",
                "tablealias" => "vendor1"
            ],
            "alias" => "first_vendor"
        ],
        [
            "element" => [
                "type" => "fld",
                "table" => "packagist_handle",
                "field" => "name",
                "tablealias" => "vendor2"
            ],
            "alias" => "second_vendor"
        ]
    ],
    "from" => "packagist_package",
    "limit" => 5
];

////////////////////////////////////////////////////////////////////////////////////////////////////////////////

            $result = $this->dataqueryservice->executeQuery($query);

	    $out .= "<h3>🧠 Generated SQL</h3><pre>" . htmlspecialchars($result->debugSql ?? '[n/a]') . "</pre>";

            // Ausgabe als Tabelle
            $out .= "<table border='1' cellpadding='4' cellspacing='0'><thead><tr>";
            foreach ($result->columns as $col) {
                $out .= "<th>{$col['name']}</th>";
            }
            $out .= "</tr></thead><tbody>";
            foreach ($result->rows as $row) {
                $out .= "<tr>";
                foreach ($row as $cell) {
                    $out .= "<td>" . htmlspecialchars((string)$cell) . "</td>";
                }
                $out .= "</tr>";
            }
            $out .= "</tbody></table>";

        } catch (\Throwable $e) {
            $out .= "<p style='color:red;'>❌ Query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        }

	return $out;
    }

    public function getHelp() {
        return 'Help of TestOutput' . "\n";
    }
}

