<?php declare(strict_types=1);

namespace DataHawk\Test\Service;

use Base3\Api\IClassMap;

class FakeClassMap implements IClassMap {

	public mixed $returnValue = null;
	public ?string $lastInterface = null;
	public ?string $lastName = null;

	public function getInstanceByInterfaceName(string $interface, string $name): mixed {
		$this->lastInterface = $interface;
		$this->lastName = $name;
		return $this->returnValue;
	}

	public function instantiate(string $class) {
		return null;
	}

	public function &getInstances(array $criteria = []) {
		$empty = [];
		return $empty;
	}

	public function getPlugins() {
		return [];
	}
}
