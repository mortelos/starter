# MortelOS Starter

Starter is the ready-made app shell for a MortelOS workspace: login, tenant
selection, dashboard, inbox, users, settings and governance pages.

MortelOS is built to be extended by an AI agent, not hand-wired. You describe a
portal capability in plain language, your AI coding agent assembles it from
MortelOS's governed primitives, and you review the result against the contracts
in this README. You stay the director and the reviewer; the agent does the
wiring.

Agents work on two surfaces. A coding agent **builds** the host app from this
README (setup and portal sections below). The MortelOS MCP server lets agents
**operate** the running workspace at runtime (see [Agent access](#agent-access-mcp)).

For the full design method an agent follows, see
[docs/building-portals.md](docs/building-portals.md).

## What is fixed vs what your agent builds

| Starter provides (fixed) | Your agent assembles from your description (you review) |
| --- | --- |
| App layout `mortelos-starter::layouts.app` | Brand and which shell components are active |
| Routes for login, tenant select, dashboard, inbox, governance, users, settings, onboarding, invitations, logout | Auth controllers and post-login redirect rules |
| `starter::` Livewire namespace and page components | Navigation, universal search and chat resolvers |
| Config-driven dashboard, governance and inbox slots | Widget implementations, policy bindings, inbox data |
| Publishable config and views | Tenant model, membership, roles and invitations |

## Requirements

- PHP `^8.4`
- Laravel support components `^13.0`
- Livewire `^4.0`
- `mortelos/ui ^0.2`

Discovered automatically through `Mortelos\Starter\MortelosStarterServiceProvider`.

## Setup

### 1. Install the package

```bash
composer require mortelos/starter
php artisan vendor:publish --tag=mortelos-starter
```

### 2. Let your agent wire it in

Open this repo in your AI coding agent and give it the wiring task. A prompt that
works:

> Wire `mortelos/starter` into this Laravel host app. Add the starter route
> bridge and require it from `routes/web.php`, delegate
> `resources/views/layouts/app.blade.php` to `mortelos-starter::layouts.app`, and
> create `config/starter.php` from the package defaults with only the minimal
> auth contracts filled. Use this README's contract tables as the spec.

The agent produces three things. This is what correct output looks like, so you
can review it:

```php
// routes/starter.php  - bridge to the package routes
require base_path('vendor/mortelos/starter/routes/starter.php');

// routes/web.php
require __DIR__.'/starter.php';
```

```blade
{{-- resources/views/layouts/app.blade.php --}}
@include('mortelos-starter::layouts.app', ['slot' => $slot])
```

```php
// config/starter.php  - starts from package defaults, fills the minimal auth block
$defaults = require __DIR__.'/../vendor/mortelos/starter/config/starter.php';

return array_replace_recursive($defaults, [
    'auth' => [
        'post_login_redirect_resolver' => App\Actions\Auth\ResolvePostLoginRedirect::class,
        'controllers' => [
            'password_login'        => App\Http\Controllers\Auth\PasswordLoginController::class,
            'passkey_authenticated' => App\Http\Controllers\Auth\PasskeyAuthenticatedController::class,
            'accept_invitation'     => App\Http\Controllers\Auth\AcceptInvitationController::class,
            'tenant_select'         => App\Http\Controllers\Auth\TenantSelectController::class,
        ],
    ],
]);
```

### 3. Review

The app boots as soon as the auth contracts are filled. Check the five auth keys
against the table below, then load the app: you should get a working `login` ->
tenant select -> `dashboard` flow. Everything else is optional and degrades
silently until you add it.

## Build a portal

Start from the capability, not from a page. Describe what each role should be
able to do, and let the agent assemble it. An example prompt:

> Add a customer document-review portal on Starter. Customers upload documents;
> account managers review and approve them. Follow `docs/building-portals.md`:
> record the package decision, model the domain as entities, links and events,
> add the navigation and inbox resolvers, seed deny-by-default policies, and
> route approvals through the inbox.

The agent works through the MortelOS method (capability map, package decision,
domain model, projections, connectors, widgets, policies, workflows,
observability) and fills the contracts below. You review the package decision,
the policies and the resulting surfaces, and approve changes through governance.

Read [docs/building-portals.md](docs/building-portals.md) for the full method.
Point your agent at it as context.

### Guided kickoff skill

This package ships a Claude skill, `portal-kickoff`, that runs the method above as
a guided workflow: it interviews for the capability map one question at a time,
wires the Starter foundation, records package decisions, writes a standalone build
plan under `docs/portals/<slug>/`, then builds the portal one vertical slice at a
time with a review checkpoint each step. It is the first skill to run on a new
portal.

The skill lives in this package at `.claude/skills/portal-kickoff/`, but portal
work writes into the host app, so run it from the host-app working directory.
Symlink it into the host once:

```bash
mkdir -p .claude/skills
ln -s vendor/mortelos/starter/.claude/skills/portal-kickoff .claude/skills/portal-kickoff
```

## Agent access (MCP)

Building the host app is one AI surface. Operating the running workspace is the
other. MortelOS exposes the live workspace to any MCP-capable agent, so it can
search and mutate entities, run skills, trigger workflows, act on governance
approvals and launch sub-agents without going through the web UI.

This server is provided by the MortelOS core package (`uteq/mortel`), not by
Starter. The host app mounts it in `routes/ai.php`:

```php
// routes/ai.php
use Laravel\Mcp\Facades\Mcp;
use Mortel\MCP\Servers\UteqOSServer;

Mcp::oauthRoutes();                       // OAuth 2.1 discovery + dynamic client registration
Mcp::web('/mcp/uteqos', UteqOSServer::class)
    ->middleware([
        'auth:api',                       // Passport OAuth
        // tenancy init from MCP token, role resolution,
        // trust-level enforcement, data classification, throttling
    ]);
```

Access is OAuth-authenticated and tenant-scoped, and every call passes through
role resolution, trust-level enforcement and data classification. An agent only
sees and changes what its grants allow. The same policies you review for the web
shell govern the MCP surface, so the runtime agent is bound by the same contracts
as the portal it operates.

## Contracts (your review checklist)

These are the contracts the agent fills, and the spec you review its output
against. Starter config is merged from the package `config/starter.php`; the host
overrides only the local bindings. See that file for every default key.

### Auth (required to boot)

| Key | Expected shape |
| --- | --- |
| `auth.post_login_redirect_resolver` | `execute(User $user, string $tenantId): string` |
| `auth.controllers.password_login` | Invokable controller for password login. |
| `auth.controllers.passkey_authenticated` | Controller for passkey login POST. |
| `auth.controllers.accept_invitation` | Controller with `show()` and `store()`. |
| `auth.controllers.tenant_select` | Controller with `show()` and `store()`. |
| `auth.controllers.passkey_authentication_options` | Optional. Controller for passkey options GET. |
| `auth.passkey_form_component` / `auth.password_form_component` | Optional. Blade components for the login forms. |

### Layout, navigation and search (optional)

| Key | Expected shape |
| --- | --- |
| `layout.sidebar_nav_component` / `topbar_component` / `universal_search_component` | Livewire component names rendered into the shell slots. |
| `navigation.sidebar_resolver` | `sections(Model $user): array`, `inboxCount(Model $user): int`, `overviews(Model $user): array` |
| `navigation.universal_search_resolver` | `results(string $query, ?Model $user): array`, `navigation(?Model $user): array`, `saveOverview(string $query, string $name, string\|int\|null $createdBy): void` |

`sections()` returns sections of items shaped like
`['label', 'icon', 'route', 'permission', 'badge']`. Action items may set
`type => 'action'` and `action => 'browser-event-name'`. `results()` returns
`entities`, `inboxItems` and `chatItems` arrays of `['id', 'name'/'title', 'url', 'icon', ...]`.

### Chat (optional)

| Key | Expected shape |
| --- | --- |
| `chat.settings_service` | Service exposing `enabled(): bool`. |
| `chat.conversation_panel_component` | Defaults to `chat::conversation-panel`. |

The chat panel renders only when a settings service is configured, a user is
authenticated and `app($settingsService)->enabled()` returns true.

### Dashboard, inbox, governance, users, onboarding (optional)

| Key | Expected shape |
| --- | --- |
| `dashboard.primary_widgets` / `secondary_widgets` | Arrays of Livewire widget component names. |
| `dashboard.proud_message_resolver` | `resolve(): string` |
| `inbox.item_type_resolver` | `resolve(string $itemId): string` |
| `inbox.intake_detail_types` | Item types that render `livewire:inbox.intake-detail` instead of `inbox-detail`. |
| `governance.resolver` / `access_resolver` | `roles(): array` and `canManage(User $user, ?string $tenantId): bool` |
| `governance.*_component` | Slots for Policy Studio surfaces (proposal queue, stats, trust config, learning patterns, channel status). |
| `users.resolver` | `canManage(): bool`, `members(): array`, `pendingInvites(): array`, `invite(string $email, string $role): void`, `revokeInvite(string $inviteId): void` |
| `onboarding.resolver` | `resolve(): array`, `complete(): void` |

## Reference implementation

UteqOS is the current reference host app. It demonstrates the route bridge, a
`config/starter.php` that starts from package defaults and injects host
resolvers, layout delegation, host-backed navigation and search, dashboard and
governance slots, and inbox detail routing. Use it for shape and intent, not as a
rule that every host must use the same class names or tenant model.

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `LogicException: Missing starter route class config [...]` | A required `auth.controllers.*` key is empty. Fill the auth block (setup step 2). |
| Routes return 404 | The starter route bridge is not required from `routes/web.php`. |
| Blank or unstyled page | `resources/views/layouts/app.blade.php` does not delegate to `mortelos-starter::layouts.app`. |
| Sidebar, search or chat missing | The matching resolver or component is still `null` in config. These are optional and degrade silently. |

## Testing

For the package itself:

```bash
composer validate --strict
```

For a host app, ask your agent to add architecture tests that prove the route
bridge requires the package route file, starter route names do not drift into
host `web.php`, the host layout delegates to `mortelos-starter::layouts.app`, the
layout renders the configured dynamic components, published config matches the
package default contract, and starter Livewire components render through the
`starter::` namespace. In UteqOS this is covered by tests under
`tests/Feature/Architecture` and `tests/Unit/Architecture`.

## Maintainer notes

- Keep package-owned shell behavior inside `mortelos/starter`.
- Keep customer policy, tenant config, auth controllers and local orchestration
  in the host app unless a package boundary is documented.
- Prefer config and resolver contracts over hardcoded `App`, `Mortel` or `Uteq`
  dependencies inside this package.
- If service provider discovery metadata changes, update the host with
  `composer update mortelos/starter`.
- Commit changes in the upstream `mortelos-starter` repository separately from
  host-app changes.

## License

Proprietary.
