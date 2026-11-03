<?php declare(strict_types=1);

namespace DataHawk\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class DatahawkmaterializationrunSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'datahawksettingstab';
	}

	public function getDisplayName(): ?string {
		return 'datahawkmaterializationrundisplay';
	}

	public function getOrder(): int {
		return 50;
	}
}
