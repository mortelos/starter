# Design — De-tenant the starter + `add-tenancy` skill

- **Date:** 2026-05-31
- **Status:** Draft (awaiting review)
- **Owner:** Nathan Jansen (UTEQ)
- **Scope:** `mortelos/starter` host app + new `add-tenancy` skill

---

## 1. Goal

Make `mortelos/starter` **single-tenant by default** by removing the
half-finished, non-functional multi-tenancy scaffolding it ships today, and
move real multi-tenancy into an **opt-in `add-tenancy` skill** that wires the
host onto the tenancy contract `mortelos/framework` already defines.

Outcomes:

1. A fresh `composer create-project mortelos/starter mijn-portal` boots a clean
   single-tenant app: `login -> dashboard`, no tenant ceremony, no dead code.
2. Roles and policies become **owner-editable data** (not hardcoded), with an
   optional connection-agnostic event-sourcing audit trail that works single-
   and multi-tenant.
3. When a portal genuinely needs multiple customers in one deployment, the
   `add-tenancy` skill applies one isolation model (database-per-tenant by
   default, row-scoped as an alternative) by binding the host onto
   `Mortel\Contracts\TenantResolver` + `stancl/tenancy`.

Non-goal: inventing a new tenancy layer. The ecosystem already has one in
`mortelos/framework`; we align with it.

---

## 2. Findings (verified)

### 2.1 The starter's "multi-tenancy" is dead scaffolding

It does not function and is never exercised by tests:

- `app/Models/User.php` has only `name/email/password`. There is **no**
  `tenants()` relation, no `Tenant` model, no `tenant_user` pivot, and no
  tenant table in `database/migrations/` (only users/cache/jobs).
- `resources/views/livewire/pages/dashboard/dashboard.blade.php:23-30` calls
  `auth()->user()->tenants()->wherePivot('tenant_id', $tenantId)` to derive a
  role. That method does not exist on `User`, so rendering the dashboard for a
  logged-in user throws `BadMethodCallException`.
- `resources/views/livewire/shared/topbar.blade.php:33` iterates the same
  non-existent `tenants()` relation (the org switcher).
- `resources/views/livewire/pages/governance/governance.blade.php:76` passes
  `session('tenant_id')` into a `canManage()` resolver that is `null` by
  default.
- Boot flow couples on a session key: `routes/starter.php:23-26` redirects to
  `auth.tenant-select` when `session('tenant_id')` is null;
  `app/Http/Controllers/Auth/PasswordLoginController.php:30` and
  `resources/views/livewire/pages/auth/login.blade.php:14` redirect to
  `auth.tenant-select` after login.
- `app/Http/Controllers/Auth/TenantSelectController.php` hard-codes a single
  `'default'` tenant and auto-picks it, so the picker never renders.
- `tests/Feature/BootSmokeTest.php` only checks the **guest** redirect, the
  `/login` page, file existence, and the doctor command. It **never logs in
  and renders the dashboard**, which is why the `tenants()` fatal has gone
  unnoticed. AGENTS.md §10 claims "login -> dashboard returns 200 for the
  seeded admin", but that path is untested and would currently fail.

Conclusion: this is a broken re-implementation of a concept the framework
already solves correctly. Removing it eliminates a latent conflict.

### 2.2 `mortelos/framework` already owns tenancy (the source of truth)

Verified by shallow-cloning `github.com/mortelos/framework`:

- **Resolver contract already exists:** `Mortel\Contracts\TenantResolver` with
  three methods: `id(): ?string`, `initialized(): bool`,
  `data(string $key, mixed $default = null): mixed`. Framework binds
  `Mortel\Contracts\NullTenantResolver` as a default scoped binding when the
  host provides none (`src/MortelServiceProvider.php:83-84`). This is exactly
  the thin resolver seam we want; it is designed to be filled by a host.
- **Engine is fixed:** `stancl/tenancy ^3.10` is a direct dependency. Tenancy
  is **database-per-tenant** via Stancl's connection swap, no `tenant_id`
  column (migration headers state this explicitly). Database-per-tenant is the
  established norm.
- **Roles are a framework concern, without spatie:** framework uses its own
  `Mortel\Models\Role` + `Mortel\Access\ActorContext` (carrying `user`,
  `tenantId`, `branchId`, `Role`) and `ActorResolver` with role pinning. No
  `spatie/laravel-permission`.
