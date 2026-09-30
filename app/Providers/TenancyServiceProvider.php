<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\InitializeTenancyBySubdomain;
use App\Listeners\Tenancy\ResetSessionDrivers;
use App\Listeners\Tenancy\ScopePermissionCacheToTenant;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\DatabaseConfig;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * @return array<class-string, list<mixed>>
     */
    public function events(): array
    {
        return [
            // Provisioning is NOT wired to TenantCreated: RegisterTenant
            // dispatches App\Jobs\ProvisionTenant explicitly, which tracks
            // status, retries idempotently and is unique per tenant.

            Events\TenantDeleted::class => [
                JobPipeline::make([Jobs\DeleteDatabase::class])
                    ->send(fn (Events\TenantDeleted $event) => $event->tenant)
                    ->shouldBeQueued(false),
            ],

            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\TenancyBootstrapped::class => [
                ScopePermissionCacheToTenant::class,
                ResetSessionDrivers::class,
            ],
            Events\RevertedToCentralContext::class => [
                ScopePermissionCacheToTenant::class,
                ResetSessionDrivers::class,
            ],
        ];
    }

    public function register(): void
    {
        // Alphanumeric only: these are interpolated into CREATE USER, which
        // cannot take bound parameters (see IdempotentMySQLDatabaseManager).
        DatabaseConfig::generateUsernamesUsing(
            static fn (): string => 'tu_'.Str::lower(Str::random(20)),
        );
        DatabaseConfig::generatePasswordsUsing(
            static fn (): string => Str::password(48, symbols: false),
        );
    }

    public function boot(): void
    {
        $this->bootEvents();
        $this->mapRoutes();
        $this->configureIdentification();
        $this->makeTenancyMiddlewareHighestPriority();
    }

    protected function bootEvents(): void
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes(): void
    {
        $this->app->booted(function (): void {
            Route::group([], base_path('routes/tenant.php'));
        });
    }

    protected function configureIdentification(): void
    {
        // Unknown subdomain or not-a-tenant host: plain 404, never a 500
        // that would reveal which subdomains exist.
        InitializeTenancyBySubdomain::$onFail = static fn () => abort(404);
    }

    /**
     * Tenancy must be initialized before StartSession (so the session is read
     * from the tenant's database) and before anything that touches the DB.
     */
    protected function makeTenancyMiddlewareHighestPriority(): void
    {
        $tenancyMiddleware = [
            Middleware\PreventAccessFromCentralDomains::class,
            InitializeTenancyBySubdomain::class,
        ];

        /** @var Kernel $kernel */
        $kernel = $this->app->make(HttpKernel::class);

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $kernel->prependToMiddlewarePriority($middleware);
        }
    }
}
