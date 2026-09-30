<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Sleep;
use InstallHub\EpcClient\Http\EpcConfig;
use InstallHub\EpcClient\Http\HttpEpcRegister;
use InstallHub\EpcClient\Http\HttpPostcodeLookup;
use InstallHub\EpcClient\Http\PostcodesConfig;
use InstallHub\EpcClient\Http\ResilientTransport;
use InstallHub\EpcClient\Http\RetryPolicy;

/*
 * The package tests need no Laravel application: the HTTP client factory
 * can be instantiated and faked on its own, and Sleep can be faked
 * statically, so retries with backoff run instantly and are assertable.
 */

uses()
    ->beforeEach(fn () => Sleep::fake())
    ->afterEach(fn () => Sleep::fake(false))
    ->in('Unit', 'Feature');

const EPC_BASE = 'https://epc.test/api/v1';
const POSTCODES_BASE = 'https://postcodes.test';

/**
 * Deterministic jitter: always the upper bound of the backoff window.
 */
function retryPolicy(int $maxAttempts = 3, int $maxRetryAfterSeconds = 10): RetryPolicy
{
    return new RetryPolicy(
        maxAttempts: $maxAttempts,
        baseDelayMs: 100,
        maxDelayMs: 1000,
        maxRetryAfterSeconds: $maxRetryAfterSeconds,
        random: static fn (int $min, int $max): int => $max,
    );
}

function epcRegister(Factory $http, ?RetryPolicy $policy = null, ?string $key = 'secret-key'): HttpEpcRegister
{
    return new HttpEpcRegister(
        $http,
        new EpcConfig(EPC_BASE, 'dev@installhub.test', $key),
        new ResilientTransport($policy ?? retryPolicy()),
    );
}

function postcodeLookup(Factory $http, ?RetryPolicy $policy = null): HttpPostcodeLookup
{
    return new HttpPostcodeLookup($http, new PostcodesConfig(POSTCODES_BASE), new ResilientTransport($policy ?? retryPolicy()));
}

function fixtureBody(string $name): string
{
    return (string) file_get_contents(__DIR__."/Fixtures/{$name}.json");
}

/**
 * @return array<string, mixed>
 */
function fixtureJson(string $name): array
{
    return json_decode(fixtureBody($name), true, flags: JSON_THROW_ON_ERROR);
}
