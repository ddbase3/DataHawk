<?php declare(strict_types=1);

namespace DataHawk\Test\Schema;

use Base3\Configuration\Api\IConfiguration;
use DataHawk\Schema\DefaultReportSchemaProvider;
use PHPUnit\Framework\TestCase;

final class DefaultReportSchemaProviderTest extends TestCase {

	private string $directory;

	protected function setUp(): void {
		parent::setUp();
		$this->directory = sys_get_temp_dir() . '/datahawk_legacy_schema_' . bin2hex(random_bytes(6));
		mkdir($this->directory . '/datahawk', 0777, true);
	}

	protected function tearDown(): void {
		foreach(glob($this->directory . '/datahawk/*') ?: [] as $file) {
			unlink($file);
		}
		rmdir($this->directory . '/datahawk');
		rmdir($this->directory);
		parent::tearDown();
	}

	public function testUsesConfiguredDataDirectoryAndLoadsSchemas(): void {
		file_put_contents($this->directory . '/datahawk/orders.json', '{"name":"orders","fields":[{"name":"id","type":"integer"}]}');
		$provider = $this->provider(['data' => $this->directory . '/']);
		self::assertSame('orders', $provider->getTable('orders')->name);
		self::assertSame('id', $provider->getSchema()[0]->fields[0]->name);
	}

	public function testAbsentDataDirectoryReturnsEmptySchema(): void {
		self::assertSame([], $this->provider([])->getSchema());
		self::assertSame([], $this->provider(['data' => ''])->getSchema());
	}

	public function testRejectsInvalidSchemaJsonFromConfiguredDirectory(): void {
		file_put_contents($this->directory . '/datahawk/broken.json', '{');
		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Invalid schema JSON');
		$this->provider(['data' => $this->directory])->getSchema();
	}

	private function provider(mixed $directories): DefaultReportSchemaProvider {
		$configuration = $this->createStub(IConfiguration::class);
		$configuration->method('get')->willReturn($directories);
		return new DefaultReportSchemaProvider($configuration);
	}
}
