<?php

declare(strict_types=1);

return [
    /*
     * Subdomains a tenant may never claim, either because we use them
     * ourselves or because they would be useful for phishing.
     */
    'reserved_subdomains' => [
        'admin', 'api', 'app', 'assets', 'auth', 'billing', 'cdn', 'dashboard',
        'docs', 'help', 'horizon', 'installhub', 'login', 'mail', 'oauth',
        'reverb', 'static', 'status', 'support', 'www', 'ws',
    ],

    'provisioning' => [
        'queue' => env('TENANT_PROVISIONING_QUEUE', 'default'),
    ],
];
