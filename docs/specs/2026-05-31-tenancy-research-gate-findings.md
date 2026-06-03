# Research gate R1-R7 findings

- **Date:** 2026-05-31
- **Source:** parallel read-only investigation of `mortelos/framework` and the 12 `mortelos/*` packages (8 agents)
- **Companion to:** `2026-05-31-tenancy-strip-and-add-tenancy-skill-design.md`

Note: the research agents cloned the repos fresh and did not have this branch's spec file, so their incidental observations about the starter are shallower than the spec's documented dead-scaffolding findings. The spec's findings stand.

## Headline

Nothing in the recommended path touches another repo. Every recommended option is host-side (`touches_other_repos: false`). The two options that would have changed `mortelos/framework` (R1 shared contracts package, R6 turnkey provider) are both rejected as unnecessary. The implementation can run fully host-side.

## Per item

### R1, implement TenantResolver without a hard framework dependency (DECISION)
Recommendation: option (c). The override seam already exists: framework binds `NullTenantResolver` only `if (! $this->app->bound(TenantResolver::class))` (`MortelServiceProvider.php:83-84`). The add-tenancy skill generates `app/Support/StanclTenantResolver.php` implementing the real `Mortel\Contracts\TenantResolver` at build time; framework goes under composer `suggest`. Zero framework change. Reject (a) mirror under `App\Contracts` (framework injects by exact FQCN, typed ctor at `Meta/OsContext.php:19`). Defer (b) shared package as YAGNI.

### R2, stancl driver recipes (FACT plus one-way-door decision)
stancl v3.10 supports both database-per-tenant (connection swap, default) and single-DB row-scoping out of the box; the discriminator is the `bootstrappers` array (row mode drops `DatabaseTenancyBootstrapper`). Both modes need identification middleware so `tenancy()->initialized` is true, and both keep a central `tenants` table. One-way door: framework is connection-swap only (no `BelongsToTenant` anywhere; `LearningPattern.php:22`), so a `row` portal cannot later adopt framework operate-mode without a data migration. Recommendation: default `database`; offer `row` only for lightweight portals that will never run framework, with the foreclosure documented.

### R3, tenant identification (FACT plus product call)
Framework is ambient and fails closed: it never parses host/subdomain/path/header; it reads the already-initialized tenant via stancl helpers + `TenantResolver->id()`. HTTP and MCP both funnel through `ActorResolver` to authed user + `TenantResolver::id()`. The host must supply the identification and initialization layer. Recommendation (product call): slug-keyed web identification (framework's `ResolvesTenant` supports slug) plus a token `tenant_id` claim for MCP, both ending in `tenancy()->initialize()` ordered before framework's `ResolveRole`/`EnforcePolicy`.

### R4, role-gating seam (DECISION, host-side)
Premise correction: there is no `Mortel\Access\Role`; the role is `Mortel\Models\Role` (tenant connection; the gate needs only `name` + `trust_config`). There is no Role/Actor contract to bind, and framework maps `Mortel\ -> src/` via PSR-4, so a host shim under `Mortel\*` would fatal on duplicate class once framework installs. Recommendation: host-owned seam under `App\Access\{AccessActor,ActorContext,SystemActor,DeniedActor}` + `App\Enums\TrustLevel`, mirroring framework signatures 1:1, fail-closed default `DeniedActor`, routed through one chokepoint in `StarterServiceProvider`. Framework adoption is then a mechanical find-replace `App\Access` to `Mortel\Access`, not a binding flip. Role storage sub-decision: research recommends (A) config-driven (`role` column + `config('access.roles')` map). See the reconciliation note below; this conflicts with the owner-editable requirement.

### R5, consumer-package tenant assumptions (FACT)
No consuming package imports the host `App\` namespace (empty grep across all 12 repos). All tenant access is mediated by framework. The strip can remove host tenancy freely. Package tiers for the skill's tenancy gate:
- Zero-tenancy-safe: `ui`, `widget-document-feedback`, `widget-compliance`, `dev-tools`.
- Degrades gracefully: `overviews` (org_id becomes null).
- Requires the full framework tenancy stack: `chat` (mutates tenancy config at boot, queries `tenant_settings`) and all 6 channels.

### R6, one event store, both modes (FACT, host-side)
`StoredEvent` sets no `$connection`, so it follows `config('database.default')`: central single-tenant, stancl-swapped multi-tenant. One store, one `events` table, no event migration. Framework does not auto-wire this (the provider is a no-op stub). The host wires five pieces: `config/event-sourcing.php`, `StoredEvent::observe(StoredEventObserver)`, the events migration in `database/migrations` (so default-connection migrate runs it), the `class_alias` bridge, and reliance on the no-connection model. Recommendation: keep wiring host-owned (zero framework change).

### R7, policy-check seam (FACT plus product call)
Two seams. The check path is decoupled and cheaply host-mirrorable: `CheckPolicy` is a concrete class reading `Policy::all()` on the default connection (only policy population is event-sourced). `ContextAccessResolver` is more coupled and overkill for the entity-less shell. Recommendation: mirror only the page-gate seam, a host `App\Contracts\GovernanceGate` (`canManage(?Authenticatable): bool`) with a safe default, normalizing framework's two divergent signatures. Reuse `Mortel\Actions\Policies\CheckPolicy` unchanged when framework is present. Default rule sub-decision: config allowlist (recommended) vs pivot-role.

## Checkpoint (decisions for review)

1. **R3 tenant-identification:** slug-keyed web + token-claim MCP. New product call.
2. **R4/R7 role and policy storage:** research recommends config-driven for minimalism, but spec D11 requires owner-editable DB data with an admin screen. Reconciliation: keep the research's framework-aligned SHAPE (`App\Access\*` seam, `GovernanceGate`, `CheckPolicy` reuse); storage follows D11 (DB tables + screen); event-sourcing is the audit layer (D12/R6).
3. **Confirm-only (no change):** R1 (c) host-generated resolver, R2 `database` default with row foreclosure documented, R6 host-owned event-sourcing. None touch framework.

## Reconciliation note (R4 storage vs D11)

The research, optimizing for a minimal seam and without this branch's spec, recommends config-driven role storage (option A). Spec D11 (owner-editable roles/policies via an admin screen) requires DB storage (closer to option B). D11 stands unless reconsidered: the SHAPE recommendation (`App\Access\*`, find-replace adoption) is adopted regardless; only the storage backend differs.
