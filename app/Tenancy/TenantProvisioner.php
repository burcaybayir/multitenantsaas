<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Actions\Tenancy\CreateTenantAdmin;
use App\Data\TenantAdmin;
use App\Models\Tenant;
use Illuminate\Contracts\Console\Kernel as Artisan;
use RuntimeException;

/**
 * Brings a tenant from "registered" to "ready to serve requests".
 *
 * Every step is idempotent, so the whole thing can be re-run after a failure
 * at any point and will converge on the same end state.
 */
final class TenantProvisioner
{
    public function __construct(
        private readonly Artisan $artisan,
        private readonly CreateTenantAdmin $createAdmin,
    ) {}

    public function provision(Tenant $tenant, TenantAdmin $admin): void
    {
        if ($tenant->isActive()) {
            return;
        }

        $tenant->markProvisioning();

        $this->createDatabase($tenant);
        $this->runTenantCommand('tenants:migrate', $tenant);
        $this->runTenantCommand('tenants:seed', $tenant);

        $tenant->run(fn () => $this->createAdmin->handle($admin));

        $tenant->markActive();
    }

    private function createDatabase(Tenant $tenant): void
    {
        $database = $tenant->database();

        // Generates name/username/password only if not set yet and persists
        // them *before* touching MySQL, so a retry reuses the same values.
        $database->makeCredentials();
        $database->manager()->createDatabase($tenant);
    }

    private function runTenantCommand(string $command, Tenant $tenant): void
    {
        $exitCode = $this->artisan->call($command, ['--tenants' => [$tenant->getTenantKey()]]);

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                '%s failed for tenant %s: %s',
                $command,
                $tenant->getTenantKey(),
                trim($this->artisan->output()),
            ));
        }
    }
}
