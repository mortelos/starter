# Dry-run / eval harness (UTEQ-530)

How to validate `add-tenancy` end to end without risking a real portal: run it on
a **disposable git worktree** of the starter, for one driver, and assert the
generated isolation test is green. Repeat per driver. Throw the worktree away.

## Substrate rule (learned the hard way)

The eval host must be a worktree with a **real `composer` install**. A symlinked
`vendor/` breaks Laravel 13's package discovery — copy or install for real:

```bash
ROOT=/Users/uteq/Sites/mortelos-starter
SBX=/tmp/add-tenancy-dryrun-database        # one per driver
git -C "$ROOT" worktree add "$SBX" HEAD
cp -a "$ROOT/vendor" "$SBX/vendor"          # real copy, NOT a symlink
cp -a "$ROOT/.env" "$SBX/.env" 2>/dev/null || true
cd "$SBX" && php artisan key:generate --force
```

`vendor/` is gitignored, so a fresh worktree has none; the copy gives Laravel a
real install to boot from. Then add the one package the skill needs:

```bash
composer require "stancl/tenancy:^3.10"     # needs packagist; this is the heavy step
```

## Run the skill for one driver

Apply the templates exactly as the references prescribe (here: `database`):

1. `templates/shared/Tenant.php.stub`            → `app/Models/Tenant.php`
2. `templates/shared/create_tenants_table.php.stub` → `database/migrations/<ts>_create_tenants_table.php`
3. `templates/database/tenancy.php.stub`         → `config/tenancy.php`
4. `templates/database/TenancyServiceProvider.php.stub` → `app/Providers/TenancyServiceProvider.php` (+ register in `bootstrap/providers.php`)
5. `templates/database/example_tenant_migration.php.stub` → `database/migrations/tenant/<ts>_create_notes_table.php`
6. `templates/shared/StanclTenantResolver.php.stub` → `app/Support/StanclTenantResolver.php`
7. `templates/shared/StarterServiceProvider.bindings.snippet` → merge `register()` into `app/Providers/StarterServiceProvider.php`
8. `templates/tests/TenancyBootTest.php.stub`     → `tests/Feature/TenancyBootTest.php`
9. `templates/tests/DatabaseTenantIsolationTest.php.stub` → `tests/Feature/TenantIsolationTest.php`

For sqlite-per-tenant, set the central connection to sqlite and
`tenancy.database.suffix => '.sqlite'`, so each tenant gets a
`database/tenant<id>.sqlite` file (no MySQL needed for the eval).

## Assert

```bash
vendor/bin/pest tests/Feature/TenancyBootTest.php tests/Feature/TenantIsolationTest.php
vendor/bin/pint --dirty --test
```

Pass criteria (the issue AC): the isolation test is green — for `database`, two
tenants resolve to separate connections and A's row is invisible in B; for
`row`, `tenant_id` is auto-filled and reads are scoped. Boot test green in the
framework-absent state (the default; framework is a `suggest`).

## Tear down

```bash
git -C "$ROOT" worktree remove --force "$SBX"
```

## Pilot before fan-out

The `composer require` + sqlite-per-tenant create/migrate is the expensive part.
Pilot **one** driver (database) to validate the harness before running the row
driver, rather than firing both in parallel.

## Verified results (2026-05-31, stancl v3.10.0, Laravel 13.12, PHP 8.4)

Both drivers ran end to end on disposable worktrees and **passed** with the
shipped templates (no manual edits):

- `database`: `TenancyBootTest` (3, 1 skipped) + `TenantIsolationTest` (2) →
  two tenants resolve to separate sqlite databases, A's row invisible in B.
- `row`: `TenancyBootTest` (3, 1 skipped) + `TenantIsolationTest` (2) →
  `tenant_id` auto-filled, reads scoped, central context unscoped.

The `binds StanclTenantResolver when present` case skips on a build-only portal
(framework is a `suggest`); it is the operate-mode-CI case.

### Gotchas the dry-run surfaced (now baked into the templates)

1. **Provider registration must be the FQCN.** A bare
   `TenancyServiceProvider::class` in the non-namespaced `bootstrap/providers.php`
   resolves to a nonexistent global class and is silently skipped — events never
   register, the tenant database is never created, and the connection never
   swaps. Use `\App\Providers\TenancyServiceProvider::class`.
2. **Do not hand-simplify stancl's provider.** Tenant DB provisioning rides a
   `JobPipeline` (`CreateDatabase` → `MigrateDatabase`); raw job class-strings as
   listeners do nothing. The shipped template is stancl's faithful provider.
3. **Isolation tests must declare `uses(RefreshDatabase::class)` themselves.** The
   starter's `Pest.php` only applies it to `Feature/Database`; the templates
   carry the trait so they work wherever they land.
