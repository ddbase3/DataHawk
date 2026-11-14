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

namespace Base3\DataHawk\Test\SettingsTab;

use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationDecision;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\CoreFoundation\Usermanager\Permission;
use Base3\DataHawk\SettingsTab\DatahawkmaterializationmanifestSettingsTab;
use Base3\DataHawk\SettingsTab\DatahawkmaterializationoverviewSettingsTab;
use Base3\DataHawk\SettingsTab\DatahawkmaterializationregistrySettingsTab;
use Base3\DataHawk\SettingsTab\DatahawkmaterializationrunSettingsTab;
use Base3\DataHawk\SettingsTab\DatahawkmaterializationtableSettingsTab;
use PHPUnit\Framework\TestCase;

final class MaterializationSettingsTabsTest extends TestCase {

	public function testAllMaterializationTabsDeclareCorrectDisplayAndOrder(): void {
		$expected = [
			DatahawkmaterializationoverviewSettingsTab::class => ['overview', 20],
			DatahawkmaterializationmanifestSettingsTab::class => ['manifest', 30],
			DatahawkmaterializationregistrySettingsTab::class => ['registry', 40],
			DatahawkmaterializationrunSettingsTab::class => ['run', 50],
			DatahawkmaterializationtableSettingsTab::class => ['table', 60]
		];

		foreach($expected as $class => [$suffix, $order]) {
			$tab = new $class($this->createStub(ITranslation::class), $this->createStub(IAuthorizationService::class));
			self::assertSame('datahawksettingstab', $tab->getParentTabName());
			self::assertSame('datahawkmaterialization' . $suffix . 'display', $tab->getDisplayName());
			self::assertSame($order, $tab->getOrder());
			self::assertSame('datahawkmaterialization' . $suffix . 'settingstab', $tab::getName());
			self::assertSame(['group' => 'administration'], $tab->getDefaultAudience());
			self::assertCount(1, $tab->getPermissions());
			self::assertInstanceOf(Permission::class, $tab->getPermissions()[0]);
		}
	}

	public function testExplicitAllowMakesFeatureTabVisible(): void {
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host');
		$authorization->method('decide')->willReturn(AuthorizationDecision::allow());

		self::assertTrue((new DatahawkmaterializationoverviewSettingsTab($this->createStub(ITranslation::class), $authorization))->isVisible());
	}

	public function testExplicitDenyAndIndeterminateNeverMakeFeatureTabVisible(): void {
		foreach([AuthorizationDecision::deny(), AuthorizationDecision::indeterminate()] as $decision) {
			$authorization = $this->createStub(IAuthorizationService::class);
			$authorization->method('getDefaultAuthority')->willReturn('host');
			$authorization->method('decide')->willReturn($decision);
			self::assertFalse((new DatahawkmaterializationoverviewSettingsTab($this->createStub(ITranslation::class), $authorization))->isVisible());
		}
	}

	public function testAuthorizationExceptionFailsClosed(): void {
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('host');
		$authorization->method('decide')->willThrowException(new \RuntimeException('authority unavailable'));

		self::assertFalse((new DatahawkmaterializationoverviewSettingsTab($this->createStub(ITranslation::class), $authorization))->isVisible());
	}
}
