# MortelOS Starter

MortelOS Starter is a Laravel application template for building governed,
AI-assisted customer portals.

It gives you a working host app first: login, tenant selection, dashboard,
inbox, governance, users and settings. From there, an AI coding agent helps you
turn a portal idea into capabilities, policies, workflows and reusable packages.

Use it when you want a portal that is not just a set of pages, but an operating
workspace with clear roles, approval flows, audit history and agent access.

## The Short Version

```bash
composer create-project mortelos/starter mijn-portal
cd mijn-portal
npm install --ignore-scripts
npm run build
php artisan starter:doctor
php artisan serve
```

Open `http://127.0.0.1:8000` and sign in with:

| Email | Password |
| --- | --- |
| `admin@example.test` | `password` |

## Start With AI

Open the project in your AI coding agent. If your agent supports local skills,
start with the `portal-kickoff` skill:

```text
Use the portal-kickoff skill.
I want to build a customer portal for: [describe the customer, user group or process].
```

The skill interviews for the capability map, records package decisions, checks
the Starter foundation, writes the build plan and stops for review before the
first vertical slice.

If your agent does not support local skills, paste this prompt instead:

```text
You are working in a MortelOS Starter host app.

Read AGENTS.md, README.md and docs/building-portals.md first.
If the portal-kickoff skill is available, use it now.
Use a capability-first interview before writing code.

I want to build a customer portal for: [describe the customer, user group or process].

First, ask me focused questions until the capability map is clear:
1. Which roles use the portal?
2. What can each role view, upload, approve or trigger?
3. Which data is shown or changed?
4. Which external systems are involved?
5. Which actions need human approval?
6. Which parts should become reusable MortelOS packages?

After the interview, write a build plan before implementation.
Use MortelOS primitives: entities, entity links, events, projections,
connectors, policies, workflows, inbox items and surfaces.

Record package decisions before adding new surfaces.
Use deny-by-default policies for mutating actions and sensitive data.
Stop for review before building the first vertical slice.
```

A smaller example:

```text
Build a document review portal.
Customers upload documents. Account managers review and approve them.
The portal must show document status, route approvals through the inbox,
record audit history and expose safe agent actions through policy checks.
Follow the MortelOS method in docs/building-portals.md.
```

## Who This Is For

MortelOS Starter is meant for implementation partners, product teams and
technical operators who want to create portals with an AI coding agent.

You do not need to start from a blank Laravel app. You describe what the portal
must let people do. The agent translates that into the MortelOS shape and you
review the decisions.

| You decide | The agent wires |
| --- | --- |
| Portal goal and user roles | Routes, controllers, Livewire surfaces |
| Which data users may see or change | Entities, projections and policies |
| Which actions need approval | Inbox workflows and governance proposals |
| Which integrations matter | Connectors and sync jobs |
| What should be reusable | Package decisions and package boundaries |

## What MortelOS Is

MortelOS is an operating system model for portals. It gives every feature a
place in a governed workspace.

Instead of asking "which page do we build?", MortelOS asks:

```text
What should a user be able to do?
        |
        v
Which domain object is involved?
        |
        v
Who may see or change it?
        |
        v
Does it need approval, audit or a workflow?
        |
        v
Where should it appear: dashboard, inbox, chat, page or package route?
```

That is the main difference. Pages are only the surface. The real portal is the
set of capabilities, rules and workflows behind those pages.

## Core Concepts

| Concept | Plain meaning | Technical shape |
| --- | --- | --- |
| Host app | The Laravel app you deploy for a customer or workspace | This `mortelos/starter` project |
| MortelOS framework | The OS layer that owns entities, links, events, projections, tenant primitives and MCP runtime | Composer package `mortelos/framework` |
| Shell | The standard portal frame | Login, tenant select, dashboard, inbox, governance, users, settings |
| Capability | Something a role can do | Example: "customer uploads a document" |
| Entity | A thing the portal cares about | Customer, dossier, project, document, invoice |
| Entity link | A typed relationship between entities | Customer owns dossier, document belongs to project |
| Event | A fact that happened | DocumentUploaded, InvoiceSynced, PolicyProposed |
| Projection | A read model made for screens, search or agents | Document review status, dossier summary |
| Connector | Boundary around an external system | Moneybird, Google Drive, Fireflies, CRM, email |
| Policy | Who may see or do something | Deny by default, reviewed through governance |
| Workflow | A process with state and consequences | Review, approve, reject, reassign, notify |
| Inbox item | Human review point inside a workflow | Approval request, connector reauth, policy proposal |
| Surface | Where a capability appears | Dashboard widget, page widget, chat widget, package route |
| MCP runtime | Agent access to a live workspace | OAuth-scoped tools from `mortelos/framework` |

The current framework PHP namespace can still be `Mortel\...` during the
external package naming transition. The public package name to use in docs and
planning is `mortelos/framework`.

## What You Get Out Of The Box

