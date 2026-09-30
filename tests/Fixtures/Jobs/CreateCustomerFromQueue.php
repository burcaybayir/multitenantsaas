<?php

declare(strict_types=1);

namespace Tests\Fixtures\Jobs;

use App\Models\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A job with no tenant awareness of its own: it just uses the default
 * connection. Which database it writes to is decided purely by the tenant_id
 * that QueueTenancyBootstrapper stamps onto the payload at dispatch time.
 */
final class CreateCustomerFromQueue implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $email) {}

    public function handle(): void
    {
        Customer::query()->create(['name' => 'Queued', 'email' => $this->email]);
    }
}
