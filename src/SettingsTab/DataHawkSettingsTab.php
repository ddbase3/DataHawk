<?php declare(strict_types=1);

namespace DataHawk\SettingsTab;

use UiFoundation\Settings\AbstractSettingsTab;

final class DataHawkSettingsTab extends AbstractSettingsTab {

	public function getOrder(): int {
		return 100;
	}
}
