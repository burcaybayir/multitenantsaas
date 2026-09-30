<?php

declare(strict_types=1);

namespace App\Data;

/**
 * The first admin user of a new tenant, carried through the provisioning job.
 *
 * Only the password *hash* is ever held here: the plaintext never leaves the
 * HTTP request, so it can't end up in Redis, Horizon's UI or failed_jobs.
 */
final readonly class TenantAdmin
{
    public function __construct(
        public string $name,
        public string $email,
        public string $passwordHash,
    ) {}
}