- Per-tenant domain models live under `App\Models\Tenant\*` (Entity, Channel,
  InboxItem, Policy, Role, Workflow, ...); framework publishes tenant
  migrations to `database/migrations/tenant`.

### 2.3 Impact on the other `mortelos/*` packages

- **None from the strip.** The starter is an app template, not a dependency;
  no package imports from it. `mortelos/ui` ships four Blade components with
  zero tenancy.
- `chat`, `channel-*`, `widget-*` are consumers of framework's tenant context
  (they run inside the tenant DB in operate mode), not of the starter. A full
  per-package audit is a research-gate task (R5) to confirm, but the pattern is
  clear: one tenancy source of truth, owned by framework.

---

## 3. Decisions (locked)

| # | Decision | Choice |
|---|----------|--------|
| D1 | Strip depth | Full strip of the broken scaffolding; the seam is framework's resolver contract, not a bespoke facade |
| D2 | Multi-tenant default model | Database-per-tenant (Stancl connection swap) |
| D3 | Alternative model | Row-scoped single-DB (per-table scoped vs shared) |
| D4 | Mode | One model **per portal**, chosen when the skill runs. Mixed-mode (db + row in one install) is **out of scope** |
| D5 | User ↔ tenant | Many-to-many supported (switcher can return in multi-tenant) |
| D6 | Tenancy engine | `stancl/tenancy ^3.10` (matches framework) |
| D7 | Foundation | Align onto `Mortel\Contracts\TenantResolver`; do not invent a parallel tenancy |
| D8 | Roles | Framework's `Role`/`ActorContext` shape; **no** `spatie/laravel-permission` |
| D9 | Task tracking | New Linear project under team **Uteq**, parent issue + sub-issues |
| D10 | Spec location | `docs/specs/` (repo has no `.planning/`) |
| D11 | Roles & policies | Manageable **data in the DB**, owner-editable via an admin screen, **deny-by-default**. Not hardcoded, not config-in-code. The dashboard/governance gates route through this policy layer (mirroring framework's `default_policies` shape), not hardcoded role names |
| D12 | Audit / event-sourcing | **Optional, connection-agnostic capability**: it follows the active default connection (no fixed `$connection`, like framework's `UteqStoredEvent`), so it works single-tenant (central DB) and multi-tenant (tenant DB) with the same code. The roles/policy audit trail runs over it, reusing framework's event-store convention rather than a divergent store |

### 3.1 Resolved decision (OPEN-1)

The bare single-tenant starter's role-gating (the dashboard sends non-admins to
inbox) currently hangs off the broken tenant pivot. Decision: replace it with a
**config/data-driven role + policy layer** (D11), not a hardcoded role enum and
not a config-in-code list. Reasoning that settled it:

- Roles/rights must be editable **by the portal owner** (a non-developer) at
  runtime, so they live as data in the DB with an admin screen; config-in-code
  would need a developer plus a deploy to change.
- This mirrors framework's own model (policy-as-data, `RoleAggregate`,
  `default_policies` config that consuming apps extend) so the call-sites stay
  stable when framework's engine later takes over.
- The audit trail uses optional event-sourcing (D12), not a bespoke log, so it
  stays consistent with the ecosystem and needs no migration when a portal goes
  multi-tenant.

R4/R7 cover *how* the layer mirrors framework's contracts; R6 covers the
event-sourcing layer.

---

## 4. Architecture

The seam is framework's `TenantResolver`. Call-sites ask the resolver; the
binding changes between single- and multi-tenant. Call-sites never change.

```
SINGLE-TENANT (after strip)            MULTI-TENANT (after add-tenancy skill)
──────────────────────────            ──────────────────────────────────────
dashboard / governance                dashboard / governance
   │ ask: current tenant + role          │ (call-sites UNCHANGED)
   ▼                                      ▼
TenantResolver  ◄──── bound ────►     TenantResolver
 single-tenant impl:                   stancl-backed impl:
  id() = fixed/default                  id() = active tenant (connection swap)
  initialized() = true                  initialized() = true
  role = framework-shaped default       role = Mortel\Access\Role (per tenant)
   │                                      │
   ▼                                      ▼
one DB, no scope                       db-per-tenant  OR  row-scoped
```

