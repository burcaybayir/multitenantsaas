<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Tenancy\IdempotentMySQLDatabaseManager;
use Database\Seeders\Tenant\TenantDatabaseSeeder;
use Stancl\Tenancy\Bootstrappers;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\UUIDGenerator;

return [
    'tenant_model' => Tenant::class,
    'id_generator' => UUIDGenerator::class,

    'domain_model' => Domain::class,

    /*
     * Hosts that serve the central (non-tenant) application: marketing site,
     * tenant registration, Horizon. Tenants are identified as exactly one DNS
     * label directly below one of these, e.g. acme.installhub.localhost.
     */
    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CENTRAL_DOMAINS', 'installhub.localhost')),
    ))),

    /*
     * Everything that has to be switched when entering a tenant context.
     * Each bootstrapper is reverted when tenancy ends (end of request/job).
     */
    'bootstrappers' => [
        Bootstrappers\DatabaseTenancyBootstrapper::class,
        Bootstrappers\CacheTenancyBootstrapper::class,
        Bootstrappers\FilesystemTenancyBootstrapper::class,
        Bootstrappers\QueueTenancyBootstrapper::class,
        Bootstrappers\RedisTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'mysql'),

        // Tenant connections are built from the central connection's config,
        // with database/username/password overridden per tenant.
        'template_tenant_connection' => null,

        // Tenant DB name = prefix + tenant UUID. The MySQL app user only holds
        // privileges on `tenant\_%`, so this prefix is also a security boundary.
        'prefix' => env('TENANCY_DB_PREFIX', 'tenant_'),
        'suffix' => '',

        'managers' => [
            // One database *and one MySQL user* per tenant; see the class for why.
            'mysql' => IdempotentMySQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        // Every cache call in tenant context is tagged with tenant_<id>.
        'tag_base' => 'tenant_',
    ],

    'filesystem' => [
        // storage_path() and disk roots are suffixed with tenants/<uuid>.
        // For S3 that becomes a key prefix: s3://bucket/tenants/<uuid>/...
        'suffix_base' => 'tenants/',
        'disks' => [
            'local',
            'public',
            's3',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/private/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        // We don't serve tenant assets through the package's asset controller.
        'asset_helper_tenancy' => false,
    ],

    'redis' => [
        'prefix_base' => 'tenant_',
        // Only the connection used for direct Redis access is prefixed. The
        // queue connection must NOT be, otherwise a job dispatched inside a
        // tenant would land on a key no central worker is listening to.
        'prefixed_connections' => [
            'default',
        ],
    ],

    'features' => [],

    // Disables the package's tenant asset route; tenant routes live in routes/tenant.php.
    'routes' => false,

    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => TenantDatabaseSeeder::class,
        '--force' => true,
    ],
];
