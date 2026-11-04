<?php declare(strict_types=1);

namespace DataHawk\Test\Reporting;

use ResourceFoundation\Api\IReportingScopeDefinitionProvider;
use ResourceFoundation\Dto\ReportingScopeDefinition;

final class ReportingScopeDefinitionProviderFixture implements IReportingScopeDefinitionProvider {

	public function __construct(private readonly ReportingScopeDefinition $definition) {}

	public static function getName(): string {
		return 'datahawkreportingscopedefinitionproviderfixture';
	}

	public function getReportingScopeDefinition(): ReportingScopeDefinition {
		return $this->definition;
	}
}