| Area | Included |
| --- | --- |
| Framework | Laravel 13, Livewire 4, Flux UI, Tailwind, Pest |
| Auth baseline | Password login, invitation stub, passkey stub, tenant-select stub |
| Shell routes | `/login`, `/dashboard`, `/inbox`, `/governance`, `/users`, `/settings`, `/onboarding` |
| Layout | `mortelos-starter::layouts.app` and `layouts.guest` |
| Livewire namespace | `starter::` pages and shared shell components |
| Config contracts | `config/starter.php` with boot-safe defaults |
| Seed account | `admin@example.test` / `password` |
| Diagnostics | `php artisan starter:doctor` |
| Tests | Pest boot smoke and config shape tests |
| Agent guidance | `AGENTS.md`, `docs/building-portals.md`, `knowledge/`, `portal-kickoff` skill |

The template boots immediately. Portal-specific behavior is added through host
bindings, resolvers, actions, policies, projections and packages.

## Setup

### 1. Create The Host App

```bash
composer create-project mortelos/starter mijn-portal
cd mijn-portal
```

The create-project hook copies `.env`, generates `APP_KEY`, creates the SQLite
database file, runs migrations and seeds the admin account.

### 2. Build Assets

```bash
npm install --ignore-scripts
npm run build
```

For active development:

```bash
composer dev
```

This starts the Laravel server, queue listener, logs and Vite.

### 3. Verify The Baseline

```bash
php artisan starter:doctor
vendor/bin/pest
php artisan serve
```

Expected result:

1. `/` redirects guests to `/login`
2. Login works with `admin@example.test` / `password`
3. Tenant select auto-picks the single default tenant
4. The user lands on `/dashboard`

## Requirements

| Tool | Version or access |
| --- | --- |
| PHP | `^8.4` |
| Composer | `^2.7` |
| Node | `^20` |
| GitHub access | SSH or token access to `github.com/mortelos/ui` |

## How Portal Builds Work

MortelOS builds are capability-first. The flow is:

```text
Portal request
  |
  v
Capability map
  |
  v
Package decisions
  |
  v
Domain model: entities, links, events
  |
  v
Read models: projections
  |
  v
Integrations: connectors
  |
  v
Surfaces: dashboard, page, inbox, chat, package route
  |
  v
Policies and governance
  |
  v
Workflows and inbox approvals
  |
  v
Observability, tests and release
```

The full method lives in
[`docs/building-portals.md`](docs/building-portals.md).

## Package-First Governance

Before adding a new feature or surface, decide where it belongs.

| Decision | Use when |
| --- | --- |
| `package-now` | The capability is reusable across MortelOS installations today |
| `package-ready` | Build in the host first, but keep the package boundary explicit |
| `workspace-only` | The behavior is specific to this customer or workspace |

If MortelOS dev tools are available:

```bash
php artisan mortelos:package-decision "Document Review Portal" \
  --decision=package-ready \
  --surface=mortelos/document-review \
  --reason="Reusable document review workflow with host-specific branding and policies." \
  --no-interaction

php artisan mortelos:package-decisions:check --require-reason --no-interaction
```

If the command is not available, record the same fields in
`.mortelos/package-decisions.md`.

## Agent Skill

This repository ships a `portal-kickoff` skill for agents that support local
skills. It runs the MortelOS method as a guided workflow:

1. Interview for the capability map
2. Record package decisions
3. Verify starter foundation wiring
4. Write a standalone build plan
5. Build the first vertical slice
6. Stop for review

Skill locations:

| Path | Purpose |
| --- | --- |
| `.agents/skills/portal-kickoff/` | Canonical skill source |
| `.claude/skills/portal-kickoff/` | Symlink for Claude Code discovery |

