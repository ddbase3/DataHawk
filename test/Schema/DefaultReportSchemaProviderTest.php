<?php declare(strict_types=1);

namespace DataHawk\Test\Schema;

use PHPUnit\Framework\TestCase;
use DataHawk\Schema\DefaultReportSchemaProvider;
use Base3\Configuration\Api\IConfiguration;
use ResourceFoundation\Dto\TableMetadata;
use ResourceFoundation\Dto\FieldMetadata;
use ResourceFoundation\Dto\JoinMetadata;

class DefaultReportSchemaProviderTest extends TestCase {

        private string $baseDir;
        private string $schemaDir;

        protected function setUp(): void {
                $this->baseDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
                        . DIRECTORY_SEPARATOR
                        . 'datahawk_test_' . bin2hex(random_bytes(6));

                $this->schemaDir = $this->baseDir . DIRECTORY_SEPARATOR . 'datahawk' . DIRECTORY_SEPARATOR;

                @mkdir($this->schemaDir, 0777, true);
        }

        protected function tearDown(): void {
                $this->rmDirRecursive($this->baseDir);
        }

        public function testGetDataDirResolvesConfiguredDirectory(): void {
                $config = new FakeConfiguration([
                        'directories' => [
                                // absichtlich mit trailing slash getestet
                                'data' => $this->baseDir . DIRECTORY_SEPARATOR,
                        ]
                ]);

                $provider = new DefaultReportSchemaProvider($config);

                $dir = $this->callProtected($provider, 'getDataDir');

                $this->assertSame($this->schemaDir, $dir);
        }

        public function testGetSchemaLoadsAndDeserializesJsonFilesAndAppliesDefaults(): void {
                // valid schema file
                file_put_contents($this->schemaDir . 'users.json', json_encode([
                        'name' => 'users',
                        'label' => 'Users',
                        'description' => 'User table',
                        'domain' => 'crm',
                        'category' => 'core',
                        'tags' => ['pii'],
                        'sensitive' => true,
                        'defaultFilters' => ['active' => 1],
                        'fields' => [
                                [
                                        'name' => 'id',
                                        'type' => 'int',
                                        'primaryKey' => true,
                                        'nullable' => false,
                                ],
                                [
                                        'name' => 'company_id',
                                        'type' => 'int',
                                        'foreignKey' => [
                                                'table' => 'companies',
                                                'column' => 'id',
                                        ],
                                        // nullable fehlt => default true
                                ],
                                [
                                        'name' => 'email',
                                        'type' => 'string',
                                        // tags fehlt => default []
                                        // alias fehlt => default null
                                        // sensitive fehlt => default false
                                ],
                        ],
                        'joins' => [
                                [
                                        'targetTable' => 'companies',
                                        // JoinMetadata erwartet array -> Testdaten entsprechend
                                        'on' => ['users.company_id = companies.id'],
                                        // type fehlt => default INNER
                                        // meta fehlt => default []
                                ]
                        ],
                ], JSON_THROW_ON_ERROR));

                // invalid json file (should be ignored)
                file_put_contents($this->schemaDir . 'invalid.json', 'null');

                $config = new FakeConfiguration([
                        'directories' => ['data' => $this->baseDir],
                ]);

                $provider = new DefaultReportSchemaProvider($config);

                $schema = $provider->getSchema();

                $this->assertIsArray($schema);
                $this->assertCount(1, $schema, 'Invalid/non-array JSON should be skipped');

                $table = $schema[0];
                $this->assertInstanceOf(TableMetadata::class, $table);

                $this->assertSame('users', $table->name);
                $this->assertSame('Users', $table->label);
                $this->assertSame('User table', $table->description);
                $this->assertSame('crm', $table->domain);
                $this->assertSame('core', $table->category);
                $this->assertSame(['pii'], $table->tags);
                $this->assertSame(['active' => 1], $table->defaultFilters);
                $this->assertTrue($table->sensitive);

                $this->assertIsArray($table->fields);
                $this->assertCount(3, $table->fields);
                $this->assertInstanceOf(FieldMetadata::class, $table->fields[0]);

                // field: id
                $id = $table->fields[0];
                $this->assertSame('id', $id->name);
                $this->assertSame('int', $id->type);
                $this->assertTrue($id->primaryKey);
                $this->assertFalse($id->nullable);

                // field: company_id with FK ref (nur strukturell prüfen, nicht auf konkrete Klasse festnageln)
                $companyId = $table->fields[1];
                $this->assertSame('company_id', $companyId->name);
                $this->assertTrue($companyId->nullable, 'nullable default should be true');

                $this->assertNotNull($companyId->foreignKey);
                $this->assertSame('companies', $companyId->foreignKey->table);
                $this->assertSame('id', $companyId->foreignKey->column);

                // field: email defaults
                $email = $table->fields[2];
                $this->assertSame('email', $email->name);
                $this->assertSame([], $email->tags);
                $this->assertNull($email->alias);
                $this->assertFalse($email->sensitive);

                // joins defaults
                $this->assertIsArray($table->joins);
                $this->assertCount(1, $table->joins);
                $this->assertInstanceOf(JoinMetadata::class, $table->joins[0]);

                $join = $table->joins[0];
                $this->assertSame('companies', $join->targetTable);
                $this->assertSame(['users.company_id = companies.id'], $join->on);
                $this->assertSame('INNER', $join->type);
                $this->assertSame([], $join->meta);
        }

