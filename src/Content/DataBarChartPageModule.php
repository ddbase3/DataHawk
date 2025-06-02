<?php declare(strict_types=1);

namespace DataHawk\Content;

use Base3\Api\IMvcView;
use ModuledPage\Page\AbstractModuleContent;

class DataBarChartPageModule extends AbstractModuleContent {

	public function __construct(private readonly IMvcView $view) {}

	public static function getName(): string {
		return "databarchartpagemodule";
	}

	public function getHtml() {
		$this->view->setPath(DIR_PLUGIN . 'DataHawk');
		$this->view->setTemplate('Content/DataBarChartPageModule.php');
		$defaults = [ 'height' => '20em' ];
		foreach ($defaults as $tag => $default) $this->view->assign($tag, isset($this->data[$tag]) ? $this->data[$tag] : $default);
		return $this->view->loadTemplate();
	}
}
