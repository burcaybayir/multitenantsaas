<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Lazy loading, silently discarded attributes and missing attributes
        // throw outside production instead of hiding bugs.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Each registration creates a MySQL database: keep it expensive to abuse.
        RateLimiter::for('tenant-registrations', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);
    }
}
