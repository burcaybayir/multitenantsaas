<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach a real third-party API (EPC, postcodes.io...).
        // An un-faked request throws instead of silently hitting the network.
        Http::preventStrayRequests();
    }

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