If your agent does not support skills, paste the prompt from
[Start With AI](#start-with-ai) and point it at
[`docs/building-portals.md`](docs/building-portals.md).

## Build Mode And Operate Mode

MortelOS has two AI surfaces.

| Mode | Purpose | Where it lives |
| --- | --- | --- |
| Build mode | A coding agent assembles the portal | This host app, files, Artisan, tests |
| Operate mode | A runtime agent operates the live workspace | MCP server from `mortelos/framework` |

Build mode is used while creating the portal. Operate mode is used after the
workspace is running. Runtime agents can search entities, trigger workflows,
work with skills and act on governance proposals, but only through OAuth,
tenant scoping, trust levels and policy checks.

Example MCP mount:

```php
// routes/ai.php
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/mortelos', config('mortelos.mcp.server'))
    ->middleware([
        'auth:api',
        // tenancy init from MCP token
        // role resolution
        // trust-level enforcement
        // data classification
        // throttling
    ]);
```

The exact server class is provided by `mortelos/framework` and may differ per
framework version. Keep generic host documentation on the `/mcp/mortelos`
route; customer-specific MCP routes belong only in that host's own
documentation.

## Technical Contracts

The starter shell is configured through `config/starter.php`. Required auth
contracts already point at working stubs, so a fresh app boots. Optional
contracts can stay `null` until the portal needs them.

### Auth

| Key | Expected shape |
| --- | --- |
| `auth.post_login_redirect_resolver` | `execute(User $user, string $tenantId): string` |
| `auth.controllers.password_login` | Invokable controller for password login |
| `auth.controllers.passkey_authenticated` | Controller for passkey login POST |
| `auth.controllers.accept_invitation` | Controller with `show()` and `store()` |
| `auth.controllers.tenant_select` | Controller with `show()` and `store()` |
| `auth.controllers.passkey_authentication_options` | Optional controller for passkey options GET |
| `auth.passkey_form_component` / `auth.password_form_component` | Optional Blade components for login forms |

### Layout, Navigation And Search

| Key | Expected shape |
| --- | --- |
| `layout.sidebar_nav_component` | Livewire component for the sidebar |
| `layout.topbar_component` | Livewire component for the topbar |
| `layout.universal_search_component` | Livewire component for search |
| `navigation.sidebar_resolver` | `sections(Model $user): array`, `inboxCount(Model $user): int`, `overviews(Model $user): array` |
| `navigation.universal_search_resolver` | `results(string $query, ?Model $user): array`, `navigation(?Model $user): array`, `saveOverview(...)` |

### Chat

| Key | Expected shape |
| --- | --- |
| `chat.settings_service` | Service exposing `enabled(): bool` |
| `chat.conversation_panel_component` | Defaults to `chat::conversation-panel` from `mortelos/chat` |

The chat panel renders only when a settings service is configured, the user is
authenticated and `enabled()` returns true.

### Dashboard, Inbox, Governance, Users And Onboarding

| Key | Expected shape |
| --- | --- |
| `dashboard.primary_widgets` / `secondary_widgets` | Arrays of Livewire widget component names |
| `dashboard.proud_message_resolver` | `resolve(): string` |
| `inbox.item_type_resolver` | `resolve(string $itemId): string` |
| `inbox.intake_detail_types` | Item types that render the intake detail component |
| `governance.resolver` / `access_resolver` | Role and manage-access resolvers |
| `governance.*_component` | Slots for Policy Studio surfaces |
| `users.resolver` | Members, invites, invite and revoke operations |
| `onboarding.resolver` | `resolve(): array`, `complete(): void` |

## What Belongs In The Host

The host app owns customer-specific wiring:

1. Branding, copy and tenant-specific configuration
2. Auth controller implementations
3. Tenant model, memberships, roles and invitations
4. Resolver classes for navigation, search, governance, users and onboarding
5. Policy seeding and host-specific defaults
6. Local orchestration, scheduled jobs and telemetry sinks

Reusable capabilities should move toward packages.

## Testing

```bash
composer test
composer test:arch
composer format:check
composer format
php artisan starter:doctor
```

For portal work, verify at least:

1. `php artisan starter:doctor` is green
2. `vendor/bin/pest` passes
3. Login -> tenant-select -> dashboard works manually
4. The new capability has at least one focused feature test
5. Permission-sensitive behavior is tested for allowed and denied roles

## Troubleshooting

| Symptom | Likely cause | First fix |
| --- | --- | --- |
| `LogicException: Missing starter route class config [...]` | An auth controller config key is `null` | Check `config/starter.php` |
| Vite manifest not found | Frontend assets were not built | Run `npm install --ignore-scripts && npm run build` |
| `View [layouts.guest] not found` | Guest layout was removed | Restore `resources/views/layouts/guest.blade.php` |
| Routes return 404 | `routes/web.php` does not require `routes/starter.php` | Check the route bridge |
| Sidebar, search or chat is empty | Resolver or component config is `null` | Bind the relevant resolver |
| `mortelos/ui` cannot be installed | Missing private repo access | Confirm GitHub SSH or token access |

Extended notes:
[`knowledge/08-troubleshooting.md`](knowledge/08-troubleshooting.md).

## Reference Docs

| Document | Use when |
| --- | --- |
| [`AGENTS.md`](AGENTS.md) | Agent rules and project conventions |
| [`docs/building-portals.md`](docs/building-portals.md) | Full portal design method |
| [`docs/init-host-app.md`](docs/init-host-app.md) | Host setup details |
| [`docs/host-app-anatomy.md`](docs/host-app-anatomy.md) | What a fleshed-out host app looks like |
| [`knowledge/README.md`](knowledge/README.md) | Short AI-first notes by topic |

## Maintainer Notes

1. Keep `starter::` Livewire namespace conventions intact.
2. Keep the starter shell inline in new host apps.
3. Keep customer policy, tenant config, auth implementations and local
   orchestration in the host.
4. Record reusable additions as package decisions.
5. Keep this README readable for implementation partners first, then technical
   users.

## License

Proprietary.
