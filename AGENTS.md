# Agent Instructions — mortelos/starter

You are an AI coding agent working on a **host application** that consumes the
`mortelos/starter` Composer package. This file is the single source of truth for
every agent (Claude, Codex, Cursor, Windsurf, generic LLM); `CLAUDE.md`,
`.cursor/rules/`, and `.windsurfrules` all point here.

**This repo is the package itself**, installed in host apps as
`vendor/mortelos/starter`. The starter does not boot standalone — it provides
the application shell (login, tenant select, dashboard, inbox, governance,
users, settings) that a host app wires together.

Default to **build mode**: assemble portals from MortelOS primitives. The runtime
**operate mode** runs through the UteqOS MCP server, not through this codebase.

## 1. Read this in order

1. `README.md` — installation, wiring contract tables, agent prompts that work
2. `docs/building-portals.md` — the design method (§1–§11)
3. `knowledge/` — short, AI-first notes per topic (start at `knowledge/README.md`)
4. `.claude/skills/portal-kickoff/` — Claude-only guided kickoff (skip for other agents)
5. This file — the rules below

If the user asks "build a portal for X" and gives only that, **do not invent the
plan in your head**. Run the interview (capability-first) before writing any
code; see `docs/building-portals.md` §1 and `knowledge/02-primitives.md`.

## 2. Mental model (capability-first, not page-first)

```text
User describes a capability ("customers can upload documents")
        │
        ▼
Capability map      → what each role can do, which data, which approvals
        │
        ▼
Package decision    → package-now / package-ready / workspace-only  (§2)
        │
        ▼
Domain model        → entities, links, events                        (§3)
        │
        ▼
Projections         → portal-ready read models                       (§4)
        │
        ▼
Connectors          → integration boundary for external systems      (§5)
        │
        ▼
Surfaces & widgets  → starter page, package route, dashboard, chat   (§6)
        │
        ▼
Policies            → deny-by-default abilities                      (§7)
        │
        ▼
Workflows           → inbox approvals for risky/human-reviewed action (§8)
        │
        ▼
Observability       → why did this appear/disappear/fail?            (§10)
        │
        ▼
Tests & release     → host-wiring tests + package tests              (§11)
```

Never embed domain rules in Blade or Livewire components. Push them behind
actions, projections, policies, resolvers or package services.

## 3. Foundation wiring contract (boot minimum)

The host app boots once these three edits and five `auth.*` keys are in place.
Everything else is optional and degrades silently.

| # | Edit | Where |
| - | --- | --- |
| 1 | Route bridge: host `routes/web.php` requires host `routes/starter.php`, which requires `vendor/mortelos/starter/routes/starter.php` | host `routes/` |
| 2 | Layout delegation: host `resources/views/layouts/app.blade.php` includes `mortelos-starter::layouts.app` | host `resources/views/` |
| 3 | Config merge: host `config/starter.php` starts from package defaults via `array_replace_recursive`, fills auth bindings | host `config/` |

| Auth contract key | Expected shape |
| --- | --- |
| `auth.post_login_redirect_resolver` | `execute(User $user, string $tenantId): string` |
| `auth.controllers.password_login` | Invokable controller for POST login |
| `auth.controllers.passkey_authenticated` | Controller for passkey login POST |
| `auth.controllers.accept_invitation` | Controller with `show()` and `store()` |
| `auth.controllers.tenant_select` | Controller with `show()` and `store()` |

If any of those five is `null`, `routes/starter.php:13` throws
`LogicException: Missing starter route class config [...]`. Fill them, run
`php artisan starter:doctor` (see `knowledge/07-test-and-verify.md`), then load
the app: `login → tenant-select → dashboard` must work end-to-end.

The package ships **runnable stubs** under `stubs/` that get a host booting in
minutes; publish them with:

```bash
php artisan vendor:publish --tag=mortelos-starter-stubs
```

See `knowledge/07-test-and-verify.md` for what to verify after publishing.

## 4. Primitives (the only things you should be assembling)

