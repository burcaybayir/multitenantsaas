<?php

declare(strict_types=1);

use App\Data\TenantAdmin;
use App\Enums\Role;
use App\Enums\TenantStatus;
use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantProvisioner;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

function databaseExists(string $name): bool
{
    return DB::table('information_schema.schemata')->where('schema_name', $name)->exists();
}

/**
 * The app user deliberately has no access to mysql.user, so prove the
 * tenant's MySQL user exists by logging in with its credentials.
 */
function canConnectWithTenantCredentials(Tenant $tenant): bool
{
    config(['database.connections.probe' => $tenant->database()->connection()]);

    try {
        DB::connection('probe')->select('select 1');

        return true;
    } catch (QueryException) {
        return false;
    } finally {
        DB::purge('probe');
    }
}

it('provisions a database, schema, roles and the first admin', function () {
    $tenant = $this->createTenant('acme');

    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->provisioned_at)->not->toBeNull()
        ->and(databaseExists($tenant->database()->getName()))->toBeTrue();

    $tenant->run(function () {
        expect(Schema::hasTable('customers'))->toBeTrue()
            ->and(Spatie\Permission\Models\Role::pluck('name')->sort()->values()->all())
            ->toBe(['admin', 'installer', 'surveyor']);

        $admin = User::sole();
        expect($admin->email)->toBe('admin@acme.test')
            ->and($admin->hasRole(Role::Admin->value))->toBeTrue()
            ->and(Hash::check(self::ADMIN_PASSWORD, $admin->password))->toBeTrue();
    });
});

it('gives every tenant its own MySQL user with an encrypted password at rest', function () {
    $tenant = $this->createTenant('acme');

    $raw = json_decode((string) DB::table('tenants')->where('id', $tenant->id)->value('data'), true);

    expect($raw['tenancy_db_username'])->toStartWith('tu_')
        ->and(canConnectWithTenantCredentials($tenant))->toBeTrue()
        // Stored value is ciphertext, and decrypts to the live password.
        ->and($raw['tenancy_db_password'])->not->toBe($tenant->tenancy_db_password)
        ->and(Crypt::decryptString($raw['tenancy_db_password']))->toBe($tenant->tenancy_db_password);
});

it('is idempotent when the job runs again', function () {
    $tenant = $this->createTenant('acme');
    $tenant->forceFill(['status' => TenantStatus::Provisioning])->save();

    $admin = new TenantAdmin('Admin', 'admin@acme.test', Hash::make('x'));
    (new ProvisionTenant($tenant, $admin))->handle(app(TenantProvisioner::class));

    expect($tenant->refresh()->status)->toBe(TenantStatus::Active);
    $tenant->run(fn () => expect(User::count())->toBe(1));
});

it('recovers when a previous attempt died after CREATE DATABASE', function () {
    Queue::fake();
    $tenant = $this->createTenant('acme');

    // Simulate a crash half way: credentials persisted and database created,
    // but no MySQL user, no tables.
    $tenant->database()->makeCredentials();
    DB::statement("CREATE DATABASE `{$tenant->database()->getName()}`");
    expect(canConnectWithTenantCredentials($tenant->refresh()))->toBeFalse();

    $admin = new TenantAdmin('Admin', 'admin@acme.test', Hash::make('x'));
    (new ProvisionTenant($tenant->refresh(), $admin))->handle(app(TenantProvisioner::class));

    expect($tenant->refresh()->status)->toBe(TenantStatus::Active)
        ->and(canConnectWithTenantCredentials($tenant))->toBeTrue();
    $tenant->run(fn () => expect(User::count())->toBe(1));
});

it('marks the tenant as failed once retries are exhausted', function () {
    Queue::fake();
    $tenant = $this->createTenant('acme');

    $job = new ProvisionTenant($tenant, new TenantAdmin('Admin', 'admin@acme.test', Hash::make('x')));
    $job->failed(new RuntimeException('MySQL went away'));

    expect($tenant->refresh()->status)->toBe(TenantStatus::Failed);
});

it('drops the database and the MySQL user when a tenant is deleted', function () {
    $tenant = $this->createTenant('acme');
    $database = $tenant->database()->getName();

    $tenant->delete();

    expect(databaseExists($database))->toBeFalse()
        ->and(canConnectWithTenantCredentials($tenant))->toBeFalse()
        ->and(Tenant::count())->toBe(0);
});
