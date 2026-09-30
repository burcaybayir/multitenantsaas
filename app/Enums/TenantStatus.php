<?php

declare(strict_types=1);

namespace App\Enums;

enum TenantStatus: string
{
    /** Registered; provisioning job queued but not started. */
    case Pending = 'pending';

    /** Provisioning job is running (DB, migrations, seed, admin user). */
    case Provisioning = 'provisioning';

    /** Ready to serve requests. */
    case Active = 'active';

    /** Provisioning exhausted its retries; needs operator attention. */
    case Failed = 'failed';

    public function canServeRequests(): bool
    {
        return $this === self::Active;
    }
}
