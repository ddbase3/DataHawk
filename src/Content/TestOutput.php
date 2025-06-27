<?php declare(strict_types=1);

namespace DataHawk\Content;

use Base3\Api\IOutput;
use DataHawk\Api\IReportQueryService;

class TestOutput implements IOutput {

    public function __construct(
        private readonly IReportQueryService $dataqueryservice
    ) {}

    public static function getName(): string {
        return 'testoutput';
    }

    public function getOutput($out = "html") {
        $out = '';

        // Tabellenübersicht
        $tables = $this->dataqueryservice->listTables();
        $out .= "<h2>📦 Available Tables</h2><ul>";
        foreach ($tables as $table) {
            $out .= "<li><strong>{$table->name}</strong> – {$table->description}</li>";
        }
        $out .= "</ul>";

        // Testfälle definieren
        $testCases = [

[ "title" => "double join", "query" => [
    "select" => [
        [
            "element" => [
                "type" => "fld",
                "table" => "packagist_handle",
                "field" => "name",
		"tablealias" => "vendor1",
		"variant" => "optional"
            ],
            "alias" => "first_vendor"
        ],
        [
            "element" => [
                "type" => "fld",
                "table" => "packagist_handle",
                "field" => "name",
		"tablealias" => "vendor2",
		"variant" => "optional"
            ],
            "alias" => "second_vendor"
        ]
    ],
    "from" => "packagist_package",
    "limit" => 5
]],
[
    "title" => "IS NULL",
    "query" => [
        "select" => [
            [ "element" => [ "type" => "fld", "table" => "packagist_package", "field" => "name" ] ],
            [ "element" => [ "type" => "fld", "table" => "packagist_handle", "field" => "lastcall", "variant" => "optional" ] ]
        ],
        "from" => "packagist_package",
        "where" => [
            "type" => "op",
            "operator" => "IS NULL",
            "params" => [
                [ "type" => "fld", "table" => "packagist_package", "field" => "description" ]
            ]
        ],
        "limit" => 5
    ]
],
[
    "title" => "NOT IN (subquery)",
    "query" => [
        "select" => [
            [ "element" => [ "type" => "fld", "table" => "packagist_handle", "field" => "name" ] ]
        ],
        "from" => "packagist_handle",
        "where" => [
            "type" => "op",
            "operator" => "NOT IN",
            "params" => [
                [ "type" => "fld", "table" => "packagist_handle", "field" => "id" ],
                [
                    "type" => "subquery",
                    "query" => [
                        "select" => [
                            [ "element" => [ "type" => "fld", "table" => "packagist_package", "field" => "handle_id" ] ]
                        ],
                        "from" => "packagist_package"
                    ]
                ]
            ]
        ],
        "limit" => 5
    ]
],
[
    "title" => "EXISTS with correlation",
    "query" => [
        "select" => [
            [ "element" => [ "type" => "fld", "table" => "packagist_handle", "field" => "name" ] ]
        ],
        "from" => "packagist_handle",
        "where" => [
            "type" => "op",
            "operator" => "EXISTS",
            "params" => [
                [
                    "type" => "subquery",
                    "query" => [
                        "select" => [
                            [ "element" => [ "type" => "fld", "table" => "packagist_package", "field" => "id" ] ]
                        ],
                        "from" => "packagist_package",
                        "where" => [
                            "type" => "op",
                            "operator" => "=",
                            "params" => [
                                [ "type" => "fld", "table" => "packagist_package", "field" => "handle_id" ],
                                [ "type" => "fld", "table" => "packagist_handle", "field" => "id" ]
                            ]
                        ]
                    ]
                ]
            ]
        ],
        "limit" => 5
    ]
]

        ];

        // Ausführen & ausgeben
        foreach ($testCases as $index => $test) {
            $out .= "<hr><h2>🧪 Test #" . ($index + 1) . ": " . htmlspecialchars($test['title']) . "</h2>";

            try {
                $result = $this->dataqueryservice->executeQuery($test['query']);

                // Query anzeigen
                $out .= "<h3>🧾 Query JSON</h3><pre>" . htmlspecialchars(json_encode($test['query'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>";
                $out .= "<h3>🧠 Generated SQL</h3><pre>" . htmlspecialchars($result->debugSql ?? '[n/a]') . "</pre>";

                // Ergebnis-Tabelle
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
        }

        return $out;
    }

    public function getHelp() {
        return 'Help of TestOutput' . "\n";
    }
}

