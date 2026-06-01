# Design — MortelOS Starter as an operate-node (framework + MCP by default)

- **Date:** 2026-06-01
- **Status:** Draft (awaiting review)
- **Owner:** Nathan Jansen (UTEQ)
- **Scope:** `mortelos/starter` host app baseline + MCP runtime wiring
- **Amends:** `docs/specs/2026-05-31-tenancy-strip-and-add-tenancy-skill-design.md`
  (the "framework is opt-in" premise; see §2)

---

## 1. Goal

Make a fresh `mortelos/starter` a **MortelOS operate-node by default**: it ships
`mortelos/framework`, mounts the MCP server, and can answer MCP tool calls under
the full auth chain. A MortelOS portal is not a blank Laravel app; it is a node
of the OS, and the OS speaks through MCP. MCP is the mouth; the framework is the
body it needs.

Outcomes:

1. A fresh portal requires `mortelos/framework` as a baseline dependency and
   exposes a working MCP surface at `/mcp/mortelos` under OAuth + tenant + role +
   trust + classification middleware.
2. **Dual-engine by design:** tests and local dev run on SQLite; production OS
   runs on PostgreSQL. Embeddings (pgvector) are a toggle, off on SQLite, on for
   Postgres. No hard infra wall at `composer create-project`.
3. "Single-tenant" is redefined from *"no framework"* to *"one seeded tenant on
   the framework"*. The `add-tenancy` skill still owns the step from one tenant
   to many.

Non-goal: turning the starter into the full uteqos monolith. We ship the
framework + MCP seam and one seeded tenant, not the channel/widget/chat packages.

---

## 2. What this amends in the 2026-05-31 spec

The tenancy-strip spec concluded (R1-R7) that the framework stays **opt-in** and
the host only mirrors the `TenantResolver` contract. The OS-via-MCP requirement
overturns that single premise. The rest of that spec stands.

| 2026-05-31 spec | This spec |
|---|---|
| Framework = opt-in (added by `add-tenancy` when needed) | **Framework = baseline dependency** in `composer.json` |
| "Single-tenant" = bare SQLite, no framework | "Single-tenant" = **one seeded tenant** on the framework |
| Coupling question R1 (mirror / shared-pkg / optional-dep) | **Resolved: framework as a direct dependency** (R1 option c), because the OS surface needs it anyway |
| MCP absent from the starter | **MCP mounted by default** (`routes/ai.php`) |
| `add-tenancy` adds tenancy from scratch | `add-tenancy` unchanged in intent: it goes from **one tenant to many** (isolation driver, switcher, isolation tests) |

What does NOT change: D2-D8, D11, D12 (db-per-tenant model, framework Role shape,
deny-by-default policy-as-data, connection-agnostic event-sourcing). The strip of
the broken legacy scaffolding (`c418952`) stays stripped.

---

## 3. Feasibility (verified, not assumed)

The dual-engine path is not aspirational; the framework already implements it.

- **pgvector extension migration** guards the driver:
  `if (DB::connection()->getDriverName() !== 'pgsql') return;`
  (`mortelos-framework` `database/migrations/2026_03_26_000001_enable_pgvector_extension.php:15`).
  Skips cleanly on SQLite.
- **embedding column** degrades by driver:
  `pgsql → $table->vector('embedding')` else `$table->text('embedding')`
  (`…2026_03_27_074835_add_embedding_to_entities_table.php:15-18`).
- **ivfflat/hnsw index** guards the driver and returns on non-pgsql
  (`…2026_03_27_195144_add_ivfflat_index_to_entities_embedding.php:12`).
- **framework's own tests run on SQLite in-memory:** `phpunit.xml` sets
  `DB_CONNECTION=testing` / `DB_DATABASE=:memory:`; 34 test files pass that way.
- **uteqos (the live OS host) runs Postgres** (`.env DB_CONNECTION=pgsql`).

Conclusion: the framework is already dual-engine. The starter inherits that for
free. The earlier "Postgres-by-default kills the SQLite promise" concern was
wrong; SQLite stays the test/dev engine.

### 3.1 The real blocker — governance needs a seeded role

The MCP tools are NOT null-tenant-safe by accident of the null resolver. Every
tool calls `applyGovernance()`, which resolves a concrete DB role:

```
tool.handle()
  └─ applyGovernance()                         AppliesGovernance.php:28
       └─ resolveRole(request)                 AppliesGovernance.php:47-69
            └─ ActorContextResolver::resolveRole(userId, tenantId)
                 ├─ reads tenant_user pivot     ActorContextResolver.php:24
                 └─ reads roles table           ActorContextResolver.php:42
            └─ no role found → throw PolicyViolationException   :57-66
```

