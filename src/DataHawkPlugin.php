<?php declare(strict_types=1);

namespace DataHawk;

use Base3\Api\ICheck;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use DataHawk\Api\IDataQueryService;
use DataHawk\Api\ISchemaProvider;
use DataHawk\Api\IQueryCompiler;
use DataHawk\Service\DefaultDataQueryService;
use DataHawk\Schema\SchemaProvider;
use DataHawk\Compiler\QueryCompiler;

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

			->set(ISchemaProvider::class, fn($c) => new SchemaProvider, IContainer::SHARED)
			->set(IQueryCompiler::class, fn($c) => new QueryCompiler($c->get(ISchemaProvider::class)), IContainer::SHARED)
			->set(IDataQueryService::class, fn($c) => new DefaultDataQueryService($c->get(ISchemaProvider::class), $c->get(IQueryCompiler::class), $c), IContainer::SHARED);
	}

	// Implementation of ICheck

	public function checkDependencies() {
		return array(
			"moduledpageplugin_installed" => $this->container->get('moduledpageplugin') ? "Ok" : "moduledpageplugin not installed"
		);
	}
}
