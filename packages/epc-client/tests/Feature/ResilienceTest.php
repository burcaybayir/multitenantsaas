<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Sleep;
use InstallHub\EpcClient\Exceptions\AuthenticationFailed;
use InstallHub\EpcClient\Exceptions\ClientException;
use InstallHub\EpcClient\Exceptions\RateLimitExceeded;
use InstallHub\EpcClient\Exceptions\ServiceUnavailable;
use InstallHub\EpcClient\Exceptions\UnexpectedResponse;
use InstallHub\EpcClient\Http\EpcConfig;

beforeEach(fn () => $this->http = new Factory);

it('retries server errors with exponential backoff, then succeeds', function () {
    $this->http->fake(['epc.test/*' => $this->http->sequence()
        ->pushStatus(503)
        ->pushStatus(502)
        ->push(fixtureBody('epc-search-postcode')),
    ]);

    $result = epcRegister($this->http)->searchByPostcode('BS1 4DJ');

    expect($result)->toHaveCount(3);
    $this->http->assertSentCount(3);
    Sleep::assertSequence([Sleep::for(100)->milliseconds(), Sleep::for(200)->milliseconds()]);
});

it('gives up after the maximum attempts and reports a retryable failure', function () {
    $this->http->fake(['epc.test/*' => $this->http->response('', 500)]);

    try {
        epcRegister($this->http, retryPolicy(maxAttempts: 3))->searchByPostcode('BS1 4DJ');
        $this->fail('Expected ServiceUnavailable');
    } catch (ServiceUnavailable $e) {
        expect($e->isRetryable())->toBeTrue();
    }

    $this->http->assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

it('retries connection failures', function () {
    $this->http->fake(['postcodes.test/*' => $this->http->sequence()
        ->pushFailedConnection()
        ->push(fixtureBody('postcode-found')),
    ]);

    expect(postcodeLookup($this->http)->lookup('BS1 4DJ'))->not->toBeNull();
    Sleep::assertSleptTimes(1);
});

it('wraps persistent connection failures', function () {
    $this->http->fake(['postcodes.test/*' => $this->http->failedConnection()]);

    expect(fn () => postcodeLookup($this->http)->lookup('BS1 4DJ'))->toThrow(ServiceUnavailable::class);
});

it('waits out a short Retry-After on 429', function () {
    $this->http->fake(['epc.test/*' => $this->http->sequence()
        ->push('', 429, ['Retry-After' => '2'])
        ->push(fixtureBody('epc-search-postcode')),
    ]);

    expect(epcRegister($this->http)->searchByPostcode('BS1 4DJ'))->toHaveCount(3);
    Sleep::assertSequence([Sleep::for(2)->seconds()]);
});

it('fails fast on a long Retry-After instead of blocking the worker', function () {
    $this->http->fake(['epc.test/*' => $this->http->response('', 429, ['Retry-After' => '300'])]);

    try {
        epcRegister($this->http)->searchByPostcode('BS1 4DJ');
        $this->fail('Expected RateLimitExceeded');
    } catch (RateLimitExceeded $e) {
        expect($e->retryAfterSeconds)->toBe(300)
            ->and($e->isRetryable())->toBeTrue();
    }

    $this->http->assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('never retries client errors', function (int $status, string $exception) {
    $this->http->fake(['epc.test/*' => $this->http->response('', $status)]);

    expect(fn () => epcRegister($this->http)->searchByPostcode('BS1 4DJ'))->toThrow($exception);

    $this->http->assertSentCount(1);
    Sleep::assertNeverSlept();
})->with([
    'bad request' => [400, UnexpectedResponse::class],
    'unauthorised' => [401, AuthenticationFailed::class],
    'forbidden' => [403, AuthenticationFailed::class],
]);

it('marks permanent failures as not retryable', function () {
    $this->http->fake(['epc.test/*' => $this->http->response('', 401)]);

    try {
        epcRegister($this->http)->searchByPostcode('BS1 4DJ');
    } catch (ClientException $e) {
        expect($e->isRetryable())->toBeFalse();
    }
});

it('never leaks the API key in exception messages or dumps', function () {
    $this->http->fake(['epc.test/*' => $this->http->response('', 401)]);

    try {
        epcRegister($this->http, key: 'super-secret-key')->searchByPostcode('BS1 4DJ');
    } catch (AuthenticationFailed $e) {
        expect($e->getMessage())->not->toContain('super-secret-key');
    }

    $config = new EpcConfig(EPC_BASE, 'dev@installhub.test', 'super-secret-key');
    expect(print_r($config, true))->not->toContain('super-secret-key');
});
