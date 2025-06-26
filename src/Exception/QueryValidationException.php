<?php declare(strict_types=1);

namespace DataHawk\Exception;

use InvalidArgumentException;

/**
 * Exception thrown when a provided query is structurally invalid
 * or fails semantic validation (e.g. missing fields, unsupported types).
 */
class QueryValidationException extends InvalidArgumentException
{
}