With no seeded tenant + roles, **every tool throws `PolicyViolationException`**.
So "MCP by default" requires a seeded tenant, a `tenant_user` pivot row for the
admin, and seeded roles/policies. This is the load-bearing wiring item, not the
DB engine.

---

## 3.2 W1 pilot findings (verified on branch, 2026-06-01)

W1 was piloted before fanning out W2-W4. `composer require mortelos/framework ^0.4`
(via VCS repo) installed v0.4.2 + `laravel/mcp` + `stancl/tenancy` +
`spatie/laravel-event-sourcing`; the starter still boots on SQLite. A
discriminating governance spike (single-tenant resolver → seed framework role +
`tenant_user` pivot → real `EntitySearch` visibility path) returned a
**non-denied `ActorContext` and a visible entity on SQLite — PASS**. So the
operate-node shape is provable single-tenant. The spike surfaced four spec
corrections:

- **F1 — single-tenant needs its OWN resolver, not the uteqos middleware.**
  `InitializeTenancyFromMcpToken` is the multi-tenant/stancl path (connection
  swap, Passport token `tenant_id`). Ported verbatim to a single-tenant starter
  it yields `TenantResolver::id() === null`, and `ContextAccessResolver::resolve`
  returns null when tenant is null (`mortelos-framework` `ActorContextResolver.php:66`)
  → every actor becomes `DeniedActor` → tools connect but **silently deny
  everything** (no exception). The starter must bind a trivial single-tenant
  `TenantResolver` returning one fixed tenant id. New wire point **W10**. (W4's
  MCP-token middleware is only added later by `add-tenancy` for multi-tenant.)
- **F2 — `tenant_user` is missing entirely.** It lives in framework's *landlord*
  migrations, not the tenant set the host publishes. The host must ship it (W3),
  and `user_id` must be a **string** (framework expects `string(26)`; the bare
  starter's `users.id` is an auto-increment integer — reconcile in W3).
- **F3 — framework migrations do not auto-load.** They are published via
  `php artisan vendor:publish --tag=os-core-migrations` into
  `database/migrations/tenant`. The host must run this and migrate them. W1
  substep / W3.
- **F4 — roles/policies collision (decided: framework wins).** Framework ships
  `roles`/`policies` tables (with `org_id`, `branch_id`, audit cols) that collide
  with the starter's own UTEQ-522 mirror (`roles`/`policies` + `app/Models/Role`,
  `Policy`, the 290-line `app/Access/*` layer + governance/roles admin screens).
  Remove the starter mirror, rebind the policy layer onto framework's
  `Mortel\Models\Role` + `ContextAccessResolver`. New wire point **W11**;
  amends/depends on UTEQ-522.

---

## 4. Architecture — the MCP seam

The host owns three things; the framework owns the rest. Mirror of the verified
uteqos mount (`uteqos/routes/ai.php:13-24`), rebranded for the starter.

```
┌─ mortelos/framework (baseline dep) ─────────────────────────┐
│  config('mortelos.mcp.server')   → MortelOS MCP server class │
│  18 MCP tools (entity, governance, agent, skill, workflow…)  │
│  ResolveRole / EnforceTrustLevel / DataClassification        │  ← already exist
│  TenantResolver contract + NullTenantResolver default        │
│  dual-engine migrations (pgvector guarded)                   │
└───────────────────────────────────────────────────┬─────────┘
                                       mount + dep   │
┌─ mortelos/starter (host, stays thin) ──────────────▼─────────┐
│  composer.json    require mortelos/framework                  │
│  routes/ai.php    Mcp::oauthRoutes();                         │
│                   Mcp::web('/mcp/mortelos', config(...))      │
│                       ->middleware([... chain ...]);          │
│  bootstrap/app.php  register ai.php route file                │
│  Passport          api guard + oauth migrations + tenant_id   │
│  Tenant model      App\Models\Tenant + tenant_user pivot      │
│  Seeder            1 default tenant + admin pivot + roles      │
│  config/mortel     embeddings toggle (off on sqlite)          │
└───────────────────────────────────────────────────────────────┘
```

### 4.0 Two layers — fixed OS skeleton vs opt-in connectors

The MCP surface is two layers, and they reach the agent by different paths. This
is why a fresh portal never shows a phantom "Moneybird" action.

