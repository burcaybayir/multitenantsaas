<?php

declare(strict_types=1);

namespace App\Actions\Tenancy;

use App\Data\TenantAdmin;
use App\Enums\Role;
use App\Models\User;

/**
 * Must be called inside the tenant's context (tenancy initialized).
 */
final class CreateTenantAdmin
{
    public function handle(TenantAdmin $admin): User
    {
        // firstOrCreate keeps provisioning retry-safe: a second run finds the
        // user from the first run instead of hitting the unique email index.
        $user = User::query()->firstOrCreate(
            ['email' => $admin->email],
            ['name' => $admin->name, 'password' => $admin->passwordHash],
        );

        $user->assignRole(Role::Admin->value);

        return $user;
    }
}
