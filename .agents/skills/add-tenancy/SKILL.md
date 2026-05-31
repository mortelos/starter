---
name: add-tenancy
description: Use this when a MortelOS / mortelos-starter portal needs to serve more than one customer (tenant) from a single deployment — it turns a single-tenant starter into a multi-tenant one. Trigger whenever someone says "add tenancy", "make this multi-tenant", "support multiple customers / organisations / clients in one install", "database per tenant", "tenant isolation", "each customer gets their own data", or describes onboarding a second organisation into the same portal. It binds the host onto mortelos/framework's TenantResolver contract plus stancl/tenancy with ONE isolation driver (database-per-tenant by default, row-scoped as an alternative), reinstates the tenant switcher, and generates isolation tests. Do NOT trigger for a first-time single-customer portal (that is portal-kickoff), and do NOT trigger on non-MortelOS stacks (Jetstream, Filament, plain Laravel).
---

# Add Tenancy

Turn a single-tenant `mortelos/starter` portal into a multi-tenant one. The
starter ships single-tenant on purpose; real multi-tenancy is owned by
`mortelos/framework` (its `Mortel\Contracts\TenantResolver` + `stancl/tenancy`).
This skill **binds the host onto that contract and invents nothing new**. It
picks one isolation driver, wires identification, hooks role-gating onto the
tenant, reinstates the switcher, and generates isolation tests.

The seam already exists: framework binds its `NullTenantResolver` only
`if (! $this->app->bound(TenantResolver::class))`
(`mortelos/framework` `MortelServiceProvider.php:83-84`). This skill fills that
seam from the host. Zero framework change, ever.

## The one-way door — read before choosing a driver

There are two drivers and **the choice is not freely reversible**:

- `database` (default) — database-per-tenant via stancl's connection swap. This
  is what `mortelos/framework` operate-mode requires. A `database` portal can
  later adopt the full framework with no data migration.
- `row` — single shared DB, tenant-scoped rows. Lighter, but framework is
  **connection-swap only** (no `BelongsToTenant` anywhere in it). **A `row`
  portal can never adopt framework operate-mode without a data migration.**
  Offer `row` only for lightweight portals that will provably never run the
  framework, and say this out loud before committing.

Default to `database` unless the user explicitly accepts the foreclosure.

## Invocation

```
add-tenancy --isolation=database                       # default
add-tenancy --isolation=row --shared=users,settings    # single-DB, scoped
```

`--shared` (row only) lists tables that stay un-scoped (global), comma
separated. `users` is always shared; add anything cross-tenant (settings,
plans, feature flags). Everything else gets a `tenant_id` + global scope.

## Workflow

```text
add-tenancy  ("maak dit portaal multi-tenant")
        |
        v
[0] PRE-FLIGHT — detect host state & idempotency
     already multi-tenant? (stancl installed, resolver bound) -> resume/stop
     framework present or only suggested? · governance seam wired? · pest/phpunit?
        |
        v
[1] DRIVER CHOICE — database (default) | row (one-way door)
     confirm the isolation model and, for row, the --shared table list.
     state the foreclosure for row before continuing.
        |
        v
[2] INSTALL & SCAFFOLD                 (references/database-driver.md | row-driver.md)
     composer require stancl/tenancy ^3.10 · central `tenants` table ·
     publish config/tenancy.php · driver-specific bootstrappers ·
     Tenant model + (database: tenant migrations dir | row: BelongsToTenant + scope)
        |
        v
[3] FRAMEWORK BINDING & IDENTIFICATION (references/framework-binding.md)
     generate app/Support/StanclTenantResolver implements Mortel\Contracts\TenantResolver
     bind it (guarded) in StarterServiceProvider · add mortelos/framework to composer "suggest"
     identification middleware: slug-keyed web + token tenant_id claim (MCP)
     -> ends in tenancy()->initialize(), ordered BEFORE framework ResolveRole/EnforcePolicy
        |
        v
[4] ROLE/POLICY HOOK & SWITCHER        (references/role-policy-hook.md)
     host seam App\Access\{AccessActor,ActorContext,SystemActor,DeniedActor} + App\Enums\TrustLevel
     App\Contracts\GovernanceGate (fail-closed) wired into config('starter.governance.access_resolver')
     roles become tenant-scoped DB data (deny-by-default) · reuse Mortel\Actions\Policies\CheckPolicy when framework present
     User::tenants() many-to-many · reinstate switcher in topbar ONLY when user has >1 tenant
        |
        v
[5] OPTIONAL AUDIT (event-sourcing)    (references/event-sourcing-audit.md)
     only if the user wants an audit trail. connection-agnostic (no fixed $connection):
     central single-tenant, tenant DB multi-tenant, same code. host-wired, no framework change.
        |
        v
[6] TESTS & DOCS                       (references/tests-and-docs.md)
     boot test (framework-absent AND framework-present states) +
     tenant-isolation test (database: separate connections | row: scoped queries)
     update the portal's docs/AGENTS notes with the chosen driver + foreclosure note
        |
        v
[7] VERIFY GATE
     vendor/bin/pest green · vendor/bin/pint --dirty clean · isolation test passes.
     report driver, foreclosure status, and what the partner must do next.
```

Create one todo per phase so progress is visible, then work them in order. You
may resume mid-way (phase [0] detects prior runs); do not clobber existing
tenancy wiring.

## Phase [0]: Pre-flight

Read host state before touching anything, so a re-run does not double-wire.

- **Already multi-tenant?** If `stancl/tenancy` is in `composer.json` and
  `app/Support/StanclTenantResolver.php` exists, this skill already ran. Print
  what is wired (driver, shared tables, switcher, audit) and ask before doing
  anything; default to a no-op.
