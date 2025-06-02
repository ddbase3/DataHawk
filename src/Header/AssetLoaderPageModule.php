<?php declare(strict_types=1);

namespace DataHawk\Header;

use ModuledPage\Page\AbstractModuleHeader;

class AssetLoaderPageModule extends AbstractModuleHeader {

	public static function getName(): string {
		return "assetloaderpagemodule";
	}

	public function getHtml() {
		$elems = [];
		$elems[] = '<script src="plugin/DataHawk/assets/assetloader/assetloader.min.js"></script>';
		return implode("\n", $elems);
	}

	public function getPriority() {
		return 50;
	}
}