```
LAYER 1 — OS skeleton          LAYER 2 — connectors (Moneybird, Gmail, Drive…)
─────────────────────          ───────────────────────────────────────────────
the 18 MCP tools               separate mortelos/channel-* packages, per portal
framework-owned, always on     register a ConnectorDataRequestProvider
stable tool contract               │
talk to entities/governance/        ▼
agents                         ConnectorDataRequestProviderRegistry
                                   │  "who can answer this external-data request?"
                                   ▼
                               AnswerExternalDataRequest (agent/chat asks)
```

Verified: Moneybird ships `MoneybirdRevenueDataRequestProvider`
(`channel-moneybird/src/`) which binds into the registry via its service
provider. It is **not** a 19th MCP tool. An agent reaches external data by asking
an external-data question; the registry routes it to whichever installed
connector `supports()` it. No installed connector → registry finds nobody → no
data. No empty promise in the tool list.

**Decision (D-MCP-1):** the 18 tools are the fixed default skeleton (LAYER 1).
Connectors are **opt-in per portal** (LAYER 2): they appear only when their
`mortelos/channel-*` package is installed, and they plug into the registry
without touching the MCP mount. A bookkeeping portal adds `channel-moneybird`; a
dossier portal does not. The starter ships LAYER 1 complete and LAYER 2 empty.

### 4.1 Middleware chain (host-owned items marked)

```
Mcp::web('/mcp/mortelos', config('mortelos.mcp.server'))->middleware([
  'auth:api',                          // 1. OAuth (Passport)        ← HOST
  InitializeTenancyFromMcpToken::class,// 2. tenant from token       ← HOST
  ResolveRole::class,                  // 3. role                    ← framework ✓
  EnforceTrustLevel::class,            // 4. trust                   ← framework ✓
  DataClassification::class,           // 5. classification          ← framework ✓
  'throttle:api-v1',                   // 6. rate limit              ← Laravel ✓
]);
```

Schakel 2 (`InitializeTenancyFromMcpToken`) stays host-owned because it binds to
`App\Models\Tenant` (host model). In the single-tenant baseline it resolves to
the one seeded tenant; after `add-tenancy` it resolves the active tenant from the
token's `tenant_id`. The `TenantTokenResolver` that backs it already lives in the
framework (`src/Access/TenantTokenResolver.php`) and is host-agnostic.

---

## 5. Wire points (what build must do)

| # | Item | Where | Notes |
|---|------|-------|-------|
| W1 | Add `mortelos/framework` to require | `composer.json` | Baseline dep; pulls `laravel/mcp`, `stancl/tenancy`, event-sourcing |
| W2 | Add Passport | `composer.json` + `config/auth.php` | New `api` guard (driver=passport); publish oauth migrations; `tenant_id` on `oauth_access_tokens` (mirror uteqos migration) |
| W3 | `App\Models\Tenant` + `tenant_user` pivot | `app/Models` + migration | Host-owned tenant model; `User::tenants()` belongsToMany |
| W4 | `InitializeTenancyFromMcpToken` middleware | `app/Http/Middleware` | Port from uteqos; resolves seeded tenant when token carries none |
| W5 | `routes/ai.php` + register in bootstrap | `routes/`, `bootstrap/app.php` | Mount server + middleware chain (§4.1) |
| W6 | Default seeder: 1 tenant + admin pivot + roles/policies | `database/seeders` | **Load-bearing** (§3.1): without it every tool throws. Deny-by-default per D11 |
| W7 | Embeddings toggle | `config/mortel.php` | `embeddings.enabled` default false; on = pgsql + pgvector. Semantic tools (`AskTool`) degrade gracefully when off |
| W8 | Doctor + smoke coverage | `StarterDoctor`, `tests/Feature` | Smoke: `login → dashboard` (SQLite); MCP boot smoke; doctor checks framework bound + MCP route registered |
| W9 | `AgentRun` graceful degrade | `mortelos/framework` `AgentRunTool` | Return "agent runtime not enabled" when no queue worker, instead of queueing a dead job (§7.1). Framework-side tweak |
| W10 | Single-tenant `TenantResolver` binding | `app/Access` + provider | **Load-bearing (F1).** Bind a trivial resolver returning one fixed tenant id so governance yields a non-denied actor. Without it every tool silently denies. Replaces the null-resolver in single-tenant; `add-tenancy` later swaps in the stancl-backed resolver |
| W11 | Remove starter roles/policies mirror; rebind onto framework | `app/Access`, `app/Models`, migrations, governance/roles screens | **F4.** Delete the UTEQ-522 mirror (`roles`/`policies` migrations, `App\Models\Role`/`Policy`, 290-line `app/Access/*`); rebind dashboard/governance gates onto `Mortel\Models\Role` + `ContextAccessResolver`. Amends UTEQ-522 |
| W1b | Publish + migrate framework migrations | `database/migrations/tenant` | **F3.** `vendor:publish --tag=os-core-migrations`, then migrate. Substep of the foundation |

