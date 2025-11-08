<?php declare(strict_types=1);

namespace DataHawk\Compiler;

use DataHawk\Api\IReportQueryValidator;
use ResourceFoundation\Exception\QueryValidationException;

/**
 * Validator for ALTER TABLE queries.
 */
class AlterQueryValidator implements IReportQueryValidator {

	public function validate(array $query): void {
		if (($query['type'] ?? null) !== 'alter') {
			throw new QueryValidationException("Invalid query type for ALTER.");
		}

		if (empty($query['table']) || !is_string($query['table'])) {
			throw new QueryValidationException("ALTER query must include a 'table' name.");
		}

		if (empty($query['actions']) || !is_array($query['actions'])) {
			throw new QueryValidationException("ALTER query must include a non-empty 'actions' array.");
		}

		foreach ($query['actions'] as $i => $action) {
			if (!is_array($action) || empty($action['action'])) {
				throw new QueryValidationException("Action #$i must contain an 'action' field.");
			}

			$act = strtolower($action['action']);

			switch ($act) {
				case 'add_column':
				case 'modify_column':
					if (empty($action['name']) || empty($action['type'])) {
						throw new QueryValidationException("Action #$i ($act) requires 'name' and 'type'.");
					}
					break;

				case 'drop_column':
					if (empty($action['name'])) {
						throw new QueryValidationException("Action #$i ($act) requires 'name'.");
					}
					break;

				case 'rename_column':
					if (empty($action['from']) || empty($action['to'])) {
						throw new QueryValidationException("Action #$i ($act) requires 'from' and 'to'.");
					}
					break;

				case 'change_column':
					if (empty($action['from']) || empty($action['to']) || empty($action['type'])) {
						throw new QueryValidationException("Action #$i ($act) requires 'from', 'to', and 'type'.");
					}
					break;

				default:
					throw new QueryValidationException("Unsupported ALTER action: '{$action['action']}' in action #$i.");
			}
		}
	}
}

