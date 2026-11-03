<?php declare(strict_types=1);

namespace DataHawk\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class DatahawkmaterializationoverviewSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'datahawksettingstab';
	}

	public function getDisplayName(): ?string {
		return 'datahawkmaterializationoverviewdisplay';
	}

	public function getOrder(): int {
		return 20;
	}
}
