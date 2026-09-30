<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use League\Flysystem\PathTraversalDetected;

beforeEach(function () {
    $this->acme = $this->createTenant('acme');
    $this->bolt = $this->createTenant('bolt');
});

it('gives each tenant its own local disk root', function () {
    $this->acme->run(fn () => Storage::disk('local')->put('surveys/report.pdf', 'acme report'));

    $this->bolt->run(function () {
        expect(Storage::disk('local')->exists('surveys/report.pdf'))->toBeFalse();
        Storage::disk('local')->put('surveys/report.pdf', 'bolt report');
    });

    $this->acme->run(function () {
        expect(Storage::disk('local')->get('surveys/report.pdf'))->toBe('acme report')
            ->and(Storage::disk('local')->path('surveys/report.pdf'))
            ->toContain("tenants/{$this->acme->id}/app/private/surveys/report.pdf");
    });

    expect(Storage::disk('local')->exists('surveys/report.pdf'))->toBeFalse(); // central
});

it('cannot escape its root with path traversal', function () {
    $this->bolt->run(fn () => Storage::disk('local')->put('secret.txt', 'bolt secret'));

    $this->acme->run(function () {
        expect(fn () => Storage::disk('local')->get("../../../{$this->bolt->id}/app/private/secret.txt"))
            ->toThrow(PathTraversalDetected::class);
    });
});

it('prefixes S3 keys with the tenant id', function () {
    $this->acme->run(fn () => expect(config('filesystems.disks.s3.root'))->toBe("tenants/{$this->acme->id}"));
    $this->bolt->run(fn () => expect(config('filesystems.disks.s3.root'))->toBe("tenants/{$this->bolt->id}"));

    expect(config('filesystems.disks.s3.root'))->toBeNull(); // central
});

it('suffixes storage_path() per tenant', function () {
    $central = storage_path();

    $this->acme->run(fn () => expect(storage_path())->toBe("{$central}/tenants/{$this->acme->id}"));

    expect(storage_path())->toBe($central);
});
