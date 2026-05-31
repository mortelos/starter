# Driver: `row` (single-DB, row-scoped) — the one-way door

One shared database; every tenant-owned table carries a `tenant_id` and a global
scope filters by the active tenant. Lighter than database-per-tenant: no
connection swap, no per-tenant database lifecycle, one `migrate`.

## ⚠ Foreclosure — say this out loud before choosing `row`

`mortelos/framework` is **connection-swap only**; it has no `BelongsToTenant`
anywhere. A `row` portal therefore **can never adopt framework operate-mode
without migrating every scoped table into per-tenant databases**. Choose `row`
only for a lightweight portal that will provably never run the framework. If
there is any chance the portal grows into the framework, use `database`.

Read `framework-binding.md` (resolver + identification — `row` still needs both)
and `role-policy-hook.md` after this.

## 1. Install (same spine as `database`)

```bash
composer require "stancl/tenancy:^3.10"
php artisan vendor:publish --provider="Stancl\Tenancy\TenancyServiceProvider"
```

`row` keeps stancl for **identification** (the central `tenants` table and the
middleware that flips `tenancy()->initialized`). It just does not swap the
connection. So you still get `tenant()` / `tenancy()` helpers and a real active
tenant; only the isolation mechanism differs.

## 2. The isolation switch — drop one bootstrapper

In `config/tenancy.php`, the **only** change from the `database` driver is
removing `DatabaseTenancyBootstrapper`. Use `templates/row/tenancy.php.stub`:

```php
'bootstrappers' => [
    // DatabaseTenancyBootstrapper intentionally absent — this is row-scoping.
    Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
    Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
],
```

`row` also does **not** use the database-lifecycle `TenancyServiceProvider` from
the `database` driver. Either skip that provider, or register it with empty
`TenantCreated`/`DeletingTenant` job lists — there is no per-tenant database to
create or drop.

## 3. Files this driver wires

| Template | Destination | Purpose |
|----------|-------------|---------|
| `templates/shared/Tenant.php.stub` | `app/Models/Tenant.php` | Slug-keyed tenant (shared) |
| `templates/shared/create_tenants_table.php.stub` | `database/migrations/<ts>_create_tenants_table.php` | Central `tenants` table (shared) |
| `templates/row/tenancy.php.stub` | `config/tenancy.php` | stancl config **without** DatabaseTenancyBootstrapper |
| `templates/row/BelongsToTenant.php.stub` | `app/Models/Concerns/BelongsToTenant.php` | Trait: auto-fill `tenant_id` + apply scope + `tenant()` relation |
| `templates/row/TenantScope.php.stub` | `app/Models/Scopes/TenantScope.php` | Global scope filtering by the active tenant |
| `templates/row/example_scoped_migration.php.stub` | `database/migrations/<ts>_create_notes_table.php` | Example scoped table with `tenant_id` |

## 4. Shared vs scoped tables (`--shared`)

The `--shared` list names tables that stay **global** (un-scoped). Everything
else is tenant-scoped: it gets a `tenant_id` column + the `BelongsToTenant`
trait on its model.

- **Always shared:** `users` (a person may belong to >1 tenant — the pivot
  carries the membership, not the user row), plus any genuinely cross-tenant
  table the user lists (settings, plans, feature flags).
- **Scoped (default):** every other domain table. Add `tenant_id` (indexed,
  often part of a composite unique with the business key) and put
  `BelongsToTenant` on the model.

For each scoped table, the migration adds:

```php
$table->string('tenant_id')->index();
// or, if you want referential integrity to the central tenants table:
// $table->foreignId... -> string FK; tenants.id is a string, so:
$table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
```

## 5. How scoping works

`BelongsToTenant`:
- on `creating`, fills `tenant_id` from `tenant()?->getTenantKey()` when tenancy
  is initialized (so you never set it by hand in tenant context);
- adds `TenantScope`, a global scope that, **when tenancy is initialized**,
  appends `where tenant_id = <active tenant key>` to every query;
- exposes a `tenant()` relation to `App\Models\Tenant`.

**Central context (tenancy not initialized) is unscoped on purpose** — artisan,
seeders and cross-tenant admin see all rows. That is also the sharp edge: a web
request that reaches a scoped model **without** identification having run would
see every tenant's rows. The identification middleware (`framework-binding.md`)
must therefore run on every tenant route; treat any un-identified access to
scoped data as admin-only. The isolation test asserts exactly this boundary.

## 6. Verify (row driver)

The isolation proof is **scoped queries**: initialize tenant A, create a scoped
row, switch to tenant B, and assert the row is invisible; then drop to central
context and assert both rows are visible (unscoped). Also assert `tenant_id` is
auto-filled on create under tenant context. See `tests-and-docs.md`.