### 4.1 Coupling question (research-gated, R1)

The bare starter does not depend on `mortelos/framework`. Three ways to let the
host implement framework's contract without forcing the full framework as a
hard dependency:

- **(a) Mirror the contract** in the host (skill generates a host-side
  resolver matching the interface shape). Lightest; risks drift.
- **(b) Shared `mortelos/tenancy-contracts` package** consumed by both
  framework and the host. Cleanest; needs a new package + framework refactor.
- **(c) Framework as an optional dependency** the skill adds when the portal
  needs operate-mode anyway. Heaviest for build-only portals.

R1 decides this before the seam is finalized. The same question gates how the
host mirrors `Mortel\Access\Role`/`ActorContext` for role-gating (R4).

---

## 5. Part A — The strip

Goal: a clean single-tenant boot with no dead tenancy code, and a role-gating
replacement that is config/data-driven and framework-aligned (per D11). The UI
ceremony removals (§5.1 items 1-5) are independent deletions. The role and
governance lookups (§5.1 items 6-7) are **not** bare deletions: they are
replaced by the policy layer (D11, §5.4).

### 5.1 Remove

- `app/Http/Controllers/Auth/TenantSelectController.php`
- `auth.tenant-select` + `auth.tenant-store` routes (`routes/starter.php`)
- `tenant_select` contract key in `config/starter.php` and its
  `StarterDoctor.php:22` check
- The `session('tenant_id')` boot redirect in `routes/starter.php:23-26`
- The org switcher block in `topbar.blade.php` (the `tenants()` iteration)
- The `tenants()->wherePivot()` role lookup in `dashboard.blade.php:23-30`
  (replaced by the policy layer per D11 / §5.4, not a bare deletion)
- The `session('tenant_id')` argument in `governance.blade.php:76`

### 5.2 Replace

- Boot: `routes/starter.php` `home` → straight to
  `post_login_redirect_resolver` (no tenant-select hop).
  `PasswordLoginController` and `login.blade.php` redirect to `home`/dashboard.
- `ResolvePostLoginRedirect::execute()` signature drops the `$tenantId` param
  (or keeps it nullable per R1).
- Dashboard role-gating: route through the config/data-driven policy layer
  (D11, §5.4) instead of `in_array($role, ['owner','admin'])`. How it mirrors
  framework's `Role`/`ActorContext` and policy-check contract is R4/R7.

### 5.3 Docs to update

`AGENTS.md` (§3 boot baseline, §10 verification, §12 don'ts), `README.md`,
`knowledge/` notes, and the `portal-kickoff` skill — all currently describe the
`login -> tenant-select -> dashboard` flow.

### 5.4 Roles, policies & audit (config/data-driven)

The strip also replaces the one hardcoded gate with the layer from D11/D12:

- **Roles & policies as data.** A roles/policies table holds which role may do
  what, editable by the portal owner via an admin screen. Deny-by-default.
- **Gates route through it.** The dashboard gate and governance check a policy
  layer (an `ability`/`can` check), not hardcoded role names. The shape mirrors
  framework's `default_policies` so framework's engine can later take over with
  stable call-sites.
- **Audit via optional event-sourcing (D12).** Changes are recorded through an
  event-store that follows the active connection: central DB single-tenant,
  tenant DB once stancl is added, same code and no event migration. The starter
  reuses framework's event-store convention (the `events` table shape plus a
  connection-following stored-event model) rather than a divergent store.
- **Mirroring shape = R6 (event-sourcing) + R7 (policy-check contract).**

---

## 6. Part B — The `add-tenancy` skill

A driver-based skill that turns a single-tenant starter into a multi-tenant one
by binding onto framework's contract. The skill chooses **one** isolation
driver per portal (D4).

### 6.1 What it wires

- Adds `stancl/tenancy ^3.10`.
- Binds a host `TenantResolver` implementation compatible with
  `Mortel\Contracts\TenantResolver` (per R1).
- Driver `database` (default): Stancl connection swap, tenant migrations under
  `database/migrations/tenant`, central vs tenant connection config.
- Driver `row`: single DB, scoped models via global scope, explicit list of
  shared (un-scoped) tables.
- Tenant identification: subdomain / path / request — **decided by R3** (must
  match how framework initializes the tenant in operate mode).
