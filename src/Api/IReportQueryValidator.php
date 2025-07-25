<?php declare(strict_types=1);

namespace DataHawk\Api;

/**
 * Interface IReportQueryValidator
 *
 * Defines the contract for validating structured report queries
 * before they are compiled into SQL.
 */
interface IReportQueryValidator {

	/**
	 * Validates a report query represented as an associative array.
	 *
	 * Implementations should check for structural and semantic correctness
	 * of the query, such as required keys, supported operations,
	 * and consistency of fields and tables.
	 *
	 * @param array $query The query structure to validate
	 *
	 * @throws \DataHawk\Exception\QueryValidationException If validation fails
	 */
	public function validate(array $query): void;
}

