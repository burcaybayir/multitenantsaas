<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use InstallHub\EpcClient\Contracts\EpcRegister;
use InstallHub\EpcClient\Contracts\PostcodeLookup;
use InstallHub\EpcClient\Exceptions\MissingCredentials;
use InstallHub\EpcClient\Http\EpcConfig;
use InstallHub\EpcClient\Http\RetryPolicy;

/*
 * The package has its own test suite; these tests only prove the host app
 * wires it correctly: auto-discovery, config/env, and Http::fake() reaching
 * the package's clients.
 */

beforeEach(function () {
    Sleep::fake();
    config([
        'epc-client.epc.base_url' => 'https://epc.example.test/api/v1',
        'epc-client.epc.email' => 'ops@installhub.test',
        'epc-client.epc.key' => 'from-config',
        'epc-client.postcodes.base_url' => 'https://postcodes.example.test',
    ]);
});

it('resolves both clients from the container', function () {
    expect(app(EpcRegister::class))->toBeInstanceOf(EpcRegister::class)
        ->and(app(PostcodeLookup::class))->toBeInstanceOf(PostcodeLookup::class);
});

it('uses the configured base URL and credentials', function () {
    Http::fake(['epc.example.test/*' => Http::response('', 200)]);

    app(EpcRegister::class)->searchByPostcode('BS1 4DJ');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://epc.example.test/api/v1/domestic/search')
        && $request->header('Authorization')[0] === 'Basic '.base64_encode('ops@installhub.test:from-config'));
});

it('is intercepted by Http::fake in application tests', function () {
    Http::fake(['postcodes.example.test/*' => Http::response([
        'status' => 200,
        'result' => ['postcode' => 'BS1 4DJ', 'latitude' => 51.45, 'longitude' => -2.59, 'country' => 'England'],
    ])]);

    expect(app(PostcodeLookup::class)->lookup('BS1 4DJ')?->latitude)->toBe(51.45);
});

it('fails loudly when EPC credentials are missing', function () {
    config(['epc-client.epc.key' => null]);
    app()->forgetInstance(EpcConfig::class);

    expect(fn () => app(EpcRegister::class)->searchByPostcode('BS1 4DJ'))->toThrow(MissingCredentials::class);
});

it('reads retry settings from config', function () {
    config(['epc-client.retry.max_attempts' => 5]);
    app()->forgetInstance(RetryPolicy::class);

    expect(app(RetryPolicy::class)->maxAttempts)->toBe(5);
});
