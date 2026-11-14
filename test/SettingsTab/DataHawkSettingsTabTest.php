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

use Base3\DataHawk\SettingsTab\DataHawkSettingsTab;
use PHPUnit\Framework\TestCase;
use Base3\CoreFoundation\Translation\Api\ITranslation;

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
