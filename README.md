# Bough Digital Portal

Laravel MVC workspace SaaS foundation for `portal.boughdigital.com`.

Phase 1 includes workspace-aware authentication, roles, policies, clients, documents, tasks, activity logging, REST routes, the initial Laravel MCP endpoint, seed data, and tenant isolation tests.

Canonical development database: PostgreSQL

Canonical production database: PostgreSQL

Local PostgreSQL connection:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=boughdigital
DB_USERNAME=boughdigital
DB_PASSWORD=root
```

SQLite is not used for the primary development database or for the database-backed tenancy, API, and MCP test suite.

## Local Setup

```bash
php /tmp/composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
```

## Development Database Safety

Do not run `php artisan migrate:fresh`, `php artisan migrate:refresh`, `php artisan db:wipe`, or truncate production/development data unless explicitly approved.

For normal migration updates, run:

```bash
php artisan migrate
```

For non-destructive seed updates, run:

```bash
php artisan db:seed
```

To restore the standard local development users without wiping PostgreSQL, run:

```bash
php artisan db:seed --class=DevelopmentUserSeeder
```

Create the first production super admin without hard-coded credentials:

```bash
php artisan bough:create-super-admin admin@example.com "Admin Name"
```

## URLs

- Login: `/login`
- Platform admin: `/admin/dashboard`
- Workspace portal: `/{workspace}/dashboard`
- REST API: `/{workspace}/api/clients`
- MCP: `/mcp/{workspace}`

See `docs/` for architecture, tenancy, permissions, database, MCP, integrations, and deployment notes.