| Concept | Primitive | Lives in |
| --- | --- | --- |
| Customer, project, document, dossier | **Entity** | `uteq/mortel` |
| Customer owns dossier, document belongs to project | **Entity link** | `uteq/mortel` |
| User uploads document, connector syncs invoice | **Event** | `uteq/mortel` (spatie/laravel-event-sourcing under the hood) |
| Portal-ready dossier overview | **Projection** | host or package; rebuild via `php artisan mortel:projection:rebuild --type=<…>` |
| Integration boundary around CRM, finance, mail, AI | **Connector** | dedicated package (see `mortelos/entity-graph` for shape) |
| Role can view or change something | **Policy** | host or package; governed through Policy Studio |
| Per-tenant or per-customer behavior toggle | **Tenant config / package config** | host config or `config/<package>.php` |
| Reusable interactive task surface | **Chat widget** | package; registered via `WidgetRegistry` |
| Per-page reusable Livewire block | **Page widget** | package or host |
| Dense operational card | **Dashboard widget** | host or package; registered in `dashboard.primary_widgets` / `secondary_widgets` |

Deeper notes per primitive: `knowledge/02-primitives.md`.

## 5. Package-first governance (mandatory)

Before implementing a new surface, decide its package boundary. Default
assumption: **if it can serve another MortelOS installation, it belongs in a
package**.

| Decision | Use when |
| --- | --- |
| `package-now` | Reusable across MortelOS installations today; build directly in a package |
| `package-ready` | Build app wiring now, keep the package boundary explicit, extract when stable |
| `workspace-only` | Customer-specific by design, with a concrete reason |

Record every decision. When the host has MortelOS dev tools:

```bash
php artisan mortelos:package-decision "Customer Portal" \
  --decision=package-ready \
  --surface=mortelos/customer-portal \
  --reason="Reusable shell with customer-specific tenant policy and branding." \
  --no-interaction

php artisan mortelos:package-decisions:check --require-reason --no-interaction
```

When the dev tools are not installed, append the same fields to
`.mortelos/package-decisions.md`. CI gates every PR on
`composer package-governance` or the artisan equivalent.

Worked examples: `knowledge/04-package-governance.md`,
`knowledge/05-mortelos-ecosystem.md`.

## 6. TALL stack conventions (Livewire 4 SFC + Flux UI first)

The starter ships Livewire **4 single-file components** (SFC) under the
`starter::` namespace. The host follows the same shape.

| Rule | Why |
| --- | --- |
| Always check **Flux UI** first before writing custom Alpine/Tailwind | Flux Pro is the design system; custom components fragment the look |
| **Livewire 4 SFC** for new components, not class-based v3 style | Single file = co-located state, view, lifecycle |
| **Pest** is the test framework | Architecture + feature tests run through Pest in both starter and host |
| **Action classes** under `app/Actions/<Domain>/` for write paths | Keeps domain rules out of components |
| **Pint** for formatting (`vendor/bin/pint --dirty`) | Single style across packages |
| **Deny by default** for new policies | Governance baseline (§7) |
| **No em-dashes** in Dutch user-facing copy | Project convention; see `CLAUDE.md` |

Worked patterns: `knowledge/03-tall-conventions.md`.

## 7. Surfaces (use the smallest that fits)

| Surface | Use for |
| --- | --- |
| Starter page (`starter::pages.*`) | Core shell: dashboard, inbox, users, settings, governance |
| Package route | Reusable feature workspace owned by a package |
| Dashboard widget | Dense operational overview or metric card |
| Chat widget | Interactive task, proposal, graph, form, guided workflow inside chat |
| Page widget | Reusable Livewire block embedded in a portal page |

## 8. MCP runtime (operate mode)

The runtime agent surface is **separate from this build mode**. The
`uteq/mortel` package mounts the UteqOS MCP server in the host:

```php
// routes/ai.php (host)
use Laravel\Mcp\Facades\Mcp;
use Mortel\MCP\Servers\UteqOSServer;

Mcp::oauthRoutes();
Mcp::web('/mcp/uteqos', UteqOSServer::class)
    ->middleware(['auth:api' /* + tenancy, trust, classification, throttling */]);
```

Access is OAuth 2.1 + tenant-scoped + policy-governed. **MCP is for operating a
running workspace, not for portal bootstrap.** Portal kickoff is artisan + code
+ config edits.

