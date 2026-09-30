<?php

declare(strict_types=1);

namespace App\Listeners\Tenancy;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Session\SessionManager;

/**
 * Session state is cached in two places:
 *  - the SessionManager caches its driver, and the database handler keeps the
 *    connection that was the default when it was created;
 *  - the container caches `session.store` as a singleton, which is what the
 *    auth guard reads the logged-in user id from.
 *
 * In any process that serves more than one tenant (Octane, queue workers,
 * tests) either cache would carry tenant A's session into tenant B, and
 * because both tenants have a user #1, that authenticates as the wrong person
 * (reproduced in HttpIsolationTest). Dropping both on every context switch
 * rules it out.
 */
final class ResetSessionDrivers
{
    public function __construct(
        private readonly Application $app,
        private readonly SessionManager $sessions,
    ) {}

    public function handle(): void
    {
        $this->sessions->forgetDrivers();
        $this->app->forgetInstance('session.store');
    }
}
