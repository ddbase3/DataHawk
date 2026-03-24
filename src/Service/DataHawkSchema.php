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

namespace DataHawk\Service;

use Base3\Api\IOutput;
use Base3\Api\IRequest;
use DataHawk\Api\IReportExporterFactory;
use ResourceFoundation\Api\IQueryService;

class DataHawkSchema implements IOutput {

	public function __construct(
		private readonly IRequest $request,
		private readonly IQueryService $dataqueryservice,
		private readonly IReportExporterFactory $reportexporterfactory
	) {}

	// Implementation of IOutput

	public static function getName(): string {
		return 'datahawkschema';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if ($out != 'json') die();

		switch ($this->request->get('q') ?? $this->request->post('q')) {

			case 'domains':
				return json_encode($this->dataqueryservice->listDomains(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

			case 'categories':
				return json_encode($this->dataqueryservice->listCategories(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

			case 'tags':
				return json_encode($this->dataqueryservice->listTags(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

			default:
				return json_encode($this->dataqueryservice->listTables(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		}

		return '';
	}

	public function getHelp(): string {
		return 'Help of DataHawkSchema' . "\n";
	}
}
