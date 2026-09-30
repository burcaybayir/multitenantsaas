<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Data\TenantRegistration;
use App\Enums\TenantStatus;
use App\Jobs\ProvisionTenant;
use App\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records a new installer company and hands the slow, failure-prone work
 * (CREATE DATABASE, migrations, seeding) to a queued job, so the HTTP
 * request returns in milliseconds and provisioning can be retried.
 */
final class RegisterTenant
{
    public function handle(TenantRegistration $registration): Tenant
    {
        try {
            $tenant = DB::transaction(function () use ($registration): Tenant {
                $tenant = new Tenant(['name' => $registration->companyName]);
                $tenant->status = TenantStatus::Pending;
                $tenant->save();

                $tenant->domains()->create(['domain' => $registration->subdomain]);

                return $tenant;
            });
        } catch (UniqueConstraintViolationException) {
            // Lost a race with a concurrent registration for the same
            // subdomain; the Form Request check had passed for both.
            throw ValidationException::withMessages([
                'subdomain' => __('validation.unique', ['attribute' => 'subdomain']),
            ]);
        }

        // Dispatched after commit: a worker must never pick up the job before
        // the tenant row is visible to it.
        ProvisionTenant::dispatch($tenant, $registration->admin)->afterCommit();

        return $tenant;
    }
}
