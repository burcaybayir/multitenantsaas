<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles every tenant is provisioned with. Permissions per role and the
 * policies that use them arrive with the security phase.
 */
enum Role: string
{
    case Admin = 'admin';
    case Surveyor = 'surveyor';
    case Installer = 'installer';
}
