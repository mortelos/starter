# 05 — MortelOS ecosystem map

When picking where to build, know what already exists. This is the high-level
map; do not duplicate what one of these already owns.

## Core

| Package | Role |
| --- | --- |
| `uteq/mortel` | The binding agent. Entity registry, entity links, event sourcing (`spatie/laravel-event-sourcing`), projection plumbing, MCP server (`Mortel\MCP\Servers\UteqOSServer`), Laravel AI integration (`laravel/ai`), tenant primitives. |
| `mortelos/starter` | Laravel application template that ships the app shell inline: layout, auth routes, dashboard/inbox/governance/users/settings pages, `starter::` Livewire namespace, all contract defaults wired to working stubs. **This repo.** New portals start with `composer create-project mortelos/starter <slug>`. |
| `mortelos/ui` | Shared design primitives consumed by starter and packages. Flux-aligned. |
| `mortelos/dev-tools` | Artisan commands for `package-decision`, `package-decisions:check`, governance CI, scaffolding helpers. |

## Feature packages (current)

| Package | Owns |
| --- | --- |
| `mortelos/entity-graph` | API routes, views, Livewire namespace, migrations, extension contracts, a chat widget, an agent tool — for traversing and visualising the entity graph |
| `mortelos/policy-studio` | Governance UI: proposal queue, trust config, learning patterns, channel status. Proposal-first policy flow. |
| `mortelos/document-studio` | Document review pipelines |
| `uteq/chat` | Chat shell and `WidgetRegistry` for chat widgets |

Reference packages to imitate when building your own:
- For an end-to-end feature package: **`mortelos/entity-graph`**
- For a governance-heavy package: **`mortelos/policy-studio`**
- For the shell pattern: **`mortelos/starter`** (this one) — boots out of the box

> UteqOS still uses the older library-pattern (`vendor/mortelos/starter`). New
> portals built from this template inline the shell instead. The contract shape
> is identical in both worlds.

## Reference host

**UteqOS** (`https://github.com/uteq/mortelos-uteqos` or
`/Users/uteq/Sites/uteqos` on a developer machine) is the concrete reference
implementation. It demonstrates:

- The route bridge (`routes/starter.php` requires the package routes)
- A `config/starter.php` that starts from package defaults and overrides with
  host bindings
- Layout delegation (`resources/views/layouts/app.blade.php` →
  `mortelos-starter::layouts.app`)
- Host-backed `Starter*Resolver` classes in `app/Support/`
- Auth controllers in `app/Http/Controllers/Auth/`
- A working `routes/ai.php` mounting the UteqOS MCP server
- Architecture tests under `tests/Feature/Architecture` and `tests/Unit/Architecture`

Use UteqOS for **shape and intent**, not as a rule that every host uses the
same class names or tenant model. UteqOS picks `stancl/tenancy` for multi-tenant
behavior; that is one valid choice, not the only one.

## Naming targets and migration

- Today's core package: `uteq/mortel`
- Long-term naming target: `mortelos/framework`
- Dev tooling source today: `https://github.com/uteq/mortelos-dev-tools.git`
  (Composer name `mortelos/dev-tools`)
- Until the `mortelos/dev-tools` repository has matching tags, keep the
  `uteq/...` source as-is

When you see a doc say "in `mortelos/framework`", read it as `uteq/mortel`
today.

## Choosing where a new capability lives

Walk through this:

1. Does an existing package already own this concept? → extend it
2. Is the concept reusable across customers? → new package, `package-now`
3. Reusable but the host needs to ship a customer-specific binding? →
   `package-ready`: build host wiring now, extract later
4. Truly customer-specific (regulator-driven, branded workflow, one-off)? →
   `workspace-only`, host-only, with a recorded reason

If you can't answer #1 in 30 seconds, grep the repos under `~/Sites/`
(`/Users/uteq/Sites/`) for the concept before deciding it doesn't exist.

## What's not in this ecosystem

These are explicitly **out**:

- **Filament** — not the chosen admin pattern
- **Jetstream / Breeze** — auth and tenancy are owned by `uteq/mortel` + starter
- **Inertia / Vue / React** — TALL stack only
- **Class-based Livewire v3** for new code — SFC v4

If a user pastes a Filament resource or a Jetstream policy as a starting point,
translate it to the MortelOS patterns instead of using it directly.
