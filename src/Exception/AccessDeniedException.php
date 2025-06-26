<?php declare(strict_types=1);

namespace DataHawk\Exception;

use RuntimeException;

/**
 * Exception thrown when the current user tries to access
 * a table or data area without sufficient permission.
 */
class AccessDeniedException extends RuntimeException
{
}

