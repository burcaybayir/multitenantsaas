<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Exceptions;

/**
 * The upstream is throttling us and asked us to wait longer than we're
 * willing to block for. Queue jobs should release($retryAfterSeconds).
 */
final class RateLimitExceeded extends ClientException
{
    public function __construct(
        string $message,
        public readonly ?int $retryAfterSeconds,
    ) {
        parent::__construct($message);
    }

    public static function for(string $service, ?int $retryAfterSeconds): self
    {
        return new self(
            sprintf('%s rate limit exceeded%s.', $service, $retryAfterSeconds !== null ? ", retry after {$retryAfterSeconds}s" : ''),
            $retryAfterSeconds,
        );
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