- Role: hooks onto framework's `Role`/`ActorContext` (per D8).
- Reinstates the tenant switcher UI only when a user belongs to >1 tenant.
- Generates tests (boot + tenant isolation) and updates docs.

### 6.2 Invocation shape (draft)

```
add-tenancy --isolation=database   # default
add-tenancy --isolation=row --shared=users,settings
```

---

## 7. Research gate (subagent tasks, BLOCKING)

Read-only investigation that must complete before the seam shape (Part A
finalization) and the skill (Part B) are locked.

| # | Task | Output |
|---|------|--------|
| R1 | How the host implements `Mortel\Contracts\TenantResolver` without forcing framework as a hard dep: mirror vs shared `tenancy-contracts` package vs optional dep | Recommendation + chosen coupling |
| R2 | Verify `stancl/tenancy ^3.10` setup for **both** database-per-tenant and single-DB row-scoped tenancy | Config recipes for each driver |
| R3 | How `mortelos/framework` initializes/identifies the tenant in operate mode (MCP, OAuth, subdomain?) | Tenant-identification contract |
| R4 | `Mortel\Access\Role`/`ActorContext`/`ActorResolver`: how the host mirrors role-gating in single-tenant without spatie | Role seam shape |
| R5 | Per-package audit (`chat`, `channel-*`, `widget-*`, `dev-tools`) for tenant assumptions that touch the host | Confirmed "no host impact" or exceptions |
| R6 | Event-sourcing layer decoupled from the tenant-runtime: reuse framework's event-store convention (`UteqStoredEvent` follows the default connection) so one store works central (single-tenant) and tenant (multi-tenant) with no event migration. Mirror vs framework extraction? | Event-sourcing seam shape |
| R7 | Is there a clean policy-check contract to mirror cheaply (like `TenantResolver`)? Inspect `ContextAccessResolver` / `CheckPolicy` / `AccessActor` | Policy-layer seam shape |

---

## 8. Linear breakdown (under team Uteq)

Parent issue: **De-tenant starter + add-tenancy skill**. Sub-issues:

1. Research gate R1-R7 (can fan out to subagents)
2. Strip: remove tenant scaffolding (deletions, §5.1)
3. Strip: clean boot flow (§5.2)
4. Strip: roles/policies layer + owner admin screen, deny-by-default (§5.4, D11)
5. Strip: optional event-sourcing audit layer, connection-agnostic (§5.4, D12)
6. Strip: extend BootSmokeTest to cover logged-in dashboard render (§9)
7. Strip: docs update (§5.3)
8. Skill: `add-tenancy` scaffold + `database` driver
9. Skill: `row` driver + shared-table handling
10. Skill: framework-contract binding + role/policy hook
11. Skill: generated tests + docs
12. Skill: eval/dry-run on a throwaway portal

Execution: subagents per sub-issue; the research gate (1) runs first and
informs 3-5, 8-10.

---

## 9. Testing & verification

- **Extend `BootSmokeTest`** to log in the seeded admin and assert the
  dashboard renders 200. This is the regression that would have caught the dead
  scaffolding; it is a required AC of the strip.
- `php artisan starter:doctor` stays green (drop the `tenant_select` key check).
- `vendor/bin/pest` green; `vendor/bin/pint --dirty` clean.
- Skill ACs: a generated multi-tenant portal isolates data per tenant
  (database driver: separate connections; row driver: scoped queries) and
  passes a tenant-isolation test.

---

## 10. Risks & open questions

- **R1 drift:** mirroring framework's contract risks divergence if framework
  changes it. A shared contracts package is safer but heavier. → R1.
- **Role/policy seam without framework installed:** the bare starter has no
  `Mortel\Models\Role`. The config/data-driven policy layer must be
  framework-shaped yet self-contained. → R4/R7.
- **Event-store convention drift:** the audit/event-sourcing layer must reuse
  framework's convention (a default-connection-following store). A divergent
  store would re-create the "two stores" problem at the multi-tenant
  transition. → R6.
- **Tenant identification mismatch:** if the skill picks an identification
  strategy that conflicts with framework's operate-mode resolution, portals
  break when framework is added. → R3.
- **Row-scoped + framework:** framework is database-per-tenant only. A
  row-scoped portal cannot later adopt framework operate-mode without
  migration. Document this trade-off in the skill.
