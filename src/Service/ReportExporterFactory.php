<?php declare(strict_types=1);

namespace DataHawk\Service;

use Base3\Api\IClassMap;
use DataHawk\Api\IReportExporter;
use DataHawk\Api\IReportExporterFactory;

class ReportExporterFactory implements IReportExporterFactory {

	public function __construct(private readonly IClassMap $classmap) {}

	public function createExporter(string $name): IReportExporter {
		$exporter = $this->classmap->getInstanceByInterfaceName(IReportExporter::class, $name);

		if (!$exporter instanceof IReportExporter) {
			throw new \RuntimeException("Report exporter '$name' could not be instantiated or is invalid");
		}

		return $exporter;
	}
}

