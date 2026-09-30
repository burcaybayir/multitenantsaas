<?php

declare(strict_types=1);

return [
    /*
     * EPC Open Data Communities API.
     * Register at https://epc.opendatacommunities.org to get an API key.
     * Authentication is HTTP Basic with your registered email + key.
     */
    'epc' => [
        'base_url' => env('EPC_API_BASE_URL', 'https://epc.opendatacommunities.org/api/v1'),
        'email' => env('EPC_API_EMAIL'),
        'key' => env('EPC_API_KEY'),
        'timeout' => (float) env('EPC_API_TIMEOUT', 10),
    ],

    /*
     * postcodes.io: free, no authentication.
     */
    'postcodes' => [
        'base_url' => env('POSTCODES_IO_BASE_URL', 'https://api.postcodes.io'),
        'timeout' => (float) env('POSTCODES_IO_TIMEOUT', 5),
    ],

    'connect_timeout' => (float) env('EPC_CLIENT_CONNECT_TIMEOUT', 3),

    /*
     * Retries apply to connection failures, 429 and 5xx responses only.
     * A 429 whose Retry-After exceeds max_retry_after_seconds is NOT waited
     * out in-process: RateLimitExceeded is thrown with the delay, so a queued
     * job can release itself instead of blocking a worker.
     */
    'retry' => [
        'max_attempts' => (int) env('EPC_CLIENT_MAX_ATTEMPTS', 3),
        'base_delay_ms' => (int) env('EPC_CLIENT_BASE_DELAY_MS', 200),
        'max_delay_ms' => (int) env('EPC_CLIENT_MAX_DELAY_MS', 5000),
        'max_retry_after_seconds' => (int) env('EPC_CLIENT_MAX_RETRY_AFTER', 10),
    ],
];
