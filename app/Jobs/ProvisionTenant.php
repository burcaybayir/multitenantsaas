<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\TenantAdmin;
use App\Models\Tenant;
use App\Tenancy\TenantProvisioner;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates the tenant's database + MySQL user, runs tenant migrations and
 * seeders, and creates the first admin user.
 *
 * - ShouldBeEncrypted: the payload holds the admin's email and password hash.
 * - ShouldBeUnique:    a double-submitted registration can't provision twice
 *                      concurrently.
 * - Retries are safe because TenantProvisioner is idempotent.
 */
final class ProvisionTenant implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds. MySQL hiccups are usually over within a minute. */
    public int $timeout = 120;

    public int $uniqueFor = 600;

    /** The tenant was deleted before the job ran: nothing to do. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly TenantAdmin $admin,
    ) {
        $this->onQueue(config('installhub.provisioning.queue'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function uniqueId(): string
    {
        return $this->tenant->getTenantKey();
    }

    public function handle(TenantProvisioner $provisioner): void
    {
        $provisioner->provision($this->tenant, $this->admin);
    }

    /**
     * Called once all retries are exhausted.
     */
    public function failed(?Throwable $exception): void
    {
        $this->tenant->refresh()->markFailed();

        Log::error('Tenant provisioning failed', [
            'tenant_id' => $this->tenant->getTenantKey(),
            'exception' => $exception?->getMessage(),
        ]);
    }
}
