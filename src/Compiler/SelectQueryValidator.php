<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of DataHawk for BASE3 Framework.
 *
 * DataHawk extends the BASE3 framework with a schema-driven query
 * engine for reporting and data access. Queries are defined as
 * structured JSON arrays, compiled into SQL, and executed through
 * the BASE3 IDatabase abstraction.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/datahawk
 * https://github.com/ddbase3/DataHawk
 **********************************************************************/

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryValidator;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Validates 'select' queries, including standard and UNION queries.
 */
class SelectQueryValidator implements IReportQueryValidator {

	public function validate(array $query): void {
		if (($query['type'] ?? null) !== 'select') {
			throw new QueryValidationException("Unsupported query type: " . ($query['type'] ?? '[not defined]'));
		}

		// Handle UNION queries
		if (isset($query['union'])) {
			$this->validateUnionBlock($query['union']);
			return; // no further validation required for root-level fields
		}

		// Standard SELECT validation
		if (empty($query['fields']) || !is_array($query['fields'])) {
			throw new QueryValidationException("SELECT query must contain a non-empty 'fields' array.");
		}

		foreach ($query['order_by'] ?? [] as $order) {
			if (!isset($order['element'])) {
				throw new QueryValidationException("Missing element in order_by clause.");
			}

			$dir = strtoupper($order['direction'] ?? 'ASC');
			if (!in_array($dir, ['ASC', 'DESC'], true)) {
				throw new QueryValidationException("Invalid order direction: $dir");
			}
		}
	}

	private function validateUnionBlock(array $union): void {
		if (!array_key_exists('queries', $union) || !is_array($union['queries']) || count($union['queries']) < 2) {
			throw new QueryValidationException("UNION must contain a 'queries' array with at least two SELECTs.");
		}

		foreach ($union['queries'] as $i => $subQuery) {
			if (!is_array($subQuery)) {
				throw new QueryValidationException("UNION subquery #$i must be a valid SELECT query.");
			}
			if (($subQuery['type'] ?? null) !== 'select') {
				throw new QueryValidationException("Each UNION subquery must be of type 'select'.");
			}

			// Recursive validation for each subquery (basic)
			if (empty($subQuery['fields']) || !is_array($subQuery['fields'])) {
				throw new QueryValidationException("UNION subquery #$i must contain a non-empty 'fields' array.");
			}
		}
	}
}

