<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Within one test the application instance is reused across HTTP calls,
     * so the auth guard would remember the user from the previous request.
     * In production every request starts clean (PHP-FPM process per request),
     * which is what this simulates. Without it, a cross-tenant request could
     * "succeed" in a test purely because of in-memory state.
     */
    protected function freshRequest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }
}
