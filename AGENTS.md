# Agent Instructions — mortelos/starter

You are an AI coding agent working on a **MortelOS Starter** project — a Laravel
application template for AI-driven portal builds on the TALL stack. This file
is the single source of truth for every agent (Claude, Codex, Cursor, Windsurf,
generic LLM); `CLAUDE.md`, `.cursor/rules/`, and `.windsurfrules` all point here.

**This repo is a runnable Laravel application**, not a library. You bootstrap a
new portal with:

```bash
composer create-project mortelos/starter mijn-portal
```

…and you get a working Laravel app with the MortelOS shell already wired:
login, tenant select, dashboard, inbox, governance, users, settings, plus a
seeded admin account. From there you assemble portal capabilities on top.

The runtime **operate mode** (chat, agent tools, governance approvals) runs
through the MortelOS MCP server in `mortelos/framework`, not through this
codebase.
Default to **build mode**: assemble portals from MortelOS primitives.

## 1. Read this in order

1. `README.md` — installation, contract tables, agent prompts that work
2. `docs/ai-architecture.md` — AI build patterns for this starter
3. `docs/building-portals.md` — the design method (§1–§11)
4. `knowledge/` — short, AI-first notes per topic (start at `knowledge/README.md`)
5. `.agents/skills/setup-portal/` — guided kickoff for agents that support local skills
6. This file — the rules below

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
Tests & release     → host wiring + capability tests                 (§11)
```

Never embed domain rules in Blade or Livewire components. Push them behind
actions, projections, policies, resolvers or package services.

## 3. What's already wired (the boot baseline)

Out of the box this app boots `/` → `/login` → `/auth/tenant-select` →
`/dashboard` with a seeded admin account
(`admin@example.test` / `password`). All five `auth.*` contract keys point at
working stubs under `app/Http/Controllers/Auth/` and
`app/Actions/Auth/ResolvePostLoginRedirect.php`. Replace each stub with a real
implementation as your portal's auth flow gets specified.

| Contract key | Default class |
| --- | --- |
| `auth.post_login_redirect_resolver` | `App\Actions\Auth\ResolvePostLoginRedirect` (returns `/dashboard`) |
| `auth.controllers.password_login` | `App\Http\Controllers\Auth\PasswordLoginController` (email + password) |
| `auth.controllers.passkey_authenticated` | `App\Http\Controllers\Auth\PasskeyAuthenticatedController` (501 stub; replace) |
| `auth.controllers.accept_invitation` | `App\Http\Controllers\Auth\AcceptInvitationController` (501 stubs; replace) |
| `auth.controllers.tenant_select` | `App\Http\Controllers\Auth\TenantSelectController` (auto-picks single tenant) |

Optional resolvers (`navigation.sidebar_resolver`,
`navigation.universal_search_resolver`, `governance.resolver`,
`users.resolver`, `onboarding.resolver`, `inbox.item_type_resolver`, etc.) are
all `null` by default and degrade silently. Fill them as the capability map
calls for them.

Verify with `php artisan starter:doctor`. Green means the boot baseline is
intact, including the MortelOS `events` table and event-sourcing config.

## 4. Primitives (the only things you should be assembling)

| Concept | Primitive | Lives in |
| --- | --- | --- |
| Customer, project, document, dossier | **Entity** | `mortelos/framework` |
| Customer owns dossier, document belongs to project | **Entity link** | `mortelos/framework` |
| User uploads document, connector syncs invoice | **Event** | `mortelos/framework` (`spatie/laravel-event-sourcing` under the hood) |
| Portal-ready dossier overview | **Projection** | host or package; rebuild via `php artisan mortel:projection:rebuild --type=<…>` |
| Integration boundary around CRM, finance, mail, AI | **Connector** | dedicated package (see `mortelos/entity-graph` for shape) |
| Role can view or change something | **Policy** | host or package; governed through Policy Studio |
| Per-tenant or per-customer behavior toggle | **Tenant config / package config** | host `config/` or `config/<package>.php` |
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

### Vendor package edit guard

Treat Composer-installed MortelOS packages as read-only while working in a
starter or host app. This includes `vendor/mortelos/*` and any local path repo
or worktree for a `mortelos/*` package, unless the current task is explicitly a
package PR.

- Agents may inspect MortelOS package source to understand contracts,
  extension points and bugs.
- Portal implementation changes start host-side, preferably under `app/`.
  Add host wiring in `config/`, `routes/`, `resources/`, `database/` or
  `tests/` only when the capability needs it.
- Do not patch `vendor/mortelos/*` or a checked-out `mortelos/*` package from
  a starter task.
- Change a MortelOS package only when the package truly owns the bug or missing
  extension point, there is no reasonable host-side override or resolver, and
  the change is made in the package's own branch with tests and a pull request.
- If a package change is needed, stop the host edit path, explain why host-side
  wiring is insufficient, then make or request a dedicated package PR.

## 6. TALL stack conventions (Livewire 4 SFC + Flux UI first)

The starter ships Livewire **4 single-file components** under
`resources/views/livewire/`. New portal pages follow the same shape.

| Rule | Why |
| --- | --- |
| Always check **Flux UI** first before writing custom Alpine/Tailwind | Flux Pro is the design system; custom components fragment the look |
| **Livewire 4 SFC** for new components, not class-based v3 style | Single file = co-located state, view, lifecycle |
| **Pest** is the test framework (`vendor/bin/pest`) | Architecture + feature tests run through Pest |
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
`mortelos/framework` package exposes the MortelOS MCP server. A host mounts it
in `routes/ai.php`:

```php
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();
Mcp::web('/mcp/mortelos', config('mortelos.mcp.server'))
    ->middleware(['auth:api' /* + tenancy, trust, classification, throttling */]);
