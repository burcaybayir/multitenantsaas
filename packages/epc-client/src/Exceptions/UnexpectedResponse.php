<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Exceptions;

/**
 * A response we can't use: an unexpected 4xx, or a body that doesn't have
 * the documented shape. Retrying won't help.
 */
final class UnexpectedResponse extends ClientException
{
    public static function status(string $service, int $status): self
    {
        return new self(sprintf('%s returned an unexpected HTTP %d.', $service, $status));
    }

    public static function malformed(string $service, string $reason): self
    {
        return new self(sprintf('%s returned a malformed response: %s', $service, $reason));
    }
}
