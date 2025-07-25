<?php declare(strict_types=1);

namespace DataHawk\Dto;

/**
 * Represents a compiled SQL query including raw SQL,
 * bound parameters, selected field metadata, and sensitivity flag.
 */
class SqlQuery {

	 /**
	 * @param string $sql            The compiled SQL statement.
	 * @param array $params          Bound parameters for the SQL query.
	 * @param array $fields          Metadata for selected fields (name, alias, table, etc.).
	 * @param bool $sensitive        True if query touches sensitive data.
	 */
	public function __construct(
		public string $sql,
		public array $params = [],
		public array $fields = [],
		public bool $sensitive = false
	) {}
}

