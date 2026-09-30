<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use InstallHub\EpcClient\Http\RetryPolicy;

function response(int $status, array $headers = []): Response
{
    return new Response(new Psr7Response($status, $headers));
}

it('only retries responses that can succeed on a second attempt', function (int $status, bool $retryable) {
    expect(retryPolicy()->isRetryable(response($status)))->toBe($retryable);
})->with([
    [429, true], [500, true], [502, true], [503, true],
    [400, false], [401, false], [403, false], [404, false], [422, false],
]);

it('backs off exponentially up to the cap', function () {
    $policy = retryPolicy(); // base 100ms, cap 1000ms, jitter = upper bound

    expect(array_map($policy->delayMs(...), [1, 2, 3, 4, 5, 6]))
        ->toBe([100, 200, 400, 800, 1000, 1000]);
});

it('applies full jitter within the backoff window', function () {
    $windows = [];
    $policy = new RetryPolicy(baseDelayMs: 100, maxDelayMs: 1000, random: function (int $min, int $max) use (&$windows) {
        $windows[] = [$min, $max];

        return 42;
    });

    expect($policy->delayMs(3))->toBe(42)
        ->and($windows)->toBe([[0, 400]]);
});

it('honours Retry-After in seconds', function () {
    expect(retryPolicy()->delayMs(1, response(429, ['Retry-After' => '3'])))->toBe(3000);
});

it('honours Retry-After as an HTTP date', function () {
    $in5s = gmdate('D, d M Y H:i:s \G\M\T', time() + 5);

    expect(retryPolicy()->delayMs(1, response(429, ['Retry-After' => $in5s])))->toBeBetween(4000, 5000);
});

it('refuses to wait out a Retry-After longer than the configured maximum', function () {
    expect(retryPolicy(maxRetryAfterSeconds: 10)->delayMs(1, response(429, ['Retry-After' => '120'])))->toBeNull();
});

it('falls back to backoff when a 429 has no usable Retry-After', function (array $headers) {
    expect(retryPolicy()->delayMs(2, response(429, $headers)))->toBe(200);
})->with([
    'missing' => [[]],
    'garbage' => [['Retry-After' => 'soon']],
]);

it('stops after the maximum number of attempts', function () {
    $policy = retryPolicy(maxAttempts: 3);

    expect($policy->canRetryAfter(1))->toBeTrue()
        ->and($policy->canRetryAfter(2))->toBeTrue()
        ->and($policy->canRetryAfter(3))->toBeFalse();
});