Details: `knowledge/06-mcp-runtime.md`.

## 9. Agent-specific entry points

| Agent | Entry | Notes |
| --- | --- | --- |
| Claude Code | `CLAUDE.md` → here + `.claude/skills/portal-kickoff/` | Use the `portal-kickoff` skill first on any new portal request |
| Codex (OpenAI) | `AGENTS.md` (this file) | No skill mechanism; follow §1 reading order and the interview flow in `docs/building-portals.md` §1 |
| Cursor | `.cursor/rules/mortelos.mdc` → here | Always-on rule pointing at this file |
| Windsurf | `.windsurfrules` → here | Same as Cursor |
| Generic LLM via README | `README.md` → "For AI agents" section → here | When a user pastes the README into a fresh chat |

## 10. Verify before claiming done

Every change that touches host behavior gets a verification checklist (see
`knowledge/07-test-and-verify.md`):

1. **Boot smoke test** — `login → tenant-select → dashboard` returns 200 for
   a seeded test user
2. **Doctor command** — `php artisan starter:doctor` reports green (or N/A
   if not installed; ship a stub in host if missing)
3. **Architecture tests** — `vendor/bin/pest --filter=Architecture` passes
4. **Manual URL + test account** — paste URL + account credentials so the
   human can verify in seconds
5. **Pint** — `vendor/bin/pint --dirty` is clean

If any of those is skipped, say so explicitly in the handoff. Do not claim
"works" on the basis of `composer validate` alone.

## 11. Troubleshooting (top symptoms)

| Symptom | Cause | Fix |
| --- | --- | --- |
| `LogicException: Missing starter route class config [...]` | An `auth.controllers.*` key is `null` | Fill it in host `config/starter.php`; see §3 |
| Routes return 404 | Route bridge not required from `routes/web.php` | Add the two `require` lines from §3 |
| Blank or unstyled page | Host layout doesn't delegate to `mortelos-starter::layouts.app` | Replace host `layouts/app.blade.php` body with the delegation |
| Sidebar/search/chat missing | Matching resolver still `null` in config | Optional; fill when the capability needs it |
| Stubs not visible after publish | Cached config or wrong tag | `php artisan config:clear && php artisan vendor:publish --tag=mortelos-starter-stubs --force` |

Extended list: `knowledge/08-troubleshooting.md`.

## 12. Don't

- Don't invent a tenant/membership/role model in the portal — that is
  **host-owned** (§9 of `docs/building-portals.md`)
- Don't write domain rules inside Blade or Livewire components
- Don't add a new feature without recording a package decision
- Don't replace `mortelos-starter::layouts.app` with a custom layout; extend it
- Don't bypass policies with component-level conditionals
- Don't hardcode `App\…`, `Mortel\…` or `Uteq\…` classes inside the package — use
  config and resolver contracts
- Don't claim a portal "works" without the verification checklist (§10)
- Don't use em-dashes in Dutch prose (project convention)

## 13. Reference host app

UteqOS is the current reference host
(`/Users/uteq/Sites/uteqos`, on this developer machine, or
`https://github.com/uteq/mortelos-uteqos` when available). It demonstrates:

- The route bridge
- A `config/starter.php` that starts from package defaults and injects host resolvers
- Layout delegation
- Host-backed `Starter*Resolver` classes in `app/Support/`
- Auth controllers in `app/Http/Controllers/Auth/`
- Architecture tests under `tests/Feature/Architecture` and `tests/Unit/Architecture`

Use UteqOS for shape and intent. Do not copy class names verbatim into a new
host; the contract is the resolver shape, not the class name.

## 14. Edit workflow for this repo

This package is consumed via symlink in development
(`vendor/mortelos/starter` → `~/Sites/mortelos-starter`). When editing here:

1. Edits land in the host app via the symlink
2. Commit **separately** in this repository (not in the host repo)
3. After service-provider or config changes, run
   `composer update mortelos/starter` in the host so autoload metadata picks
   up changes
4. Run `composer validate --strict` and `vendor/bin/pest` in this repo before
   pushing
