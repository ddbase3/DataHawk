<?php declare(strict_types=1);

namespace DataHawk\Content;

use Base3\Api\IOutput;
use DataHawk\Api\IReportQueryService;
use DataHawk\Api\IReportExporterFactory;

class TestOutput2 implements IOutput {

    public function __construct(
        private readonly IReportQueryService $dataqueryservice,
        private readonly IReportExporterFactory $reportexporterfactory
    ) {}

    public static function getName(): string {
        return 'testoutput2';
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

            // Subquery mit COUNT
            [
                "title" => "Subquery COUNT(packages) per vendor",
                "query" => [
                    "select" => [
                        [
                            "element" => [ "type" => "fld", "table" => "packagist_handle", "field" => "name" ],
                            "alias" => "vendor"
                        ],
                        [
                            "element" => [
                                "type" => "subquery",
                                "query" => [
                                    "select" => [
                                        [
                                            "element" => [
                                                "type" => "fn",
                                                "function" => "COUNT",
                                                "params" => [
                                                    [ "type" => "fld", "table" => "packagist_package", "field" => "id" ]
                                                ]
                                            ],
                                            "alias" => "package_count"
                                        ]
                                    ],
                                    "from" => "packagist_package",
                                    "where" => [
                                        "type" => "op",
                                        "operator" => "=",
                                        "params" => [
                                            [ "type" => "fld", "table" => "packagist_package", "field" => "handle_id" ],
                                            [ "type" => "fld", "table" => "packagist_handle", "field" => "id", "variant" => "required" ]
                                        ]
                                    ]
                                ]
                            ],
                            "alias" => "package_count"
                        ]
                    ],
                    "from" => "packagist_handle",
                    "limit" => 10
                ]
            ],

            // IS NOT NULL & BETWEEN
            [
                "title" => "IS NOT NULL + BETWEEN",
                "query" => [
                    "select" => [
                        [
                            "element" => [ "type" => "fld", "table" => "packagist_package", "field" => "name" ]
                        ]
                    ],
                    "from" => "packagist_package",
                    "where" => [
                        "type" => "op",
                        "operator" => "AND",
                        "params" => [
                            [
                                "type" => "op",
                                "operator" => "IS NOT NULL",
                                "params" => [
                                    [ "type" => "fld", "table" => "packagist_package", "field" => "description" ]
                                ]
                            ],
                            [
                                "type" => "op",
                                "operator" => "BETWEEN",
                                "params" => [
                                    [ "type" => "fld", "table" => "packagist_package", "field" => "downloads" ],
                                    1,
                                    100
                                ]
                            ]
                        ]
                    ],
                    "limit" => 5
                ]
            ],

            // REGEXP
            [
                "title" => "REGEXP match vendor",
                "query" => [
                    "select" => [
                        [ "element" => [ "type" => "fld", "table" => "packagist_handle", "field" => "name" ] ]
                    ],
                    "from" => "packagist_handle",
                    "where" => [
                        "type" => "op",
                        "operator" => "REGEXP",
                        "params" => [
                            [ "type" => "fld", "table" => "packagist_handle", "field" => "name" ],
                            "^ddbase3"
                        ]
                    ],
                    "limit" => 5
                ]
            ],

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
                // Query anzeigen
                $out .= "<h3>🧾 Query JSON</h3><pre>" . htmlspecialchars(json_encode($test['query'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>";

                // Ergebnis-Tabelle
		$exporter = $this->reportexporterfactory->createExporter('htmltablereportexporter');
		$out .= $exporter->setExportQuery($test['query'])->toString();

		// SQL-Statement
		$result = $exporter->getResult();
		if ($result != null) {
		        $out .= "<h3>🧠 Generated SQL</h3><pre>" . htmlspecialchars($result->debugSql ?? '[n/a]') . "</pre>";
		}
            } catch (\Throwable $e) {
                $out .= "<p style='color:red;'>❌ Query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }

        return $out;
    }

    public function getHelp() {
        return 'Help of TestOutput2' . "\n";
    }
}

