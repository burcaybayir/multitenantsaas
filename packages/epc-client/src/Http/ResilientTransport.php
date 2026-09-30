<?php

declare(strict_types=1);

namespace InstallHub\EpcClient\Http;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Sleep;
use InstallHub\EpcClient\Exceptions\AuthenticationFailed;
use InstallHub\EpcClient\Exceptions\RateLimitExceeded;
use InstallHub\EpcClient\Exceptions\ServiceUnavailable;
use InstallHub\EpcClient\Exceptions\UnexpectedResponse;

/**
 * Sends a request with retries, then turns any failure into one of the
 * package's typed exceptions. Clients only ever see a successful response
 * (or a 404, which they interpret themselves).
 *
 * An explicit loop rather than PendingRequest::retry(): the rules (honour
 * Retry-After, give up early on long waits, never retry 4xx) are easier to
 * read and to test here than spread across retry()'s callbacks.
 *
 * @internal
 */
final readonly class ResilientTransport
{
    public function __construct(private RetryPolicy $policy) {}

    /**
     * @param  Closure(): Response  $send
     */
    public function send(string $service, Closure $send): Response
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $send();
            } catch (ConnectionException $e) {
                if (! $this->policy->canRetryAfter($attempt)) {
                    throw ServiceUnavailable::connectionFailed($service, $e);
                }

                Sleep::usleep((int) $this->policy->delayMs($attempt) * 1000);

                continue;
            }

            if (! $this->policy->isRetryable($response) || ! $this->policy->canRetryAfter($attempt)) {
                return $this->guard($service, $response);
            }

            $delayMs = $this->policy->delayMs($attempt, $response);

            if ($delayMs === null) {
                return $this->guard($service, $response);
            }

            Sleep::usleep($delayMs * 1000);
        }
    }

    private function guard(string $service, Response $response): Response
    {
        $status = $response->status();

        return match (true) {
            $response->successful(), $status === 404 => $response,
            $status === 401, $status === 403 => throw AuthenticationFailed::for($service, $status),
            $status === 429 => throw RateLimitExceeded::for($service, RetryPolicy::retryAfterSeconds($response)),
            $response->serverError() => throw ServiceUnavailable::status($service, $status),
            default => throw UnexpectedResponse::status($service, $status),
        };
    }
}
