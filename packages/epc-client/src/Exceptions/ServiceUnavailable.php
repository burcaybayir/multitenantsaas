<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Exceptions;

use Throwable;

/**
 * Connection failures and 5xx responses that persisted through all retries.
 */
final class ServiceUnavailable extends ClientException
{
    public static function connectionFailed(string $service, Throwable $previous): self
    {
        return new self(sprintf('Could not connect to %s: %s', $service, $previous->getMessage()), 0, $previous);
    }

    public static function status(string $service, int $status): self
    {
        return new self(sprintf('%s is unavailable (HTTP %d).', $service, $status));
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
