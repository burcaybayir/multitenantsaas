<?php

declare(strict_types=1);

use App\Enums\TenantStatus;
use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

function registration(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Acme Solar Ltd',
        'subdomain' => 'acme',
        'admin_name' => 'Ada Admin',
        'admin_email' => 'ada@acme.test',
        'admin_password' => 'sunny-roof-2026',
        'admin_password_confirmation' => 'sunny-roof-2026',
    ], $overrides);
}

describe('with the queue faked', function () {
    beforeEach(fn () => Queue::fake());

    it('accepts a registration and queues provisioning', function () {
        $response = $this->postJson($this->centralUrl('/api/tenants'), registration());

        $tenant = Tenant::sole();

        $response->assertAccepted()
            ->assertHeader('Location', route('central.tenants.status', $tenant))
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.url', 'http://acme.installhub.localhost');

        expect($tenant->status)->toBe(TenantStatus::Pending)
            ->and($tenant->domains()->sole()->domain)->toBe('acme');

        Queue::assertPushedOn('default', ProvisionTenant::class, fn (ProvisionTenant $job) => $job->tenant->is($tenant));
    });

    it('never puts the plaintext password on the queue', function () {
        $this->postJson($this->centralUrl('/api/tenants'), registration())->assertAccepted();

        Queue::assertPushed(ProvisionTenant::class, function (ProvisionTenant $job) {
            return $job instanceof ShouldBeEncrypted
                && ! str_contains(serialize($job), 'sunny-roof-2026')
                && Hash::check('sunny-roof-2026', $job->admin->passwordHash);
        });
    });

    it('normalises the subdomain', function () {
        $this->postJson($this->centralUrl('/api/tenants'), registration(['subdomain' => '  AcMe ']))
            ->assertAccepted()
            ->assertJsonPath('data.url', 'http://acme.installhub.localhost');
    });

    it('rejects subdomains that are not a single safe DNS label', function (string $subdomain) {
        $this->postJson($this->centralUrl('/api/tenants'), registration(['subdomain' => $subdomain]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subdomain');

        expect(Tenant::count())->toBe(0);
    })->with([
        'too short' => 'ab',
        'too long' => str_repeat('a', 64),
        'dot (nested subdomain)' => 'acme.evil',
        'leading hyphen' => '-acme',
        'trailing hyphen' => 'acme-',
        'underscore' => 'acme_solar',
        'unicode' => 'äcme',
        'reserved' => 'www',
        'reserved api' => 'api',
    ]);

    it('rejects a subdomain that is already taken', function () {
        $this->postJson($this->centralUrl('/api/tenants'), registration())->assertAccepted();

        $this->postJson($this->centralUrl('/api/tenants'), registration(['admin_email' => 'other@x.test']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subdomain');
    });

    it('rejects weak or unconfirmed passwords', function (array $overrides) {
        $this->postJson($this->centralUrl('/api/tenants'), registration($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('admin_password');
    })->with([
        'too short' => [['admin_password' => 'short1', 'admin_password_confirmation' => 'short1']],
        'no digits' => [['admin_password' => 'onlylettershere', 'admin_password_confirmation' => 'onlylettershere']],
        'mismatch' => [['admin_password_confirmation' => 'something-else-1']],
    ]);

    it('rate limits registrations per IP', function () {
        foreach (['one', 'two', 'three'] as $subdomain) {
            $this->postJson($this->centralUrl('/api/tenants'), registration(['subdomain' => "{$subdomain}-co"]))
                ->assertAccepted();
        }

        $this->postJson($this->centralUrl('/api/tenants'), registration(['subdomain' => 'four-co']))
            ->assertTooManyRequests();
    });

    it('reports provisioning status', function () {
        $id = $this->postJson($this->centralUrl('/api/tenants'), registration())->json('data.id');

        $this->getJson($this->centralUrl("/api/tenants/{$id}/status"))
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');
    });

    it('answers 503 on the tenant subdomain until provisioning has finished', function (string $environment) {
        // In `local`, stancl checks the tenant DB exists while bootstrapping,
        // so the gate must run before tenancy is initialized at all.
        app()->detectEnvironment(fn () => $environment);

        $this->postJson($this->centralUrl('/api/tenants'), registration())->assertAccepted();

        $this->getJson('http://acme.installhub.localhost/me')
            ->assertServiceUnavailable()
            ->assertHeader('Retry-After', '30');

        expect(tenancy()->initialized)->toBeFalse();
    })->with(['testing', 'local', 'production']);
});

it('provisions end to end when the queue runs the job', function () {
    // phpunit.xml uses the sync driver, so the job runs inline.
    $id = $this->postJson($this->centralUrl('/api/tenants'), registration())->assertAccepted()->json('data.id');

    $this->getJson($this->centralUrl("/api/tenants/{$id}/status"))
        ->assertJsonPath('data.status', 'active');

    $this->freshRequest()
        ->postJson('http://acme.installhub.localhost/login', ['email' => 'ada@acme.test', 'password' => 'sunny-roof-2026'])
        ->assertOk()
        ->assertJsonPath('data.roles', ['admin']);
});

it('is only reachable on the central domain', function () {
    $this->createTenant('acme');

    $this->postJson('http://acme.installhub.localhost/api/tenants', registration(['subdomain' => 'other']))
        ->assertNotFound();

    expect(Tenant::count())->toBe(1);
});
