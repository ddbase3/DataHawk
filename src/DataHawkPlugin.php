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

namespace DataHawk;

use Base3\Api\ICheck;
use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Configuration\Api\IConfiguration;
use Base3\Database\Api\IDatabase;
use DataHawk\Api\IReportExporterFactory;
use DataHawk\Service\DefaultReportQueryService;
use DataHawk\Schema\DefaultReportSchemaProvider;
use DataHawk\Compiler\DefaultTableNameResolver;
use DataHawk\Compiler\MysqlReportQueryCompiler;
use DataHawk\Materialization\DatabaseMaterializationRegistry;
use DataHawk\Materialization\DefaultMaterializationService;
use DataHawk\Materialization\ManifestMaterializationSchemaProvider;
use DataHawk\Materialization\MaterializationPhysicalTableNameGenerator;
use DataHawk\Materialization\MaterializationTableNameResolver;
use DataHawk\Service\ReportExporterFactory;
use ResourceFoundation\Api\IMaterializationManifestProvider;
use ResourceFoundation\Api\IMaterializationRegistry;
use ResourceFoundation\Api\IMaterializationRunRepository;
use ResourceFoundation\Api\IMaterializationSchemaProvider;
use ResourceFoundation\Api\IMaterializationService;
use ResourceFoundation\Api\IQueryCompiler;
use ResourceFoundation\Api\ITableNameResolver;
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
                                IMaterializationRegistry::class,
                                fn($c) => new DatabaseMaterializationRegistry(
                                        $c->get(IDatabase::class)),
                                IContainer::SHARED | IContainer::NOOVERWRITE)

                        ->set(
                                IMaterializationRunRepository::class,
                                fn($c) => $c->get(IMaterializationRegistry::class),
                                IContainer::SHARED | IContainer::NOOVERWRITE)

                        ->set(
                                ManifestMaterializationSchemaProvider::class,
                                fn($c) => new ManifestMaterializationSchemaProvider(
                                        dirname(__DIR__) . '/local/DataHawk/materialized',
                                        $c->get(IDatabase::class)
                                ),
                                IContainer::SHARED | IContainer::NOOVERWRITE)

                        ->set(
                                IMaterializationManifestProvider::class,
                                ManifestMaterializationSchemaProvider::class,
                                IContainer::ALIAS | IContainer::NOOVERWRITE)

                        ->set(
                                IMaterializationSchemaProvider::class,
                                ManifestMaterializationSchemaProvider::class,
                                IContainer::ALIAS | IContainer::NOOVERWRITE)

                        ->set(
                                MaterializationPhysicalTableNameGenerator::class,
                                fn($c) => new MaterializationPhysicalTableNameGenerator(),
                                IContainer::SHARED | IContainer::NOOVERWRITE)

                        ->set(
                                IMaterializationService::class,
                                fn($c) => new DefaultMaterializationService(
                                        $c->get(IMaterializationManifestProvider::class),
                                        $c->get(IQueryService::class),
                                        $c->get(IMaterializationRegistry::class),
                                        $c->get(MaterializationPhysicalTableNameGenerator::class),
                                        $c->get(IMaterializationRunRepository::class),
                                        $c->get(IDatabase::class)
                                ),
                                IContainer::SHARED | IContainer::NOOVERWRITE)

                        ->set(
                                MaterializationTableNameResolver::class,
                                fn($c) => new MaterializationTableNameResolver(
                                        $c->get(IMaterializationRegistry::class)),
                                IContainer::SHARED | IContainer::NOOVERWRITE)

                        ->set(
                                ITableNameResolver::class,
                                fn($c) => new DefaultTableNameResolver(),
                                IContainer::SHARED | IContainer::NOOVERWRITE)

                        ->set(
                                IQueryCompiler::class,
                                fn($c) => new MysqlReportQueryCompiler(
                                        $c->get(IQuerySchemaProvider::class),
                                        $c->get(ITableNameResolver::class)),
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
