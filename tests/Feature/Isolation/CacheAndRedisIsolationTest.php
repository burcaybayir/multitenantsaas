<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->acme = $this->createTenant('acme');
    $this->bolt = $this->createTenant('bolt');
});

it('isolates cache entries under the same key', function () {
    $this->acme->run(fn () => Cache::put('dashboard.stats', 'acme-stats', 60));

    $this->bolt->run(function () {
        expect(Cache::get('dashboard.stats'))->toBeNull();
        Cache::put('dashboard.stats', 'bolt-stats', 60);
    });

    $this->acme->run(fn () => expect(Cache::get('dashboard.stats'))->toBe('acme-stats'));
    expect(Cache::get('dashboard.stats'))->toBeNull(); // central
});

it('scopes cache flushes to the current tenant', function () {
    $this->acme->run(fn () => Cache::put('k', 'acme', 60));
    $this->bolt->run(fn () => Cache::flush());

    $this->acme->run(fn () => expect(Cache::get('k'))->toBe('acme'));
});

it('isolates direct Redis access by prefixing keys per tenant', function () {
    $this->acme->run(fn () => Redis::set('counter', 'acme'));

    $this->bolt->run(fn () => expect(Redis::get('counter'))->toBeNull());
    $this->acme->run(fn () => expect(Redis::get('counter'))->toBe('acme'));
    expect(Redis::get('counter'))->toBeNull(); // central
});

it('does not serve one tenant\'s cached roles and permissions to another', function () {
    $registrar = app(PermissionRegistrar::class);

    $this->acme->run(function () use ($registrar) {
        Permission::create(['name' => 'acme.only']);
        // Warm both the cache and the registrar's in-memory copy.
        expect($registrar->getPermissions()->pluck('name'))->toContain('acme.only');
    });

    // Same PermissionRegistrar singleton, same process, different tenant.
    $this->bolt->run(fn () => expect($registrar->getPermissions()->pluck('name'))->not->toContain('acme.only'));

    expect($registrar->cacheKey)->toBe(config('permission.cache.key'));
});
