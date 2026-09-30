<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSessionBelongsToTenant;
use App\Models\Customer;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->acme = $this->createTenant('acme');
    $this->bolt = $this->createTenant('bolt');
});

describe('identification', function () {
    it('serves each tenant on its own subdomain', function () {
        $session = $this->loginTo($this->acme);

        $this->withSessionCookie($session)
            ->getJson('http://acme.installhub.localhost/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@acme.test');
    });

    it('404s for hosts that are not exactly <label>.<central domain>', function (string $host) {
        $this->postJson("http://{$host}/login", ['email' => 'admin@acme.test', 'password' => self::ADMIN_PASSWORD])
            ->assertNotFound();
    })->with([
        'unknown tenant' => 'nobody.installhub.localhost',
        'nested subdomain' => 'acme.evil.installhub.localhost',
        'nested below tenant' => 'evil.acme.installhub.localhost',
        'lookalike domain' => 'acmeinstallhub.localhost',
        'real label on lookalike parent' => 'acme.evilinstallhub.localhost',
        'foreign domain' => 'acme.example.com',
    ]);

    it('does not expose tenant routes on the central domain', function () {
        $this->postJson($this->centralUrl('/login'), ['email' => 'admin@acme.test', 'password' => self::ADMIN_PASSWORD])
            ->assertNotFound();
    });
});

describe('authentication', function () {
    it('issues an XSRF cookie for the dashboard SPA', function () {
        $this->get($this->tenantUrl($this->acme, '/csrf-cookie'))
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    });

    it('rejects a user\'s credentials on another tenant', function () {
        // admin@acme.test simply does not exist in bolt's database.
        $this->freshRequest()
            ->postJson($this->tenantUrl($this->bolt, '/login'), ['email' => 'admin@acme.test', 'password' => self::ADMIN_PASSWORD])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    });

    it('does not accept tenant A\'s session cookie on tenant B', function () {
        $acmeSession = $this->loginTo($this->acme);

        // Both tenants have a user with id 1, which is exactly the case where
        // a leaked session would authenticate as the wrong person.
        $this->withSessionCookie($acmeSession)
            ->getJson($this->tenantUrl($this->bolt, '/me'))
            ->assertUnauthorized();

        $this->withSessionCookie($acmeSession)
            ->getJson($this->tenantUrl($this->bolt, '/customers'))
            ->assertUnauthorized();
    });

    it('throttles login attempts per tenant', function () {
        foreach (range(1, 5) as $_) {
            $this->freshRequest()
                ->postJson($this->tenantUrl($this->acme, '/login'), ['email' => 'admin@acme.test', 'password' => 'wrong'])
                ->assertUnprocessable();
        }

        $this->freshRequest()
            ->postJson($this->tenantUrl($this->acme, '/login'), ['email' => 'admin@acme.test', 'password' => self::ADMIN_PASSWORD])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', fn (string $message) => str_contains($message, 'Too many'));

        // The lockout lives in acme's cache namespace only.
        $this->loginTo($this->bolt);
    });
});

describe('with a shared session store', function () {
    // Simulates the misconfiguration EnsureSessionBelongsToTenant defends
    // against: one session store for all tenants (e.g. a shared Redis). A
    // file store with a fixed directory is genuinely shared across tenants.
    beforeEach(function () {
        $this->sharedSessionPath = sys_get_temp_dir().'/installhub-shared-sessions-'.getmypid();
        File::ensureDirectoryExists($this->sharedSessionPath);
        config(['session.driver' => 'file', 'session.files' => $this->sharedSessionPath]);
    });

    afterEach(fn () => File::deleteDirectory($this->sharedSessionPath));

    it('invalidates a session replayed on another tenant instead of logging it in', function () {
        $acmeSession = $this->loginTo($this->acme);

        $this->withSessionCookie($acmeSession)->getJson($this->tenantUrl($this->acme, '/me'))->assertOk();

        // Without the binding, this would authenticate as bolt's user #1.
        $this->withSessionCookie($acmeSession)
            ->getJson($this->tenantUrl($this->bolt, '/me'))
            ->assertUnauthorized();
    });

    it('binds every session to the tenant that issued it', function () {
        $this->loginTo($this->acme);

        expect(session(EnsureSessionBelongsToTenant::SESSION_KEY))->toBe($this->acme->id);
    });
});

describe('reads and writes over HTTP', function () {
    it('lists only the current tenant\'s customers', function () {
        $this->acme->run(fn () => Customer::factory()->create(['email' => 'acme-customer@example.test']));
        $this->bolt->run(fn () => Customer::factory()->create(['email' => 'bolt-customer@example.test']));

        $this->withSessionCookie($this->loginTo($this->bolt))
            ->getJson($this->tenantUrl($this->bolt, '/customers'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'bolt-customer@example.test');
    });

    it('cannot fetch another tenant\'s record by guessing its id', function () {
        $acmeCustomerId = $this->acme->run(fn () => Customer::factory()->create()->id);

        $this->withSessionCookie($this->loginTo($this->bolt))
            ->getJson($this->tenantUrl($this->bolt, "/customers/{$acmeCustomerId}"))
            ->assertNotFound();
    });

    it('writes only into the current tenant\'s database', function () {
        $this->withSessionCookie($this->loginTo($this->acme))
            ->postJson($this->tenantUrl($this->acme, '/customers'), [
                'name' => 'Jo Bloggs',
                'email' => 'jo@example.test',
                'phone' => '07700 900123',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'jo@example.test');

        $this->acme->run(fn () => expect(Customer::where('email', 'jo@example.test')->exists())->toBeTrue());
        $this->bolt->run(fn () => expect(Customer::count())->toBe(0));
    });

    it('validates uniqueness against the current tenant only', function () {
        $this->bolt->run(fn () => Customer::factory()->create(['email' => 'jo@example.test']));

        // Taken in bolt, free in acme.
        $this->withSessionCookie($this->loginTo($this->acme))
            ->postJson($this->tenantUrl($this->acme, '/customers'), ['name' => 'Jo', 'email' => 'jo@example.test'])
            ->assertCreated();
    });
});
