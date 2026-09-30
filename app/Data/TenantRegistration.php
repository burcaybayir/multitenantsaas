<?php

declare(strict_types=1);

namespace App\Data;

final readonly class TenantRegistration
{
    public function __construct(
        public string $companyName,
        public string $subdomain,
        public TenantAdmin $admin,
    ) {}
}
