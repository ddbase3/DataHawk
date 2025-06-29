<?php declare(strict_types=1);

namespace DataHawk\Service;

use Base3\Api\IOutput;
use Base3\Api\IRequest;
use DataHawk\Api\IReportQueryService;
use DataHawk\Api\IReportExporterFactory;

class DataHawkSchema implements IOutput {

	public function __construct(
		private readonly IRequest $request,
		private readonly IReportQueryService $dataqueryservice,
		private readonly IReportExporterFactory $reportexporterfactory
	) {}

	// Implementation of IOutput

	public static function getName(): string {
		return 'datahawkschema';
	}

	public function getOutput($out = "html") {
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

	public function getHelp() {
		return 'Help of DataHawkSchema' . "\n";
	}
}