        public function testGetTableReturnsTableByNameOrNull(): void {
                file_put_contents($this->schemaDir . 'a.json', json_encode([
                        'name' => 'a',
                        'domain' => 'd',
                        'category' => 'c',
                        'fields' => [],
                        'joins' => [],
                ], JSON_THROW_ON_ERROR));

                $config = new FakeConfiguration([
                        'directories' => ['data' => $this->baseDir],
                ]);

                $provider = new DefaultReportSchemaProvider($config);

                $this->assertInstanceOf(TableMetadata::class, $provider->getTable('a'));
                $this->assertNull($provider->getTable('does_not_exist'));
        }

        public function testGetSchemaIsCachedAndDoesNotPickUpNewFilesAfterFirstCall(): void {
                file_put_contents($this->schemaDir . 'one.json', json_encode([
                        'name' => 'one',
                        'domain' => 'd',
                        'category' => 'c',
                        'fields' => [],
                        'joins' => [],
                ], JSON_THROW_ON_ERROR));

                $config = new FakeConfiguration([
                        'directories' => ['data' => $this->baseDir],
                ]);

                $provider = new DefaultReportSchemaProvider($config);

                $first = $provider->getSchema();
                $this->assertCount(1, $first);

                // add new file after caching
                file_put_contents($this->schemaDir . 'two.json', json_encode([
                        'name' => 'two',
                        'domain' => 'd',
                        'category' => 'c',
                        'fields' => [],
                        'joins' => [],
                ], JSON_THROW_ON_ERROR));

                $second = $provider->getSchema();
                $this->assertCount(1, $second, 'Schema should remain cached after first getSchema() call');
                $this->assertSame('one', $second[0]->name);
        }

        private function callProtected(object $obj, string $method): mixed {
                $ref = new \ReflectionClass($obj);
                $m = $ref->getMethod($method);
                $m->setAccessible(true);
                return $m->invoke($obj);
        }

        private function rmDirRecursive(string $dir): void {
                if ($dir === '' || !is_dir($dir)) return;

                $items = scandir($dir);
                if ($items === false) return;

                foreach ($items as $item) {
                        if ($item === '.' || $item === '..') continue;
                        $path = $dir . DIRECTORY_SEPARATOR . $item;
                        if (is_dir($path)) {
                                $this->rmDirRecursive($path);
                        } else {
                                @unlink($path);
                        }
                }
                @rmdir($dir);
        }
}

class FakeConfiguration implements IConfiguration {

        public function __construct(private array $data = []) {}

        public function get($configuration = "") {
                if ($configuration === "") {
                        return $this->data;
                }
                return $this->data[$configuration] ?? null;
        }

        public function set($data, $configuration = "") {
                if ($configuration === "") {
                        $this->data = is_array($data) ? $data : ['' => $data];
                        return;
                }
                $this->data[$configuration] = $data;
        }

        public function save() {
                // no-op in tests
        }
}
