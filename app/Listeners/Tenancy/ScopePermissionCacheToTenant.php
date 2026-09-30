<?php

declare(strict_types=1);

namespace App\Listeners\Tenancy;

use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\RevertedToCentralContext;
use Stancl\Tenancy\Events\TenancyBootstrapped;

/**
 * spatie/laravel-permission caches every role/permission under ONE global key
 * and also keeps them in memory on a singleton. With a database per tenant,
 * that means tenant B could be served tenant A's roles from cache, or from
 * memory inside a long-running queue worker. Give each tenant its own key and
 * flush the in-memory copy on every context switch.
 */
final class ScopePermissionCacheToTenant
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function handle(TenancyBootstrapped|RevertedToCentralContext $event): void
    {
        // Resets the cache key from config and drops in-memory permissions.
        $this->registrar->initializeCache();

        if ($event instanceof TenancyBootstrapped) {
            $this->registrar->cacheKey .= '.tenant.'.$event->tenancy->tenant?->getTenantKey();
        }
    }
}
