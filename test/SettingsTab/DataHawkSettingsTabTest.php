<?php declare(strict_types=1);

namespace DataHawk\Test\SettingsTab;

use DataHawk\SettingsTab\DataHawkSettingsTab;
use PHPUnit\Framework\TestCase;
use Base3\Translation\Api\ITranslation;

final class DataHawkSettingsTabTest extends TestCase {

	public function testRootSettingsTabKeepsStableIdentityAndNavigation(): void {
		$translation = $this->createStub(ITranslation::class);
		$translation->method('translate')->willReturn('Settings');
		$tab = new DataHawkSettingsTab($translation);

		self::assertSame('datahawksettingstab', $tab::getName());
		self::assertSame(100, $tab->getOrder());
		self::assertNull($tab->getParentTabName());
		self::assertNull($tab->getDisplayName());
		self::assertNull($tab->getDisplayData());
		self::assertTrue($tab->isVisible());
		self::assertSame('Settings', $tab->getLabel());
		self::assertSame('Settings', $tab->getDescription());
	}
}
