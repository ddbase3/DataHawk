<?php declare(strict_types=1);

namespace DataHawk;

use Base3\Api\ICheck;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use DataHawk\Api\IReportQueryService;
use DataHawk\Api\IReportSchemaProvider;
use DataHawk\Api\IReportQueryCompiler;
use DataHawk\Service\DefaultReportQueryService;
use DataHawk\Schema\DefaultReportSchemaProvider;
use DataHawk\Compiler\ReportQueryCompiler;

class DataHawkPlugin implements IPlugin, ICheck {

	public function __construct(private readonly IContainer $container) {}

	// Implementation of IBase

	public static function getName(): string {
		return "datahawkplugin";
	}

	// Implementation of IPlugin

	public function init() {

		$this->container
			->set(self::getName(), $this, IContainer::SHARED)

			->set(IReportSchemaProvider::class, fn($c) => new DefaultReportSchemaProvider, IContainer::SHARED)
			->set(IReportQueryCompiler::class, fn($c) => new ReportQueryCompiler($c->get(IReportSchemaProvider::class)), IContainer::SHARED)
			->set(IReportQueryService::class, fn($c) => new DefaultReportQueryService($c->get(IReportSchemaProvider::class), $c->get(IReportQueryCompiler::class), $c), IContainer::SHARED);
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return array(
			"moduledpageplugin_installed" => $this->container->get('moduledpageplugin') ? "Ok" : "moduledpageplugin not installed"
		);
	}
}
