<?php declare(strict_types=1);

namespace DataHawk\Header;

use Base3\Api\IAssetResolver;
use ModuledPage\Page\AbstractModuleHeader;

class AssetLoaderPageModule extends AbstractModuleHeader {

	public function __construct(private readonly IAssetResolver $assetresolver) {}

	public static function getName(): string {
		return "assetloaderpagemodule";
	}

	public function getHtml() {
		$elems = [];
		// $elems[] = '<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>';
		$elems[] = '<script src="' . $this->assetresolver->resolve('plugin/DataHawk/assets/assetloader/assetloader.min.js') . '"></script>';
		return implode("\n", $elems);
	}

	public function getPriority() {
		return 50;
	}
}
