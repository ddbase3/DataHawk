<?php declare(strict_types=1);

namespace DataHawk\Content;

use Base3\Api\IMvcView;
use Base3\Api\ISchemaProvider;
use Base3\Core\ServiceLocator;
use ModuledPage\Page\AbstractModuleContent;
use DataHawk\Api\IReportSchemaProvider;

class DataHawkSchemaPageModule extends AbstractModuleContent implements ISchemaProvider {

	public function __construct(
		private readonly IMvcView $view,
		private readonly IReportSchemaProvider $reportschemaprovider
	) {}

	// Implementation of IBase

	public static function getName(): string {
		return 'datahawkschemapagemodule';
	}

	// Implementation of IPageModule

	public function getHtml() {

		$schema = $this->reportschemaprovider->getSchema();
return print_r($schema, true);

		$this->view->setPath(DIR_PLUGIN . 'DataHawk');
		$this->view->setTemplate('Content/DataHawkSchemaPageModule.php');
		$defaults = [];
		foreach (array_merge($defaults, $this->data) as $tag => $content) $this->view->assign($tag, $content);
		return $this->view->loadTemplate();
	}

	// Implementation of ISchemaProvider

	public function getSchema(): array {
		$schema = [
			'$schema' => 'https://json-schema.org/draft-2020-12/schema',
			'type' => 'object',
			'properties' => [],
			'required' => [],
		];
		return $schema;
	}
}