- **Framework state.** Is `mortelos/framework` in `require`, in `suggest`, or
  absent? The default starter has it nowhere. You will add it to **`suggest`**
  (R1) — never force it into `require`. If it is already in `require`, the
  resolver binding is live; if only suggested/absent, the binding is dormant
  but tenancy still runs on stancl.
- **Governance seam.** Confirm `config/starter.php` has
  `governance.access_resolver` (nullable, deny-by-default when null) and that
  `resources/views/livewire/pages/governance/governance.blade.php` reads it.
  This is the `GovernanceGate` seam you hook in phase [4]; it ships in the
  starter already.
- **Test framework.** `vendor/bin/pest` vs `vendor/bin/phpunit`. Generated
  tests in phase [6] follow whichever is present.
- **Stack sanity.** Laravel + Livewire 4 + `mortelos/starter`. If this is not a
  MortelOS host, stop — this skill does not apply.

State what you found and whether you are doing a fresh wire or a resume.

## Phase [1]: Driver choice

Confirm the isolation model with the user. `database` is the default and the
only one compatible with framework operate-mode. If they pick `row`, restate
the one-way door ("this portal can never adopt framework operate-mode without a
data migration") and capture the `--shared` table list before continuing.

## Phase [2]: Install & scaffold

Both drivers share the install spine (stancl, central `tenants` table,
identification middleware). The isolation mechanism differs. Follow the
matching reference exactly:

- `database` → **`references/database-driver.md`** (connection swap, tenant
  migrations under `database/migrations/tenant`).
- `row` → **`references/row-driver.md`** (`BelongsToTenant` + `TenantScope`,
  drop `DatabaseTenancyBootstrapper`, shared-table handling).

Templates live in `templates/`; copy and adapt them rather than hand-writing
each file (they encode the exact stancl + framework shapes so every run is
consistent). The reference tells you which template maps to which path.

## Phase [3]: Framework binding & identification

Follow **`references/framework-binding.md`**. The host generates
`app/Support/StanclTenantResolver.php` implementing the real
`Mortel\Contracts\TenantResolver` (`id()`, `initialized()`, `data()`), binds it
**guarded** so a framework-absent install never fatals on the missing
interface, and adds `mortelos/framework` to composer `suggest`. Identification
is slug-keyed for web (stancl `ResolvesTenant`) plus a token `tenant_id` claim
for MCP; both end in `tenancy()->initialize()` and must be ordered **before**
framework's `ResolveRole`/`EnforcePolicy` (and degrade cleanly when those are
not installed, which is the default).

## Phase [4]: Role/policy hook & switcher

Follow **`references/role-policy-hook.md`**. Generate the host-owned access seam
under `App\Access\*` + `App\Enums\TrustLevel`, mirroring framework signatures
1:1 so a later framework adoption is a find-replace `App\Access → Mortel\Access`,
not a rewrite. Add `App\Contracts\GovernanceGate` (fail-closed default) and wire
it into the existing `governance.access_resolver` config seam. Roles become
**tenant-scoped DB data, deny-by-default**; reuse `Mortel\Actions\Policies\CheckPolicy`
unchanged when framework is present. Add `User::tenants()` (many-to-many) and
reinstate the switcher in the topbar **only when a user belongs to >1 tenant**.

## Phase [5]: Optional audit (event-sourcing)

Only if the user wants an audit trail. Follow
**`references/event-sourcing-audit.md`**. The store is connection-agnostic (no
fixed `$connection`, like framework's `UteqStoredEvent`): it follows
`config('database.default')`, so it records into the central DB single-tenant
and the tenant DB multi-tenant with the same code and no event migration. Wire
it host-side (config + observer + events migration on the default connection);
no framework change.

## Phase [6]: Tests & docs

Follow **`references/tests-and-docs.md`**. Generate a boot test that passes in
**both** the framework-absent and framework-present states, and a
tenant-isolation test that proves the chosen driver isolates data (database:
two tenants resolve to separate connections; row: queries are tenant-scoped and
a second tenant cannot read the first's rows). Update the portal's own docs
(README / AGENTS notes) with the chosen driver and, for `row`, the foreclosure.

## Phase [7]: Verify gate

Evidence before claims:

- `vendor/bin/pest` (or `phpunit`) green.
- `vendor/bin/pint --dirty` clean.
- The tenant-isolation test passes.

Then report, in a short list: the driver, whether framework operate-mode is
still reachable (database: yes; row: foreclosed), what was wired, and the one or
two things the partner does next (e.g. seed tenants, add `mortelos/framework` to
`require` when going to operate-mode).

## Key principles

- **Framework owns tenancy; the host only binds.** Never re-implement the
  resolver, the connection swap, or the role model. Fill the seam, do not
  rebuild it.
- **`suggest`, never `require`.** Adding `mortelos/framework` to `require`
  pulls a heavy dependency build-only portals do not need. The resolver is
  written against the contract but bound guarded, so a framework-absent install
  runs on stancl alone.
- **One driver per portal.** Mixed db+row in one install is out of scope.
- **`row` is a one-way door.** Say so before choosing it.
- **Deny by default.** The governance gate returns false until a role grants
  access; roles are tenant-scoped data, not hardcoded names.
- **Switcher only when earned.** Reinstate the tenant switcher only for users
  who actually belong to more than one tenant.
- **Isolation is proven by a test, not asserted.** The dry-run (and every real
  run) must pass a tenant-isolation test before the gate.
- **Idempotent.** Phase [0] detects a prior run; never double-wire.
