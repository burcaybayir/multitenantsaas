<?php

use App\Http\Middleware\EnsureSessionBelongsToTenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        using: function (): void {
            // Central routes are bound to the central domain(s), so they are
            // unreachable from tenant subdomains. Tenant routes are registered
            // by TenancyServiceProvider.
            foreach (config('tenancy.central_domains') as $domain) {
                Route::middleware('web')->domain($domain)->group(base_path('routes/web.php'));
                Route::middleware('api')->domain($domain)->prefix('api')->group(base_path('routes/api.php'));
            }
        },
    )
    // Listeners are registered explicitly (tenancy wiring lives in
    // TenancyServiceProvider); auto-discovery would register them twice.
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware): void {
        // Must see the session (after StartSession) but run before the
        // auth guard reads the user id from it.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureSessionBelongsToTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Backend-only app for now: every error is rendered as JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