### 5.1 Test vs production matrix

| | Tests / local dev | Production OS |
|---|---|---|
| DB engine | SQLite (`:memory:` / file) | PostgreSQL |
| Embeddings | off (text column) | on (pgvector) |
| MCP server | mounted, exercised in feature tests | mounted, live |
| Tenant | 1 seeded | 1+ (after `add-tenancy`) |

The framework's guarded migrations (§3) make this matrix work with one codebase.

---

## 6. Impact on existing work

| Artifact | Impact |
|---|---|
| Tenancy-strip spec (2026-05-31) | Premise amended (§2); strip + policy layer (D11/D12) stand |
| Research-gate R1-R7 | R1 re-decided (framework = direct dep); R2-R7 still hold |
| `add-tenancy` skill (UTEQ-526..529) | Intent unchanged (one→many tenants). The "install framework + stancl" step it does today becomes baseline; skill keeps the isolation-driver + switcher + tests |
| Smoke-portals (v028..v0212) | Re-baseline on framework dep; still SQLite, so create-project smoke stays viable |
| `setup-portal` tests (35) | Re-run on framework baseline (SQLite); expect green, verify |
| `composer create-project` promise | Preserved: boots on SQLite, no Postgres required for dev/test |

---

## 7. Decisions resolved in review

| # | Question | Decision |
|---|----------|----------|
| Q1 | Seeder shape (W6) | **Resolved:** mirror framework's `default_policies` (owner + member, deny-by-default), seeded into the one default tenant |
| Q2 | Passport vs Sanctum | **Not a choice.** Remote MCP via `laravel/mcp` requires OAuth 2.1 + dynamic client registration (`Mcp::oauthRoutes()`); only Passport provides this. Sanctum has no OAuth flow. Passport it is |
| Q3 | Which MCP tools ship | **Resolved:** mount all 18 (fixed OS skeleton, D-MCP-1 §4.0). Connectors are a separate opt-in layer, not extra tools. `AgentRun` queues a job; with no worker it returns a clear "agent runtime not enabled" error rather than being omitted |
| Q4 | `add-tenancy` overlap | **Resolved:** strip the skill's framework-install steps (now baseline); it keeps only the one→many flow (isolation driver, switcher, isolation tests) |
| Q5 | Connectors by default | **Resolved:** opt-in per portal (D-MCP-1 §4.0). No `channel-*` package ships in the baseline; a portal adds the ones it needs |

### 7.1 Follow-up: AgentRun graceful degradation (W9)

`AgentRunTool` dispatches `ExecuteAgentRunJob` onto the queue. A fresh portal has
no queue worker / agent runtime. Add a guard so the tool returns a clear
"agent runtime not enabled" response instead of silently queueing a job that
never runs. Small framework-side tweak; tracked as a new wire point (W9).

---

## 8. Sequencing (no code until this spec is approved)

```
spec approved · Linear UTEQ-538 + W1-W9 created · W1 piloted (done)
   │
   ▼
FOUNDATION (revised by F1-F4)
  W1   require framework            ✅ done (branch)
  W1b  publish+migrate framework migrations          (F3)
  W3   App\Models\Tenant + tenant_user (string user_id) (F2)
  W11  remove roles/policies mirror, rebind on framework (F4)
  W10  single-tenant TenantResolver binding          (F1, load-bearing)
  W2   Passport (api guard, oauth, tenant_id on token)
  W4   InitializeTenancyFromMcpToken (multi-tenant path, add-tenancy)
   │
   ▼
W5    routes/ai.php + bootstrap mount                 (the seam)
   │
   ▼
W6    default seeder: 1 tenant + pivot + framework roles (unblocks tools)
   │
   ▼
W7-W8 embeddings toggle + doctor/smoke + MCP smoke    (verify dual-engine)
W9    AgentRun graceful degrade (framework-side, parallel)
   │
   ▼
re-run setup-portal tests on framework baseline
```

Note: W10 (single-tenant resolver) supersedes the spec's original assumption that
W4's MCP-token middleware covers the baseline. W4 is now multi-tenant-only,
deferred into `add-tenancy`. The single-tenant baseline binds W10 instead.
