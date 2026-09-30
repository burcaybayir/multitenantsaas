<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Runs inside each new tenant database during provisioning. Must stay
 * idempotent: provisioning retries re-run it.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(PermissionRegistrar $permissions): void
    {
        foreach (Role::cases() as $role) {
            RoleModel::findOrCreate($role->value, 'web');
        }

        $permissions->forgetCachedPermissions();
    }
}
