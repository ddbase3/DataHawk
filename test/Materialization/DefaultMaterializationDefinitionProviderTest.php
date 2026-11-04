<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use DataHawk\Materialization\DefaultMaterializationDefinitionProvider;
use PHPUnit\Framework\TestCase;

final class DefaultMaterializationDefinitionProviderTest extends TestCase {

	public function testDeclaresStableDiscoverableNameAndScope(): void {
		$provider = new DefaultMaterializationDefinitionProvider();
		self::assertSame('datahawkdefaultmaterializationdefinitionprovider', $provider::getName());
		self::assertSame('datahawk_materialized', $provider->getScope());
	}

	public function testLocalDefinitionsAreReadableWithoutMutatingProjectState(): void {
		$definitions = (new DefaultMaterializationDefinitionProvider())->getDefinitions();
		self::assertIsArray($definitions);
		foreach($definitions as $name => $dataset) {
			self::assertTrue(is_string($name) || is_int($name));
			self::assertTrue($dataset['enabled']);
			self::assertIsArray($dataset['definition']);
		}
	}

}
