<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Exceptions;

use RuntimeException;

/**
 * Base class for everything this package throws, so callers can catch one
 * type. isRetryable() tells a queued job whether to release (try later) or
 * fail permanently.
 */
abstract class ClientException extends RuntimeException
{
    public function isRetryable(): bool
    {
        return false;
    }
}
