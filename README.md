# MortelOS Starter

Laravel application template for AI-driven portal builds on the TALL stack.
One command gets you a working Laravel app with the MortelOS shell already
wired: login, tenant select, dashboard, inbox, governance, users, settings.
From there an AI coding agent assembles portal capabilities on top.

```bash
composer create-project mortelos/starter mijn-portal
cd mijn-portal
php artisan serve
# open http://127.0.0.1:8000 and log in as admin@example.test / password
```

MortelOS is built to be extended by an AI agent, not hand-wired. You describe a
portal capability in plain language; your AI coding agent assembles it from
MortelOS's governed primitives; you review the result against the contracts
below. You stay the director and the reviewer; the agent does the wiring.

Agents work on two surfaces. A coding agent **builds** the host app from this
README (setup and portal sections below). The MortelOS MCP server lets agents
**operate** the running workspace at runtime
(see [Agent access](#agent-access-mcp)).

For the full design method an agent follows, see
[docs/building-portals.md](docs/building-portals.md).

## For AI agents (start here)

If you are a coding agent (Claude, Codex, Cursor, Windsurf, generic LLM) and the
user just asked you to build a portal:

1. Read [`AGENTS.md`](AGENTS.md) — single source of truth for every agent
2. Read [`docs/building-portals.md`](docs/building-portals.md) — the design method
3. Skim [`knowledge/`](knowledge/README.md) — short, scanbare notes per topic
   (primitives, TALL conventions, package governance, MCP runtime, troubleshooting)
4. If you are Claude, also use the
   [`portal-kickoff` skill](.claude/skills/portal-kickoff/SKILL.md), which runs
   the method as a guided workflow

## What's in the box

| What you get out of the box | What your agent assembles per portal |
| --- | --- |
| Laravel 13 + Livewire 4 + Flux UI + Pest, fully wired | Brand, copy, and which shell components are active |
| App layout `mortelos-starter::layouts.app` | Auth controller bodies (replace the working stubs) |
| Routes for login, tenant select, dashboard, inbox, governance, users, settings, onboarding, invitations, logout | Tenant model, membership, roles, invitations |
| `starter::` Livewire namespace with all shell pages | Navigation, universal search, chat resolvers |
| `app/Http/Controllers/Auth/*` stubs that boot a fresh host | Widget implementations, policy bindings, inbox data |
| Seeded admin account (`admin@example.test` / `password`) | The portal capability map and build plan |
| `php artisan starter:doctor` wiring diagnostic | Domain entities, projections, connectors |
| Pest suite covering boot, config shape, doctor, view namespaces | Portal-specific feature tests on top |

## Requirements

- PHP `^8.4`
- Laravel `^13.8` (installed as part of the template)
- Node `^20` (for Vite + Tailwind)
- SSH access to `github.com/mortelos/ui` (private dependency)

## Setup

### 1. Create the project

```bash
composer create-project mortelos/starter mijn-portal
cd mijn-portal
```

This runs `composer install`, sets an `APP_KEY`, creates
`database/database.sqlite`, runs migrations, and seeds an admin account.

### 2. Build the frontend assets

```bash
npm install --ignore-scripts
npm run build
```

For active development use `composer dev` instead — it launches `php artisan
serve`, the queue worker, `pail` logs, and `vite` in parallel.

### 3. Verify

```bash
php artisan starter:doctor    # green = boot baseline is intact
vendor/bin/pest               # 16+ baseline assertions
php artisan serve             # open http://127.0.0.1:8000
```

You should land on the login page; sign in as `admin@example.test` /
`password`; you'll be auto-routed through tenant select (single default tenant)
to `/dashboard`.

### 4. Build a portal

Open the project in your AI coding agent and describe what you want:

> Add a customer document-review portal. Customers upload documents; account
> managers review and approve them. Follow `docs/building-portals.md`: record
> the package decision, model the domain as entities, links and events, add
> the navigation and inbox resolvers, seed deny-by-default policies, and route
> approvals through the inbox.

The agent works through the MortelOS method (capability map, package decision,
domain model, projections, connectors, widgets, policies, workflows,
observability) and fills the contracts below. You review the package decision,
the policies, and the resulting surfaces, and approve changes through
governance.

Read [docs/building-portals.md](docs/building-portals.md) for the full method.
Point your agent at it as context.

### Guided kickoff skill (Claude)

This template ships a Claude skill, `portal-kickoff`, that runs the method
above as a guided workflow: it interviews for the capability map one question
at a time, records package decisions, writes a standalone build plan under
`docs/portals/<slug>/`, then builds the portal one vertical slice at a time
with a review checkpoint each step.

The skill lives at `.claude/skills/portal-kickoff/`. Because this repo is the
host app (not a library), no symlink is needed — Claude Code triggers the
skill automatically on phrases like "build a customer portal" or "customers
should be able to upload documents".

## Agent access (MCP)

Building the host app is one AI surface. Operating the running workspace is the
other. MortelOS exposes the live workspace to any MCP-capable agent so it can
search and mutate entities, run skills, trigger workflows, act on governance
approvals, and launch sub-agents without going through the web UI.

The MCP server is provided by `uteq/mortel` (when installed). Mount it in the
host:

```php
// routes/ai.php
use Laravel\Mcp\Facades\Mcp;
use Mortel\MCP\Servers\UteqOSServer;

Mcp::oauthRoutes();
Mcp::web('/mcp/uteqos', UteqOSServer::class)
    ->middleware([
        'auth:api',
        // tenancy init from MCP token, role resolution,
        // trust-level enforcement, data classification, throttling
    ]);
```

Access is OAuth-authenticated and tenant-scoped, and every call passes through
role resolution, trust-level enforcement, and data classification. An agent
only sees and changes what its grants allow. The same policies you review for
the web shell govern the MCP surface, so the runtime agent is bound by the
same contracts as the portal it operates.

## Contracts (your review checklist)

These are the contracts the agent fills, and the spec you review its output
against. The defaults in `config/starter.php` already boot a working flow;
override the local bindings as your portal grows.

### Auth (required to boot)

| Key | Expected shape |
| --- | --- |
| `auth.post_login_redirect_resolver` | `execute(User $user, string $tenantId): string` |
| `auth.controllers.password_login` | Invokable controller for password login |
| `auth.controllers.passkey_authenticated` | Controller for passkey login POST |
| `auth.controllers.accept_invitation` | Controller with `show()` and `store()` |
| `auth.controllers.tenant_select` | Controller with `show()` and `store()` |
| `auth.controllers.passkey_authentication_options` | Optional. Controller for passkey options GET |
| `auth.passkey_form_component` / `auth.password_form_component` | Optional. Blade components for the login forms |

### Layout, navigation, and search (optional)

| Key | Expected shape |
| --- | --- |
| `layout.sidebar_nav_component` / `topbar_component` / `universal_search_component` | Livewire component names rendered into the shell slots |
| `navigation.sidebar_resolver` | `sections(Model $user): array`, `inboxCount(Model $user): int`, `overviews(Model $user): array` |
| `navigation.universal_search_resolver` | `results(string $query, ?Model $user): array`, `navigation(?Model $user): array`, `saveOverview(string $query, string $name, string\|int\|null $createdBy): void` |

`sections()` returns sections of items shaped like
`['label', 'icon', 'route', 'permission', 'badge']`. Action items may set
`type => 'action'` and `action => 'browser-event-name'`. `results()` returns
`entities`, `inboxItems`, and `chatItems` arrays of
`['id', 'name'/'title', 'url', 'icon', ...]`.

### Chat (optional)

| Key | Expected shape |
| --- | --- |
| `chat.settings_service` | Service exposing `enabled(): bool` |
| `chat.conversation_panel_component` | Defaults to `chat::conversation-panel` |

The chat panel renders only when a settings service is configured, a user is
authenticated, and `app($settingsService)->enabled()` returns true.

### Dashboard, inbox, governance, users, onboarding (optional)

| Key | Expected shape |
| --- | --- |
| `dashboard.primary_widgets` / `secondary_widgets` | Arrays of Livewire widget component names |
| `dashboard.proud_message_resolver` | `resolve(): string` |
| `inbox.item_type_resolver` | `resolve(string $itemId): string` |
| `inbox.intake_detail_types` | Item types that render `livewire:inbox.intake-detail` instead of `inbox-detail` |
| `governance.resolver` / `access_resolver` | `roles(): array`, `canManage(User $user, ?string $tenantId): bool` |
| `governance.*_component` | Slots for Policy Studio surfaces (proposal queue, stats, trust config, learning patterns, channel status) |
| `users.resolver` | `canManage(): bool`, `members(): array`, `pendingInvites(): array`, `invite(string $email, string $role): void`, `revokeInvite(string $inviteId): void` |
| `onboarding.resolver` | `resolve(): array`, `complete(): void` |

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `LogicException: Missing starter route class config [...]` | An `auth.controllers.*` key is empty in `config/starter.php`. Fill it. |
| Vite manifest not found | `npm install && npm run build` not run yet. |
| `View [layouts.guest] not found` | `resources/views/layouts/guest.blade.php` was removed; restore it from git. |
| Routes return 404 | `routes/web.php` doesn't require `routes/starter.php`. |
| Sidebar, search, or chat missing | The matching resolver or component is still `null` in config. These are optional and degrade silently. |
| `mortelos/ui` not installable | Ensure SSH access to `github.com/mortelos/ui` (private dep). The vcs repository is already declared in `composer.json`. |

Extended troubleshooting: [`knowledge/08-troubleshooting.md`](knowledge/08-troubleshooting.md).

## Testing

```bash
composer test            # vendor/bin/pest
composer test:arch       # tests/Feature/Architecture only (when present)
composer format:check    # vendor/bin/pint --test
composer format          # vendor/bin/pint
php artisan starter:doctor
```

The starter ships baseline tests under `tests/Feature/`:

- `BootSmokeTest` — boot redirect, login page returns 200, `LogicException` path
  when an auth controller is unset, view namespaces and shell pages are present,
  doctor returns success
- `ConfigShapeTest` — full contract surface for auth, layout, navigation,
  governance, users, onboarding, dashboard, inbox, chat

Add capability-level tests in `tests/Feature/<Capability>/` as the portal
grows.

## Reference host app

UteqOS (`https://github.com/uteq/mortelos-uteqos`) is the most mature MortelOS
host. It demonstrates fully fleshed-out resolvers, working passkey + tenant +
governance flows, mounted MCP server, and complete architecture tests. Use it
for shape and intent when you need a real example beyond what's wired in this
template.

UteqOS still uses the older library-pattern (`vendor/mortelos/starter`). New
portals built from this template inline the shell instead. The contract tables
above and the design method in `docs/building-portals.md` are the same in both
worlds.

## Maintainer notes

- Keep `starter::` Livewire namespace conventions intact when modifying shell
  pages.
- When updating the upstream `mortelos-starter` template, version it; existing
  portals do not auto-update.
- Keep customer policy, tenant config, auth controllers, and local
  orchestration in the host app, not in the template.
- Record reusable additions as package decisions in `.mortelos/package-decisions.md`.

## License

Proprietary.
