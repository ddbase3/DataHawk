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

		$data = ['data' => [], 'foreignKeys' => []];
		$schema = $this->reportschemaprovider->getSchema();

		foreach ($schema as $table) {

			// only git tables
			if (substr($table->name, 0, 3) != 'git') continue;

			$fields = [];
			$primaryKeys = [];
			foreach ($table->fields as $field) {
				if ($field->primaryKey) $primaryKeys[] = $field->name;
				$fields[] = [
					'name' => $field->name,
					'type' => $field->type
				];
			}
			$data['data'][] = [
				'id' => $table->name,
				'name' => $table->name,
				'fields' => $fields,
				'primaryKeys' => $primaryKeys,
				'position' => array_merge(['x' => 100, 'y' => 100], $table->position ?? [])
			];

			foreach ($table->joins as $join) {
				foreach ($join->on as $from => $to) {
					$fromParts = explode('.', $from);
					$toParts = explode('.', $to);
					$data['foreignKeys'][] = [
						'from' => [
							'tableId' => $fromParts[0],
							'tableName' => $fromParts[0],
							'fieldName' => $fromParts[1]
						],
						'to' => [
							'tableId' => $toParts[0],
							'tableName' => $toParts[0],
							'fieldName' => $toParts[1]
						]
					];
				}
			}
		}

		$this->view->setPath(DIR_PLUGIN . 'DataHawk');
		$this->view->setTemplate('Content/DataHawkSchemaPageModule.php');
		$this->view->assign('data', $data);
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
