# InstallHub

Multi-tenant SaaS for UK renewable energy installers (solar PV, heat pumps).
Each installer company is a tenant with its **own MySQL database and its own
MySQL user**, identified by subdomain: `acme.installhub.localhost`.

> Work in progress. Phase 1 (foundation and tenancy) is done. Full architecture
> docs arrive in Phase 6.

## Run locally (Docker)

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

`*.localhost` resolves to 127.0.0.1 in browsers and curl, so no hosts-file
edits are needed.

```bash
# Register a tenant (202 Accepted; provisioning runs on the queue via Horizon)
curl -X POST http://installhub.localhost/api/tenants \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"company_name":"Acme Solar Ltd","subdomain":"acme","admin_name":"Ada",
       "admin_email":"ada@acme.test","admin_password":"sunny-roof-2026",
       "admin_password_confirmation":"sunny-roof-2026"}'

# Poll the URL from the Location header until "status": "active"
```

## Tests

The suite runs against **real MySQL 8 and Redis**. Tenant isolation depends
on engine behaviour (per-tenant MySQL grants, Redis key prefixes, cache tags)
that SQLite or array drivers can't reproduce.

```bash
docker compose exec app composer test   # Pest
docker compose exec app composer lint   # Pint
```

The MySQL app user needs the grants in `docker/mysql/init.sql` (the tests
run under exactly those least-privilege grants).
