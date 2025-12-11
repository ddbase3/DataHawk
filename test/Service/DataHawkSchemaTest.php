<?php declare(strict_types=1);

namespace DataHawk\Test\Service;

use PHPUnit\Framework\TestCase;
use DataHawk\Service\DataHawkSchema;
use Base3\Api\IRequest;
use DataHawk\Api\IReportExporterFactory;
use ResourceFoundation\Api\IQueryService;

class DataHawkSchemaTest extends TestCase {

        public function testGetNameReturnsExpectedValue(): void {
                $req = new FakeRequest([]);
                $svc = $this->createStub(IQueryService::class);
                $factory = $this->createStub(IReportExporterFactory::class);

                $out = new DataHawkSchema($req, $svc, $factory);

                $this->assertSame('datahawkschema', DataHawkSchema::getName());
        }

        public function testGetHelpReturnsString(): void {
                $req = new FakeRequest([]);
                $svc = $this->createStub(IQueryService::class);
                $factory = $this->createStub(IReportExporterFactory::class);

                $out = new DataHawkSchema($req, $svc, $factory);

                $this->assertSame("Help of DataHawkSchema\n", $out->getHelp());
        }

        public function testGetOutputJsonDefaultsToListTables(): void {
                $req = new FakeRequest([]); // no q in get/post

                $svc = $this->createMock(IQueryService::class);
                $svc->expects($this->once())
                        ->method('listTables')
                        ->willReturn(['t1', 't2']);

                $factory = $this->createStub(IReportExporterFactory::class);

                $out = new DataHawkSchema($req, $svc, $factory);

                $json = $out->getOutput('json');
                $this->assertJson($json);
                $this->assertSame(["t1","t2"], json_decode($json, true));
        }

        public function testGetOutputJsonDomainsUsesGetParamOverPostParam(): void {
                $req = new FakeRequest(['q' => 'domains'], ['q' => 'categories']);

                $svc = $this->createMock(IQueryService::class);
                $svc->expects($this->once())
                        ->method('listDomains')
                        ->willReturn(['d1', 'd2']);

                $factory = $this->createStub(IReportExporterFactory::class);

                $out = new DataHawkSchema($req, $svc, $factory);

                $json = $out->getOutput('json');
                $this->assertSame(["d1","d2"], json_decode($json, true));
        }

        public function testGetOutputJsonCategoriesUsesPostWhenGetMissing(): void {
                $req = new FakeRequest([], ['q' => 'categories']);

                $svc = $this->createMock(IQueryService::class);
                $svc->expects($this->once())
                        ->method('listCategories')
                        ->willReturn(['c1']);

                $factory = $this->createStub(IReportExporterFactory::class);

                $out = new DataHawkSchema($req, $svc, $factory);

                $json = $out->getOutput('json');
                $this->assertSame(["c1"], json_decode($json, true));
        }

        public function testGetOutputJsonTags(): void {
                $req = new FakeRequest(['q' => 'tags']);

                $svc = $this->createMock(IQueryService::class);
                $svc->expects($this->once())
                        ->method('listTags')
                        ->willReturn(['x', 'y']);

                $factory = $this->createStub(IReportExporterFactory::class);

                $out = new DataHawkSchema($req, $svc, $factory);

                $json = $out->getOutput('json');
                $this->assertSame(["x","y"], json_decode($json, true));
        }
}

/**
 * Minimal request fake for tests.
 */
class FakeRequest implements \Base3\Api\IRequest {

        public function __construct(
                private array $get = [],
                private array $post = [],
                private array $cookie = [],
                private array $session = [],
                private array $server = [],
                private array $files = [],
                private array $jsonBody = [],
                private bool $isCli = true,
                private string $context = \Base3\Api\IRequest::CONTEXT_TEST
        ) {}

        public function get(string $key, $default = null) {
                return array_key_exists($key, $this->get) ? $this->get[$key] : $default;
        }

        public function post(string $key, $default = null) {
                return array_key_exists($key, $this->post) ? $this->post[$key] : $default;
        }

        public function request(string $key, $default = null) {
                // POST takes precedence over GET
                if (array_key_exists($key, $this->post)) return $this->post[$key];
                if (array_key_exists($key, $this->get)) return $this->get[$key];
                return $default;
        }

        public function allRequest(): array {
                return array_merge($this->get, $this->post);
        }

        public function cookie(string $key, $default = null) {
                return array_key_exists($key, $this->cookie) ? $this->cookie[$key] : $default;
        }

        public function session(string $key, $default = null) {
                return array_key_exists($key, $this->session) ? $this->session[$key] : $default;
        }

        public function server(string $key, $default = null) {
                return array_key_exists($key, $this->server) ? $this->server[$key] : $default;
        }

        public function files(string $key, $default = null) {
                return array_key_exists($key, $this->files) ? $this->files[$key] : $default;
        }

        public function allGet(): array { return $this->get; }
        public function allPost(): array { return $this->post; }
        public function allCookie(): array { return $this->cookie; }
        public function allSession(): array { return $this->session; }
        public function allServer(): array { return $this->server; }
        public function allFiles(): array { return $this->files; }

        public function getJsonBody(): array {
                return $this->jsonBody;
        }

        public function isCli(): bool {
                return $this->isCli;
        }

        public function getContext(): string {
                return $this->context;
        }
}
