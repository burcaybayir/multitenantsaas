<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->acme = $this->createTenant('acme');
    $this->bolt = $this->createTenant('bolt');
});

it('keeps each tenant\'s rows in its own database', function () {
    $this->acme->run(fn () => Customer::factory()->count(3)->create());
    $this->bolt->run(fn () => Customer::factory()->create(['email' => 'only-bolt@example.test']));

    $this->acme->run(fn () => expect(Customer::count())->toBe(3)
        ->and(Customer::where('email', 'only-bolt@example.test')->exists())->toBeFalse());

    $this->bolt->run(fn () => expect(Customer::pluck('email')->all())->toBe(['only-bolt@example.test']));
});

it('lets two tenants hold the same natural keys independently', function () {
    $this->acme->run(fn () => Customer::factory()->create(['email' => 'jo@example.test', 'name' => 'Acme Jo']));
    $this->bolt->run(fn () => Customer::factory()->create(['email' => 'jo@example.test', 'name' => 'Bolt Jo']));

    $this->acme->run(fn () => expect(Customer::sole()->name)->toBe('Acme Jo'));
    $this->bolt->run(fn () => expect(Customer::sole()->name)->toBe('Bolt Jo'));
});

it('isolates writes: updating and deleting in one tenant never touches the other', function () {
    // Both tenants get a customer with primary key 1.
    $this->acme->run(fn () => Customer::factory()->create(['name' => 'Acme #1']));
    $this->bolt->run(fn () => Customer::factory()->create(['name' => 'Bolt #1']));

    $this->acme->run(function () {
        Customer::whereKey(1)->update(['name' => 'Renamed by Acme']);
        Customer::query()->delete();
    });

    $this->bolt->run(fn () => expect(Customer::find(1)?->name)->toBe('Bolt #1'));
});

it('refuses cross-database queries at the MySQL level', function () {
    // Even hand-written SQL that names another tenant's schema is rejected,
    // because the connection runs as this tenant's own MySQL user.
    $boltDatabase = $this->bolt->database()->getName();

    $this->acme->run(function () use ($boltDatabase) {
        expect(fn () => DB::select("select * from `{$boltDatabase}`.`customers`"))
            ->toThrow(QueryException::class, 'denied');

        expect(fn () => DB::insert("insert into `{$boltDatabase}`.`customers` (name, email) values ('x', 'x@x.test')"))
            ->toThrow(QueryException::class, 'denied');
    });
});

it('cannot reach the central database from a tenant connection', function () {
    $central = config('database.connections.mysql.database');

    $this->acme->run(function () use ($central) {
        expect(fn () => DB::select("select * from `{$central}`.`tenants`"))
            ->toThrow(QueryException::class, 'denied');
    });
});

it('uses the tenant connection by default and the central one outside tenancy', function () {
    $this->acme->run(function () {
        expect(DB::getDefaultConnection())->toBe('tenant')
            ->and(DB::connection()->getDatabaseName())->toBe($this->acme->database()->getName())
            ->and(User::sole()->email)->toBe('admin@acme.test');
    });

    expect(DB::getDefaultConnection())->toBe('mysql')
        ->and(Schema::hasTable('customers'))->toBeFalse();
});
