<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of DataHawk for BASE3 Framework.
 *
 * DataHawk extends BASE3 with schema-driven reporting and data queries.
 * Structured query definitions compile to backend-specific statements.
 * Execution uses the shared BASE3 database and resource contracts.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/datahawk
 * https://github.com/ddbase3/DataHawk
 **********************************************************************/

namespace Base3\DataHawk\Test\Schema;

use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use Base3\DataHawk\Schema\DefaultQuerySchemaDefinitionProvider;
use PHPUnit\Framework\TestCase;

final class DefaultQuerySchemaDefinitionProviderTest extends TestCase {

	private string $dataDirectory;

	protected function setUp(): void {
		parent::setUp();
		$this->dataDirectory = sys_get_temp_dir() . '/datahawk_definitions_' . bin2hex(random_bytes(6));
		mkdir($this->dataDirectory . '/datahawk', 0777, true);
	}

	protected function tearDown(): void {
		foreach(glob($this->dataDirectory . '/datahawk/*') ?: [] as $file) {
			@unlink($file);
		}
		@rmdir($this->dataDirectory . '/datahawk');
		@rmdir($this->dataDirectory);
		parent::tearDown();
	}

	public function testExposesDiscoverableNameAndDefaultScope(): void {
		$provider = $this->provider(['data' => $this->dataDirectory]);
		self::assertSame('datahawkdefaultqueryschemadefinitionprovider', $provider::getName());
		self::assertSame('default', $provider->getScope());
	}

	public function testReturnsEmptyDefinitionsWithoutConfiguredDataDirectory(): void {
		self::assertSame([], $this->provider([])->getDefinitions());
	}

	public function testLoadsJsonFilesInDeterministicOrder(): void {
		file_put_contents($this->dataDirectory . '/datahawk/z.json', '{"name":"z"}');
		file_put_contents($this->dataDirectory . '/datahawk/a.json', '{"name":"a"}');
		$definitions = $this->provider(['data' => $this->dataDirectory])->getDefinitions();

		self::assertSame(['a', 'z'], array_keys($definitions));
		self::assertTrue($definitions['a']['enabled']);
		self::assertSame(['name' => 'z'], $definitions['z']['definition']);
	}

	public function testRejectsInvalidJson(): void {
		file_put_contents($this->dataDirectory . '/datahawk/broken.json', '{');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid DataHawk schema JSON');
		$this->provider(['data' => $this->dataDirectory])->getDefinitions();
	}

	public function testIgnoresNonJsonFiles(): void {
		file_put_contents($this->dataDirectory . '/datahawk/readme.txt', 'not a definition');
		self::assertSame([], $this->provider(['data' => $this->dataDirectory])->getDefinitions());
	}

	private function provider(array $directories): DefaultQuerySchemaDefinitionProvider {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn(['data' => $directories['data'] ?? '']);
		return new DefaultQuerySchemaDefinitionProvider($config);
	}
}