```

Access is OAuth 2.1 + tenant-scoped + policy-governed. **MCP is for operating a
running workspace, not for portal bootstrap.** Portal kickoff is artisan + code
+ config edits.

Details: `knowledge/06-mcp-runtime.md`.

## 9. Agent-specific entry points

| Agent | Entry | Notes |
| --- | --- | --- |
| Claude Code | `CLAUDE.md` → here + `.claude/skills/setup-portal/` | `.claude` is a symlink to the canonical `.agents` skill |
| Codex (OpenAI) | `AGENTS.md` (this file) + `.agents/skills/setup-portal/` when supported | Use the `setup-portal` skill first on any new portal request |
| Cursor | `.cursor/rules/mortelos.mdc` → here | Always-on rule pointing at this file |
| Windsurf | `.windsurfrules` → here | Same as Cursor |
| Generic LLM via README | `README.md` → "For AI agents" section → here | When a user pastes the README into a fresh chat |

## 10. Verify before claiming done

Every change that touches host behavior gets a verification checklist (see
`knowledge/07-test-and-verify.md`):

1. **Boot smoke test** — `login → dashboard` returns 200 for
   the seeded admin
2. **Doctor command** — `php artisan starter:doctor` reports green, including
   event-store wiring
3. **Pest** — `vendor/bin/pest` is green (16+ baseline assertions, growing
   per capability)
4. **Manual URL + test account** — paste URL + account credentials so the
   human can verify in seconds
5. **Pint** — `vendor/bin/pint --dirty` is clean

If any of those is skipped, say so explicitly in the handoff. Do not claim
"works" on the basis of `composer validate` alone.

## 11. Troubleshooting (top symptoms)

| Symptom | Cause | Fix |
| --- | --- | --- |
| `LogicException: Missing starter route class config [...]` | An `auth.controllers.*` key is `null` in `config/starter.php` | Fill it; see §3 |
| `database table events is missing` | Event-store migration did not run | Run `php artisan migrate` and check `database/migrations/2026_03_26_000003_create_events_table.php` |
| Vite manifest not found | `npm install && npm run build` not run | `npm install --ignore-scripts && npm run build` |
| `View [layouts.guest] not found` | `resources/views/layouts/guest.blade.php` got removed | Restore it from git; the login page expects it |
| Sidebar/search/chat missing | Matching resolver still `null` in config | Optional; fill when the capability needs it |
| `mortelos/ui` not installable | Private package, vcs repo in `composer.json` not honored | Ensure SSH access to `github.com/mortelos/ui` or add the vcs repo |

Extended list: `knowledge/08-troubleshooting.md`.

## 12. Don't

- Don't invent a tenant/membership/role model in the portal hastily — that is
  **host-owned** (§9 of `docs/building-portals.md`). The stub `TenantSelectController`
  is a placeholder; replace it once the membership model is in place
- Don't write domain rules inside Blade or Livewire components
- Don't add a new feature without recording a package decision
- Don't replace `mortelos-starter::layouts.app` with a custom layout; extend it
- Don't bypass policies with component-level conditionals
- Don't modify `vendor/mortelos/*` or local `mortelos/*` package worktrees from
  a starter task; package changes require a dedicated package branch + PR and
  only after host-side options are exhausted
- Don't hardcode host-specific classes inside packages — use
  config and resolver contracts
- Don't claim a portal "works" without the verification checklist (§10)
- Don't use em-dashes in Dutch prose (project convention)

## 13. Reference host apps

Use internal MortelOS host apps for shape and intent when you need a complete
example beyond what this template ships. Treat them as examples, not naming
sources: new starter documentation should use `mortelos/framework`,
`mortelos/chat`, `mortelos/ui` and `mortelos/starter`.
