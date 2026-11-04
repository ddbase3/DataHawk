<?php declare(strict_types=1);

namespace DataHawk\Test\Materialization;

use Base3\Database\Api\IDatabase;

final class DataHawkDatabaseFixture implements IDatabase {

	public bool $allowConnection = true;
	public bool $isConnected = false;
	public int $connectCalls = 0;
	public array $nonQueries = [];
	public array $queries = [];
	public ?array $singleRow = null;
	public array $multiRows = [];
	public array $listRows = [];
	public mixed $scalarValue = null;
	public int|string $insertIdValue = 1;
	public ?\Closure $onMultiQuery = null;
	public ?\Closure $onListQuery = null;
	public ?\Closure $onSingleQuery = null;
	public ?\Closure $onWrite = null;
	private array $returnRows = [];
	private array $returnList = [];

	public function connect(): void {
		$this->connectCalls++;
		if($this->allowConnection) $this->isConnected = true;
	}

	public function connected(): bool { return $this->isConnected; }
	public function disconnect(): void { $this->isConnected = false; }
	public function beginTransaction(): void {}
	public function commit(): void {}
	public function rollback(): void {}

	public function nonQuery(string $query): void {
		$this->nonQueries[] = $query;
		if($this->onWrite !== null) ($this->onWrite)($query);
	}

	public function scalarQuery(string $query): mixed {
		$this->queries[] = $query;
		return $this->scalarValue;
	}

	public function singleQuery(string $query): ?array {
		$this->queries[] = $query;
		return $this->onSingleQuery !== null ? ($this->onSingleQuery)($query) : $this->singleRow;
	}

	public function &listQuery(string $query): array {
		$this->queries[] = $query;
		$this->returnList = $this->onListQuery !== null ? ($this->onListQuery)($query) : $this->listRows;
		return $this->returnList;
	}

	public function &multiQuery(string $query): array {
		$this->queries[] = $query;
		$this->returnRows = $this->onMultiQuery !== null ? ($this->onMultiQuery)($query) : $this->multiRows;
		return $this->returnRows;
	}

	public function affectedRows(): int { return 0; }
	public function insertId(): int|string { return $this->insertIdValue; }
	public function escape(string $str): string { return addslashes($str); }
	public function isError(): bool { return false; }
	public function errorNumber(): int { return 0; }
	public function errorMessage(): string { return ''; }
}
