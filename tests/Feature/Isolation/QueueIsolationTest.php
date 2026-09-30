<?php

declare(strict_types=1);

use App\Models\Customer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Tests\Fixtures\Jobs\CreateCustomerFromQueue;

beforeEach(function () {
    $this->acme = $this->createTenant('acme');
    $this->bolt = $this->createTenant('bolt');
});

/**
 * Runs a real worker against Redis until the queue is empty, exactly like
 * Horizon would: central context, one process, many tenants' jobs.
 */
function work(string $queue = 'isolation'): void
{
    Artisan::call('queue:work', [
        'connection' => 'redis',
        '--queue' => $queue,
        '--stop-when-empty' => true,
        '--tries' => 1,
        '--sleep' => 0,
    ]);
}

function dispatchFrom($tenant, string $email): void
{
    tenancy()->initialize($tenant);
    CreateCustomerFromQueue::dispatch($email)->onConnection('redis')->onQueue('isolation');
    tenancy()->end();
}

it('runs each job in the tenant that dispatched it', function () {
    dispatchFrom($this->acme, 'from-acme@example.test');
    dispatchFrom($this->bolt, 'from-bolt@example.test');
    dispatchFrom($this->acme, 'from-acme-2@example.test');

    work();

    $this->acme->run(fn () => expect(Customer::orderBy('id')->pluck('email')->all())
        ->toBe(['from-acme@example.test', 'from-acme-2@example.test']));
    $this->bolt->run(fn () => expect(Customer::pluck('email')->all())
        ->toBe(['from-bolt@example.test']));
});

it('stamps the tenant id onto the job payload', function () {
    dispatchFrom($this->acme, 'x@example.test');

    $payload = json_decode(Redis::connection('queue')->lindex('queues:isolation', 0), true);

    expect($payload['tenant_id'])->toBe($this->acme->id);
});

it('does not prefix queue keys per tenant, so one central worker serves all tenants', function () {
    // Regression guard: if the queue connection were tenant-prefixed, the job
    // would sit under tenant_<id>queues:isolation where no worker looks.
    dispatchFrom($this->acme, 'x@example.test');

    expect(Redis::connection('queue')->llen('queues:isolation'))->toBe(1);
});

it('returns the worker to the central context after every job', function () {
    dispatchFrom($this->acme, 'x@example.test');

    work();

    expect(tenancy()->initialized)->toBeFalse()
        ->and(DB::getDefaultConnection())->toBe('mysql');
});
