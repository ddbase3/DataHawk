<?php declare(strict_types=1);

namespace DataHawk;

use Base3\Api\ICheck;
use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Configuration\Api\IConfiguration;
use DataHawk\Api\IReportExporterFactory;
use DataHawk\Service\DefaultReportQueryService;
use DataHawk\Schema\DefaultReportSchemaProvider;
use DataHawk\Compiler\MysqlReportQueryCompiler;
use DataHawk\Service\ReportExporterFactory;
use ResourceFoundation\Api\IQueryCompiler;
use ResourceFoundation\Api\IQuerySchemaProvider;
use ResourceFoundation\Api\IQueryService;

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

			->set(
				IQuerySchemaProvider::class,
				fn($c) => new DefaultReportSchemaProvider(
					$c->get(IConfiguration::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(
				IQueryCompiler::class,
				fn($c) => new MysqlReportQueryCompiler(
					$c->get(IQuerySchemaProvider::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(
				IQueryService::class,
				fn($c) => new DefaultReportQueryService(
					$c->get(IQuerySchemaProvider::class),
					$c->get(IQueryCompiler::class),
					$c),
				IContainer::SHARED | IContainer::NOOVERWRITE)

			->set(
				IReportExporterFactory::class,
				fn($c) => new ReportExporterFactory($c->get(IClassMap::class)),
				IContainer::SHARED | IContainer::NOOVERWRITE);
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return array(
			'resourcefoundationplugin_installed' => $this->container->get('resourcefoundationplugin') ? 'Ok' : 'resourcefoundationplugin not installed'
		);
	}
}
