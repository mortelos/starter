# Driver: `database` (database-per-tenant, default)

The default and framework-compatible driver. Each tenant gets its own database;
`stancl/tenancy` swaps the default connection on `tenancy()->initialize()`.
There is **no `tenant_id` column** — isolation is the connection itself. This is
exactly how `mortelos/framework` operate-mode works, so a `database` portal can
later adopt the framework with no data migration.

Read `framework-binding.md` (resolver + identification) and `role-policy-hook.md`
(roles + switcher) after this; this file is only the stancl scaffold spine and
the connection-swap isolation mechanism.

## 1. Install

```bash
composer require "stancl/tenancy:^3.10"
php artisan vendor:publish --provider="Stancl\Tenancy\TenancyServiceProvider"
```

Publishing writes `config/tenancy.php`, the `TenancyServiceProvider`, and the
central `tenants`/`domains` migrations. Replace the published `config/tenancy.php`
and `Tenant` model with the templates below so the shape is consistent across
runs (the published defaults assume domain identification; we use slug).

## 2. Files this driver wires

| Template | Destination | Purpose |
|----------|-------------|---------|
| `templates/shared/Tenant.php.stub` | `app/Models/Tenant.php` | Slug-keyed stancl tenant model (shared by both drivers) |
| `templates/shared/create_tenants_table.php.stub` | `database/migrations/<ts>_create_tenants_table.php` | Central `tenants` table (shared) |
| `templates/database/tenancy.php.stub` | `config/tenancy.php` | stancl config with **database** bootstrappers |
| `templates/database/TenancyServiceProvider.php.stub` | `app/Providers/TenancyServiceProvider.php` | Maps stancl events to create/migrate/seed/delete-database jobs |
| `templates/database/tenant_connection.snippet` | merge into `config/database.php` | The per-tenant connection template stancl clones |
| `templates/database/example_tenant_migration.php.stub` | `database/migrations/tenant/<ts>_create_example_table.php` | Proves tenant migrations run on the tenant connection |

Register `TenancyServiceProvider` in `bootstrap/providers.php` (Laravel 11+
uses that array, not `config/app.php`).

## 3. The isolation switch — bootstrappers

The driver is defined by the `bootstrappers` array in `config/tenancy.php`. The
`database` driver keeps `DatabaseTenancyBootstrapper` first; everything else is
optional comfort:

```php
'bootstrappers' => [
    Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class, // the swap
    Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
],
```

`DatabaseTenancyBootstrapper` is the line that makes this database-per-tenant.
The `row` driver drops exactly this one (see `row-driver.md`).

## 4. Central vs tenant connections

- The central connection (`config('tenancy.database.central_connection')`,
  usually the app default) holds `tenants`, `users`, and anything global.
- Tenant databases are cloned from `tenancy.database.template_tenant_connection`
  (the `tenant_connection.snippet`). stancl creates/drops them via the jobs in
  `TenancyServiceProvider` and runs `database/migrations/tenant/*` against the
  swapped connection.
- Tenant migrations live in `database/migrations/tenant/` and run **only** with
  tenancy initialized (via `php artisan tenants:migrate`). Keep app-global
  migrations in `database/migrations/` as usual.

## 5. Lifecycle commands the portal gets

```bash
php artisan tenants:migrate          # run database/migrations/tenant on every tenant DB
php artisan tenants:migrate-fresh
php artisan tenants:seed
php artisan tenants:run "<command>"  # run any command in each tenant context
```

Tenant create/delete is event-driven: creating a `Tenant` row fires stancl's
`TenantCreated`, which the `TenancyServiceProvider` maps to
`CreateDatabase` → `MigrateDatabase` (→ `SeedDatabase`). Deleting fires
`DeleteDatabase`. Do not create tenant databases by hand.

## 6. Verify (database driver)

The isolation proof for this driver is **two tenants resolve to separate
connections and cannot see each other's data**. The generated isolation test
(see `tests-and-docs.md`) initializes tenant A, writes a row, ends tenancy,
initializes tenant B, and asserts the row is absent — proving the swap. Also
assert `tenancy()->initialized` flips true/false around `initialize()`/`end()`.

Run `php artisan tenants:migrate` before the isolation test, or have the test
create+migrate tenants in `beforeEach` (the template does the latter so it is
self-contained).
