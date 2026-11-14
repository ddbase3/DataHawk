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

namespace Base3\DataHawk;

use Base3\CoreFoundation\Api\ICheck;
use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Api\IPlugin;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\DataHawk\Compiler\MysqlReportQueryCompiler;
use Base3\DataHawk\Materialization\CompositeMaterializationSchemaProvider;
use Base3\DataHawk\Materialization\DatabaseMaterializationRegistry;
use Base3\DataHawk\Materialization\DefaultMaterializationService;
use Base3\DataHawk\Materialization\MaterializationPhysicalTableNameGenerator;
use Base3\DataHawk\Materialization\MaterializationTableNameResolver;
use Base3\DataHawk\Reporting\ReportingScopeRegistry;
use Base3\DataHawk\Schema\CompositeQuerySchemaProvider;
use Base3\DataHawk\Schema\DataHawkQuerySchemaProviderRegistry;
use Base3\DataHawk\Service\DefaultReportQueryService;
use Base3\ResourceFoundation\Api\IMaterializationManifestProvider;
use Base3\ResourceFoundation\Api\IMaterializationRegistry;
use Base3\ResourceFoundation\Api\IMaterializationRunRepository;
use Base3\ResourceFoundation\Api\IMaterializationSchemaProvider;
use Base3\ResourceFoundation\Api\IMaterializationService;
use Base3\ResourceFoundation\Api\IQueryCompiler;
use Base3\ResourceFoundation\Api\IQuerySchemaProvider;
use Base3\ResourceFoundation\Api\IQueryService;
use Base3\ResourceFoundation\Api\IReportingScopeRegistry;
use Base3\ResourceFoundation\Api\IScopedMaterializationManifestProvider;
use Base3\ResourceFoundation\Api\IScopedQuerySchemaProvider;
use Base3\ResourceFoundation\Api\ITableNameResolver;

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
                                IReportingScopeRegistry::class,
                                fn($c) => new ReportingScopeRegistry($c->get(IClassMap::class)),
                                IContainer::SHARED | IContainer::NOOVERWRITE)

                        ->set(
                                DataHawkQuerySchemaProviderRegistry::class,
                                fn($c) => new DataHawkQuerySchemaProviderRegistry(
                                        $c->get(IClassMap::class),
                                        $c->get(IDatabase::class)),
                                IContainer::SHARED | IContainer::NOOVERWRITE)

                        ->set(
                                CompositeQuerySchemaProvider::class,
                                fn($c) => new CompositeQuerySchemaProvider(
                                        $c->get(DataHawkQuerySchemaProviderRegistry::class)),
                                IContainer::SHARED | IContainer::NOOVERWRITE)

                        ->set(
                                IQuerySchemaProvider::class,
                                CompositeQuerySchemaProvider::class,
                                IContainer::ALIAS | IContainer::NOOVERWRITE)

                        ->set(
                                IScopedQuerySchemaProvider::class,
                                CompositeQuerySchemaProvider::class,
                                IContainer::ALIAS | IContainer::NOOVERWRITE)

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
                                CompositeMaterializationSchemaProvider::class,
                                fn($c) => new CompositeMaterializationSchemaProvider(
                                        $c->get(DataHawkQuerySchemaProviderRegistry::class)),
                                IContainer::SHARED | IContainer::NOOVERWRITE)

                        ->set(
                                IMaterializationManifestProvider::class,
                                CompositeMaterializationSchemaProvider::class,
                                IContainer::ALIAS | IContainer::NOOVERWRITE)

                        ->set(
                                IScopedMaterializationManifestProvider::class,
                                CompositeMaterializationSchemaProvider::class,
                                IContainer::ALIAS | IContainer::NOOVERWRITE)

                        ->set(
                                IMaterializationSchemaProvider::class,
                                CompositeMaterializationSchemaProvider::class,
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
                                fn($c) => $c->get(MaterializationTableNameResolver::class),
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
                                IContainer::SHARED | IContainer::NOOVERWRITE);
        }

        // Implementation of ICheck

        public function checkDependencies() {
                return array(
                        'resourcefoundationplugin_installed' => $this->container->get('resourcefoundationplugin') ? 'Ok' : 'resourcefoundationplugin not installed'
                );
        }
}
