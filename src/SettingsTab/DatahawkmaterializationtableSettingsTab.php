<?php declare(strict_types=1);

namespace DataHawk\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class DatahawkmaterializationtableSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'datahawksettingstab';
	}

	public function getDisplayName(): ?string {
		return 'datahawkmaterializationtabledisplay';
	}

	public function getOrder(): int {
		return 60;
	}
}
