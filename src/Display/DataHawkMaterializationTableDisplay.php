<?php declare(strict_types=1);

namespace DataHawk\Display;

use Base3\Api\IDisplay;
use Base3\Api\IMvcView;

final class DataHawkMaterializationTableDisplay implements IDisplay {

        public function __construct(
                private readonly IMvcView $view
        ) {}

        public static function getName(): string {
                return 'datahawkmaterializationtabledisplay';
        }

        public function setData($data) {
                // no-op
        }

        public function getOutput(string $out = 'html', bool $final = false): string {
                $this->view->setPath(DIR_PLUGIN . 'DataHawk');
                $this->view->setTemplate('Display/MaterializationPlaceholderDisplay.php');
                $this->view->assign('title', 'Materialization tables');
                $this->view->assign('message', 'Materialization table administration will be added here.');

                return $this->view->loadTemplate();
        }

        public function getHelp(): string {
                return 'Shows DataHawk materialization tables.';
        }
}
