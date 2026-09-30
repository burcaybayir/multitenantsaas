<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Actions\Tenancy\RegisterTenant;
use App\Data\TenantAdmin;
use App\Data\TenantRegistration;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;

/**
 * Creates fully provisioned tenants (real databases, real MySQL users) and
 * tears them down again after each test.
 */
trait InteractsWithTenants
{
    public const ADMIN_PASSWORD = 'correct-horse-battery-9';

    protected function setUpInteractsWithTenants(): void
    {
        foreach (['default', 'cache', 'queue'] as $connection) {
            Redis::connection($connection)->flushdb();
        }
    }

    protected function tearDownInteractsWithTenants(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // Fires TenantDeleted -> DROP DATABASE + DROP USER.
        Tenant::query()->get()->each(function (Tenant $tenant): void {
            $tenant->delete();
            File::deleteDirectory(storage_path('tenants/'.$tenant->getTenantKey()));
        });
    }

    /**
     * Registers a tenant; with the sync queue driver this also provisions it.
     */
    protected function createTenant(string $subdomain, ?string $adminEmail = null): Tenant
    {
        $tenant = app(RegisterTenant::class)->handle(new TenantRegistration(
            companyName: ucfirst($subdomain).' Solar Ltd',
            subdomain: $subdomain,
            admin: new TenantAdmin(
                name: 'Admin '.ucfirst($subdomain),
                email: $adminEmail ?? "admin@{$subdomain}.test",
                passwordHash: Hash::make(self::ADMIN_PASSWORD),
            ),
        ));

        return $tenant->refresh();
    }

    protected function tenantUrl(Tenant $tenant, string $path = '/'): string
    {
        $subdomain = $tenant->domains()->value('domain');

        return "http://{$subdomain}.".config('tenancy.central_domains')[0].$path;
    }

    protected function centralUrl(string $path = '/'): string
    {
        return 'http://'.config('tenancy.central_domains')[0].$path;
    }

    /**
     * Logs in through the real endpoint and returns the session cookie value.
     */
    protected function loginTo(Tenant $tenant, ?string $email = null, string $password = self::ADMIN_PASSWORD): string
    {
        $email ??= "admin@{$tenant->domains()->value('domain')}.test";

        $response = $this->freshRequest()
            ->postJson($this->tenantUrl($tenant, '/login'), ['email' => $email, 'password' => $password])
            ->assertOk();

        return (string) $response->getCookie(config('session.cookie'))?->getValue();
    }

    /**
     * Sends subsequent JSON requests with the given session cookie, exactly
     * as a browser would.
     */
    protected function withSessionCookie(string $sessionId): static
    {
        return $this->freshRequest()
            ->withCredentials()
            ->withCookie(config('session.cookie'), $sessionId);
    }
}
