<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * An installer company. Lives in the central database; everything the
 * company owns (users, customers, installations...) lives in its own DB.
 *
 * @property string $id
 * @property string $name
 * @property TenantStatus $status
 * @property Carbon|null $provisioned_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    /**
     * The base model is fully unguarded. Status and DB credentials must only
     * change through the explicit methods below / the provisioning pipeline.
     *
     * @var list<string>
     */
    protected $fillable = ['name'];

    /**
     * Real columns; anything else is stored in the `data` JSON column.
     *
     * @return list<string>
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'status', 'provisioned_at', 'created_at', 'updated_at'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'provisioned_at' => 'datetime',
            // The per-tenant MySQL password would otherwise sit in plaintext in
            // the central `data` column. The package reads it back through
            // getAttribute(), so the cast is transparent to it.
            'tenancy_db_password' => 'encrypted',
        ];
    }

    public function markProvisioning(): void
    {
        $this->forceFill(['status' => TenantStatus::Provisioning])->save();
    }

    public function markActive(): void
    {
        $this->forceFill([
            'status' => TenantStatus::Active,
            'provisioned_at' => now(),
        ])->save();
    }

    public function markFailed(): void
    {
        $this->forceFill(['status' => TenantStatus::Failed])->save();
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }
}
