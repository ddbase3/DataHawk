<?php declare(strict_types=1);

namespace DataHawk\Content;

use Base3\Api\IOutput;
use DataHawk\Api\IReportQueryService;
use DataHawk\Api\IReportExporterFactory;

class TestOutputGit implements IOutput {

	public function __construct(
		private readonly IReportQueryService $dataqueryservice,
		private readonly IReportExporterFactory $reportexporterfactory
	) {}

	public static function getName(): string {
		return 'testoutputgit';
	}

	public function getOutput($out = "html") {
		$out = '';

		$tables = $this->dataqueryservice->listTables();
		$out .= "<h2>🧭 Git Tables Overview</h2><ul>";
		foreach ($tables as $table) {
			if (str_starts_with($table->name, 'git_')) {
				$out .= "<li><strong>{$table->name}</strong> – {$table->description}</li>";
			}
		}
		$out .= "</ul>";

		$out .= '<pre>' . htmlspecialchars(json_encode($this->dataqueryservice->getTable('git_topic'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';

		$testCases = [

			// Join: repository -> license -> owner
			[
				"title" => "Git repository with license and owner",
				"query" => [
					"select" => [
						[
							"element" => [ "type" => "fld", "table" => "git_repository", "field" => "name" ],
							"alias" => "repository"
						],
						[
							"element" => [ "type" => "fld", "table" => "git_license", "field" => "name", "variant" => "optional" ],
							"alias" => "license"
						],
						[ "element" => [ "type" => "fld", "table" => "git_owner", "field" => "login", "variant" => "required" ] ]
					],
					"from" => "git_repository",
					"limit" => 10
				]
			],

			// Join über repository -> branch -> commit message
			[
				"title" => "Latest commit messages for each repository",
				"query" => [
					"select" => [
						[ "element" => [ "type" => "fld", "table" => "git_repository", "field" => "full_name" ] ],
						[ "element" => [ "type" => "fld", "table" => "git_branch", "field" => "name", "variant" => "optional" ] ],
						[ "element" => [ "type" => "fld", "table" => "git_branch", "field" => "message", "variant" => "optional" ] ],
						[ "element" => [ "type" => "fld", "table" => "git_branch", "field" => "date", "variant" => "optional" ] ]
					],
					"from" => "git_repository",
					"limit" => 10
				]
			],

			// Join auf git_topic
			[
				"title" => "Repositories with topics",
				"query" => [
					"select" => [
						[ "element" => [ "type" => "fld", "table" => "git_repository", "field" => "name" ] ],
						[ "element" => [ "type" => "fld", "table" => "git_topic", "field" => "topic", "variant" => "optional" ] ]
					],
					"from" => "git_repository",
					"limit" => 20
				]
			],

			// Join auf git_owner mit WHERE Bedingung
			[
				"title" => "Repositories of user ddbase3",
				"query" => [
					"select" => [
						[ "element" => [ "type" => "fld", "table" => "git_repository", "field" => "full_name" ] ],
						[ "element" => [ "type" => "fld", "table" => "git_owner", "field" => "login", "variant" => "required" ] ]
					],
					"from" => "git_repository",
					"where" => [
						"type" => "op",
						"operator" => "=",
						"params" => [
							[ "type" => "fld", "table" => "git_owner", "field" => "login" ],
							"ddbase3"
						]
					],
					"limit" => 10
				]
			]

		];

		foreach ($testCases as $index => $test) {
			$out .= "<hr><h2>🧪 Test #" . ($index + 1) . ": " . htmlspecialchars($test['title']) . "</h2>";

			try {
				$out .= "<h3>🧾 Query JSON</h3><pre>" . htmlspecialchars(json_encode($test['query'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "</pre>";

				$exporter = $this->reportexporterfactory->createExporter('htmltablereportexporter');
				$out .= $exporter->setExportQuery($test['query'])->toString();

				$result = $exporter->getResult();
				if ($result != null) {
					$out .= '<h3>' . ($result->sensitive ? 'SENSITIVE' : 'PUBLIC') . '</h3>';
					$out .= "<h3>🧠 Generated SQL</h3><pre>" . htmlspecialchars($result->debugSql ?? '[n/a]') . "</pre>";
				}
			} catch (\Throwable $e) {
				$out .= "<p style='color:red;'>❌ Query failed: " . htmlspecialchars($e->getMessage()) . "</p>";
			}
		}

		return $out;
	}

	public function getHelp() {
		return 'Help for TestOutputGit' . "\n";
	}
}

