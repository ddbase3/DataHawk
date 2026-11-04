<?php declare(strict_types=1);

namespace DataHawk\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IContainer;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Authorization\Dto\AuthorizationRequest;
use Base3\Database\Api\IDatabase;
use Base3\LinkTarget\Api\ILinkTargetService;
use DataHawk\Display\DataHawkMaterializationManifestDisplay;
use DataHawk\Display\DataHawkMaterializationOverviewDisplay;
use DataHawk\Display\DataHawkMaterializationRegistryDisplay;
use DataHawk\Display\DataHawkMaterializationRunDisplay;
use DataHawk\Display\DataHawkMaterializationTableDisplay;
use PHPUnit\Framework\TestCase;
use ResourceFoundation\Api\IReportingScopeRegistry;

final class DataHawkMaterializationDisplayTest extends TestCase {

	public function testEveryDisplayHasStableIdentityAndLocalizedHelp(): void {
		foreach($this->displays() as [$class, $suffix, $help]) {
			self::assertSame('datahawkmaterialization' . $suffix . 'display', $class::getName());
			$display = $this->display($class);
			self::assertSame($help, $display->getHelp());
		}
	}

	public function testAllDisplaysDenyAccessWithoutPositiveAuthorizationDecision(): void {
		foreach($this->displays() as [$class, $suffix]) {
			$authorization = $this->createMock(IAuthorizationService::class);
			$authorization->method('getDefaultAuthority')->willReturn('host');
			$authorization->expects($this->once())->method('decide')->with($this->callback(static function(AuthorizationRequest $request) use($suffix): bool {
				return $request->authority === 'host' &&
					$request->permission->scope === 'feature' &&
					$request->permission->permission === 'datahawkmaterialization' . $suffix . 'settingstab';
			}))->willReturn(AuthorizationDecision::deny());
			$display = $this->display($class, $authorization);
			self::assertSame('', $display->getOutput('json'));
		}
		http_response_code(200);
	}

	public function testAllowedHtmlRenderingPassesItsViewAndServiceLink(): void {
		foreach($this->displays() as [$class, $suffix]) {
			$assigned = [];
			$view = $this->createStub(IMvcView::class);
			$view->method('getBricks')->willReturn([]);
			$view->method('loadTemplate')->willReturn('<html>rendered</html>');
			$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void {
				$assigned[$key] = $value;
			});
			$display = $this->display($class, null, $view);
			self::assertSame('<html>rendered</html>', $display->getOutput('HTML'));
			self::assertSame($suffix === 'overview' ? 'overview' : ($suffix === 'manifest' ? 'manifests' : ($suffix === 'run' ? 'runs' : ($suffix === 'table' ? 'tables' : 'registry'))), $assigned['viewName']);
			self::assertSame('/datahawk/json', $assigned['service']);
		}
	}

	public function testJsonPageRequestReturnsViewSpecificResponse(): void {
		foreach($this->displays() as [$class, $suffix]) {
			$request = $this->createStub(IRequest::class);
			$request->method('getJsonBody')->willReturn(['mode' => 'page']);
			$display = $this->display($class, null, null, $request);
			$response = json_decode($display->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
			self::assertTrue($response['ok']);
			self::assertSame('page', $response['mode']);
			self::assertIsArray($response['overview']);
			self::assertIsArray($response['manifests']);
			self::assertIsArray($response['registry']);
		}
	}

	public function testEmptyGridUsesRequestedPaginationBoundaries(): void {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn(['mode' => 'grid', 'gridView' => 'runs', 'page' => 0, 'pageSize' => 999]);
		$response = json_decode($this->display(DataHawkMaterializationRunDisplay::class, null, null, $request)->getOutput('json'), true, 512, JSON_THROW_ON_ERROR);
		self::assertTrue($response['ok']);
		self::assertSame('grid', $response['mode']);
		self::assertSame(1, $response['page']);
		self::assertSame(250, $response['pageSize']);
		self::assertSame(0, $response['total']);
		self::assertSame([], $response['data']);
	}

	private function displays(): array {
		return [
			[DataHawkMaterializationOverviewDisplay::class, 'overview', 'Shows the DataHawk materialization overview.'],
			[DataHawkMaterializationManifestDisplay::class, 'manifest', 'Shows DataHawk materialization manifests.'],
			[DataHawkMaterializationRegistryDisplay::class, 'registry', 'Shows the DataHawk materialization registry.'],
			[DataHawkMaterializationRunDisplay::class, 'run', 'Shows DataHawk materialization runs.'],
			[DataHawkMaterializationTableDisplay::class, 'table', 'Shows DataHawk materialization tables.']
		];
	}

	private function display(string $class, ?IAuthorizationService $authorization = null, ?IMvcView $view = null, ?IRequest $request = null): object {
		if($authorization === null) {
			$authorization = $this->createStub(IAuthorizationService::class);
			$authorization->method('getDefaultAuthority')->willReturn('host');
			$authorization->method('decide')->willReturn(AuthorizationDecision::allow());
		}
		if($view === null) {
			$view = $this->createStub(IMvcView::class);
			$view->method('getBricks')->willReturn([]);
		}
		$request ??= $this->createStub(IRequest::class);
		$assets = $this->createStub(IAssetResolver::class);
		$assets->method('resolve')->willReturn('/asset');
		$links = $this->createStub(ILinkTargetService::class);
		$links->method('getLink')->willReturn('/datahawk/json');
		$scopes = $this->createStub(IReportingScopeRegistry::class);
		$scopes->method('getScopes')->willReturn([]);

		return new $class(
			$this->createStub(IContainer::class), $request, $view, $assets, $links,
			$this->createStub(IDatabase::class), $scopes, $authorization
		);
	}
}
