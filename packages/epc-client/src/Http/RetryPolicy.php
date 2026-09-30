<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Http;

use Closure;
use DateTimeImmutable;
use Illuminate\Http\Client\Response;

/**
 * Decides whether and how long to wait before retrying.
 *
 * - Retries only what can succeed on a second try: connection errors, 429,
 *   and 5xx. A 400/401/404 will fail the same way every time.
 * - Exponential backoff with "full jitter" (random delay in [0, cap]), so
 *   many workers failing at once don't retry in lock-step and hammer the API.
 * - A 429's Retry-After is honoured, but only up to maxRetryAfterSeconds:
 *   beyond that, blocking a worker is worse than failing fast and letting the
 *   queue reschedule the job.
 */
final readonly class RetryPolicy
{
    /** @var Closure(int, int): int */
    private Closure $random;

    /**
     * @param  (Closure(int, int): int)|null  $random  injectable for deterministic tests
     */
    public function __construct(
        public int $maxAttempts = 3,
        public int $baseDelayMs = 200,
        public int $maxDelayMs = 5000,
        public int $maxRetryAfterSeconds = 10,
        ?Closure $random = null,
    ) {
        $this->random = $random ?? static fn (int $min, int $max): int => random_int($min, $max);
    }

    public function canRetryAfter(int $attempt): bool
    {
        return $attempt < $this->maxAttempts;
    }

    public function isRetryable(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    /**
     * Milliseconds to wait before the next attempt, or null if we should
     * give up now (Retry-After longer than we're willing to wait).
     */
    public function delayMs(int $attempt, ?Response $response = null): ?int
    {
        if ($response?->status() === 429) {
            $retryAfter = self::retryAfterSeconds($response);

            if ($retryAfter !== null) {
                return $retryAfter <= $this->maxRetryAfterSeconds ? $retryAfter * 1000 : null;
            }
        }

        $cap = (int) min($this->maxDelayMs, $this->baseDelayMs * 2 ** ($attempt - 1));

        return ($this->random)(0, $cap);
    }

    /**
     * Retry-After is either delay-seconds or an HTTP-date (RFC 9110 §10.2.3).
     */
    public static function retryAfterSeconds(Response $response): ?int
    {
        $header = trim($response->header('Retry-After'));

        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            return (int) $header;
        }

        $date = DateTimeImmutable::createFromFormat(DATE_RFC7231, $header);

        return $date === false ? null : max(0, $date->getTimestamp() - time());
    }
}
