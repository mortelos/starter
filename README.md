# MortelOS Starter

MortelOS Starter is the application shell and extension architecture layer for
MortelOS workspaces. It provides the reusable Laravel and Livewire surfaces that
every MortelOS host application starts from: authentication entrypoints,
tenant-aware navigation, dashboard composition, inbox layout, governance,
users, onboarding, settings and the surrounding app layout.

The package is intentionally not a complete product. It is the stable shell that
a host application wires into its own tenant model, auth controllers, policies,
connectors, widgets and customer-specific packages.

```text
Customer portal
  -> Capability map
  -> Package decision
  -> Domain model
  -> Projection/read model
  -> Connector/data source
  -> Widget/page surface
  -> Policy/governance
  -> Workflow/inbox approval
  -> Observability/audit
  -> Tests/release
```

## What Starter Provides

`mortelos/starter` provides:

- A reusable app layout registered as `mortelos-starter::layouts.app`.
- A Livewire namespace registered as `starter::`.
- Starter-owned routes for login, tenant selection, dashboard, inbox,
  governance, users, settings, onboarding, invitations and logout.
- Config-driven shell slots for sidebar navigation, topbar, universal search
  and chat.
- Config-driven dashboard widget composition.
- Config-driven governance surfaces.
- A split-pane inbox shell that can switch between standard approval details
  and intake detail surfaces.
- Publishable package config and views.

The host application provides:

- Auth controllers and post-login redirect rules.
- Tenant selection, membership and invitation behavior.
- Navigation and universal search resolvers.
- Dashboard widgets and page widgets.
- Governance resolvers and Policy Studio component bindings.
- Inbox item type resolution.
- Connector, projection, policy and customer package wiring.

## Requirements

The package currently targets:

- PHP `^8.4`
- Laravel support components `^13.0`
- Livewire `^4.0`
- `mortelos/ui ^0.2`

The package is discovered through Laravel package discovery via
`Mortelos\Starter\MortelosStarterServiceProvider`.

## Installation

Install the package in a MortelOS host app:

```bash
composer require mortelos/starter
```

Publish the default configuration when you want to own the host wiring:

```bash
php artisan vendor:publish --tag=mortelos-starter
```

Publish views only when the host app needs explicit overrides:

```bash
php artisan vendor:publish --tag=mortelos-starter-views
```

Most host apps should keep the package views upstream and customize behavior
through config, resolvers and package-level extension points.

## Route Bridge

Starter routes are package-owned, but the host app decides where to load them.
The reference host app keeps a thin bridge:

```php
// routes/starter.php
<?php

declare(strict_types=1);

require base_path('vendor/mortelos/starter/routes/starter.php');
```

Then include that bridge from the host web routes:

```php
// routes/web.php
require __DIR__.'/starter.php';
```

The package route file owns these route names:

| Route name | Purpose |
| --- | --- |
| `home` | Redirects an authenticated user to tenant selection or post-login destination. |
| `login` | Renders the starter login page. |
| `passkeys.login` | Handles passkey authentication. |
| `passkeys.authentication_options` | Optional passkey options endpoint. |
| `auth.password-login` | Handles password login. |
| `invite.show` | Shows an invitation acceptance page. |
| `invite.store` | Accepts an invitation. |
| `onboarding` | Renders first-run user onboarding. |
| `dashboard` | Renders the management dashboard. |
| `inbox` | Renders the work inbox. |
| `governance` | Renders governance and Policy Studio surfaces. |
| `users` | Renders user and invitation management. |
| `settings` | Renders profile and password settings. |
| `auth.tenant-select` | Shows tenant selection. |
| `auth.tenant-store` | Stores the selected tenant in session. |
| `logout` | Logs out the current user. |

Keep these routes in the starter route bridge. Add customer-specific routes in
the host app or in customer packages.

## Layout Bridge

Host apps usually delegate their app layout to Starter:

```blade
{{-- resources/views/layouts/app.blade.php --}}
@include('mortelos-starter::layouts.app', ['slot' => $slot])
```

The package layout reads shell components from `config('starter.layout.*')` and
renders them through dynamic Livewire components:

- `starter.layout.sidebar_nav_component`
- `starter.layout.topbar_component`
- `starter.layout.universal_search_component`
- `starter.chat.settings_service`
- `starter.chat.conversation_panel_component`

This keeps the layout reusable while allowing each host app to decide which
navigation, search and chat surfaces are active.

## Configuration

Starter configuration is merged from `config/starter.php`. A host app should
require the package defaults and override only the local bindings:

```php
<?php

declare(strict_types=1);

$defaults = require __DIR__.'/../vendor/mortelos/starter/config/starter.php';

return array_replace_recursive($defaults, [
    'auth' => [
        'post_login_redirect_resolver' => App\Actions\Auth\ResolvePostLoginRedirect::class,
        'passkey_form_component' => 'mortelos-starter::auth.passkey-form',

        'controllers' => [
            'accept_invitation' => App\Http\Controllers\Auth\AcceptInvitationController::class,
            'passkey_authenticated' => App\Http\Controllers\Auth\PasskeyAuthenticatedController::class,
            'passkey_authentication_options' => Spatie\LaravelPasskeys\Http\Controllers\GeneratePasskeyAuthenticationOptionsController::class,
            'password_login' => App\Http\Controllers\Auth\PasswordLoginController::class,
            'tenant_select' => App\Http\Controllers\Auth\TenantSelectController::class,
        ],
    ],

    'layout' => [
        'sidebar_nav_component' => 'starter::shared.sidebar-nav',
        'universal_search_component' => 'starter::shared.universal-search',
    ],

    'navigation' => [
        'sidebar_resolver' => App\Support\StarterSidebarNavigationResolver::class,
        'universal_search_resolver' => App\Support\StarterUniversalSearchResolver::class,
    ],
]);
```

### Auth

```php
'auth' => [
    'post_login_redirect_resolver' => null,
    'passkey_form_component' => null,
    'password_form_component' => 'mortelos-starter::auth.password-form',
    'controllers' => [
        'accept_invitation' => null,
        'passkey_authenticated' => null,
        'passkey_authentication_options' => null,
        'password_login' => null,
        'tenant_select' => null,
    ],
],
```

Required route-backed classes:

| Key | Expected shape |
| --- | --- |
| `post_login_redirect_resolver` | `execute(User $user, string $tenantId): string` |
| `controllers.password_login` | Invokable controller for password login. |
| `controllers.tenant_select` | Controller with `show()` and `store()` methods. |
| `controllers.accept_invitation` | Controller with `show()` and `store()` methods. |
| `controllers.passkey_authenticated` | Controller for passkey login POST. |
| `controllers.passkey_authentication_options` | Optional controller for passkey options GET. |

### Chat

```php
'chat' => [
    'settings_service' => null,
    'conversation_panel_component' => 'chat::conversation-panel',
],
```

The chat panel is rendered only when a settings service class is configured, the
conversation panel component is configured, a user is authenticated and
`app($settingsService)->enabled()` returns true.

### Layout

```php
'layout' => [
    'sidebar_nav_component' => null,
    'topbar_component' => 'starter::shared.topbar',
    'universal_search_component' => null,
],
```

Use package-provided components when the host app implements the expected
resolvers:

```php
'layout' => [
    'sidebar_nav_component' => 'starter::shared.sidebar-nav',
    'topbar_component' => 'starter::shared.topbar',
    'universal_search_component' => 'starter::shared.universal-search',
],
```

### Navigation

```php
'navigation' => [
    'sidebar_resolver' => null,
    'universal_search_resolver' => null,
],
```

The sidebar resolver is expected to provide:

```php
sections(Model $user): array
inboxCount(Model $user): int
overviews(Model $user): array
```

`sections()` should return sections with items that match this shape:

```php
[
    [
        'label' => 'Work',
        'items' => [
            [
                'label' => 'Inbox',
                'icon' => 'inbox',
                'route' => 'inbox',
                'permission' => 'nav.sidebar.inbox',
                'badge' => null,
            ],
        ],
    ],
]
```

Action items may set `type => 'action'` and `action => 'browser-event-name'`.

The universal search resolver is expected to provide:

```php
results(string $query, ?Model $user): array
navigation(?Model $user): array
saveOverview(string $query, string $name, string|int|null $createdBy): void
```

`results()` should return:

```php
[
    'entities' => [
        ['id' => '...', 'name' => 'Acme', 'type' => 'company', 'url' => '/entities/...', 'icon' => 'building-office'],
    ],
    'inboxItems' => [
        ['id' => '...', 'title' => 'Review', 'summary' => '...', 'type' => 'policy_review', 'status' => 'pending', 'url' => '/inbox?item=...', 'icon' => 'inbox'],
    ],
    'chatItems' => [
        ['id' => '...', 'title' => 'Question', 'summary' => '...', 'url' => '/chat?conversation=...', 'icon' => 'chat-bubble-left-right'],
    ],
]
```

### Onboarding

```php
'onboarding' => [
    'resolver' => null,
],
```

The onboarding resolver is expected to provide:

```php
resolve(): array
complete(): void
```

`resolve()` should return:

```php
[
    'completed' => false,
    'user_name' => 'Taylor',
    'user_role' => 'member',
    'trust_levels' => [],
]
```

### Governance

```php
'governance' => [
    'resolver' => null,
    'access_resolver' => null,
    'proposal_queue_component' => null,
    'stats_component' => null,
    'trust_config_component' => null,
    'learning_patterns_component' => null,
    'channel_status_component' => null,
],
```

The governance resolver is expected to provide:

```php
roles(): array
```

The access resolver is expected to provide:

```php
canManage(User $user, ?string $tenantId): bool
```

Policy Studio can be exposed through the component slots:

```php
'governance' => [
    'proposal_queue_component' => 'policy-studio::governance.proposal-queue',
    'stats_component' => 'governance.governance-stats',
    'trust_config_component' => 'governance.trust-config',
    'learning_patterns_component' => 'governance.learning-patterns',
    'channel_status_component' => 'governance.channel-status',
],
```

### Users

```php
'users' => [
    'resolver' => null,
],
```

The users resolver is expected to provide:

```php
canManage(): bool
members(): array
pendingInvites(): array
invite(string $email, string $role): void
revokeInvite(string $inviteId): void
```

`members()` should return rows with `id`, `name`, `email`, `role` and
`joined_at`. `pendingInvites()` should return rows with `id`, `email`, `role`
and `expires_at`.

### Dashboard

```php
'dashboard' => [
    'proud_message_resolver' => null,
    'primary_widgets' => [
        'dashboard.ai-performance',
        'dashboard.team-activity',
        'dashboard.overdue-items',
        'dashboard.roi-overview',
    ],
    'secondary_widgets' => [
        'dashboard.pending-proposals',
        'dashboard.notification-list',
        'dashboard.recent-activity',
        'dashboard.deadline-list',
    ],
],
```

The dashboard renders widget component names through dynamic Livewire
components. The proud message resolver may provide:

```php
resolve(): string
```

### Inbox

```php
'inbox' => [
    'item_type_resolver' => null,
    'intake_detail_types' => [
        'compliance_intake',
        'policy_review',
        'audit',
    ],
],
```

The inbox item type resolver is expected to provide:

```php
resolve(string $itemId): string
```

When the selected item type is listed in `intake_detail_types`, the inbox shell
renders `livewire:inbox.intake-detail`. Other item types render
`livewire:inbox.inbox-detail`.

## Application Shell

Starter owns the shell and route contracts. Host apps own the rules behind
those contracts.

This boundary matters because customer portals should be assembled from
reusable, governed capabilities instead of one-off page code:

| Layer | Owned by Starter | Owned by host app or package |
| --- | --- | --- |
| Layout | Sidebar/header/main/chat placement | Brand, active components, search and navigation data |
| Routes | Starter-owned route names and page components | Customer routes and package routes |
| Auth pages | Login page composition | Login controllers, tenant selection, invitations |
| Dashboard | Widget slots and layout | Widget implementations and metrics |
| Inbox | Split-pane shell and detail switch | Inbox data, approval behavior, intake details |
| Governance | Slots for governance surfaces | Policy Studio bindings and role rules |
| Users | User management page shell | Membership, invitations, role semantics |

## Building Customer Portals

Start with the customer capability, not with a page.

```text
Customer asks for a portal
  |
  +-- What can each role do?
  +-- Which data is shown or mutated?
  +-- Which external systems are involved?
  +-- Which actions require approval?
  +-- Which parts are reusable across customers?
  |
  v
MortelOS extension plan
```

### 1. Capability Map

Write the customer-facing capabilities first. Examples:

- Customers can view their dossiers.
- Customers can upload documents.
- Account managers can review uploaded documents.
- Finance users can request fresh revenue data from Moneybird.
- Operators can approve AI-proposed policy changes.

For each capability, record:

- Primary users and roles.
- Read data and write data.
- External data sources.
- Approval moments.
- Portal surfaces.
- Audit and reporting needs.
- Reuse potential across other MortelOS installations.

### 2. Package Decision

Before implementation, choose the package boundary:

| Decision | Use when |
| --- | --- |
| `package-now` | The capability is reusable across MortelOS installations now. |
| `package-ready` | The host app needs local wiring first, but the package boundary is explicit. |
| `workspace-only` | The behavior is customer-specific by design. |

In UteqOS host apps with MortelOS dev tools installed, record decisions with:

```bash
php artisan mortelos:package-decision "Customer Portal" \
  --decision=package-ready \
  --surface=mortelos/customer-portal \
  --reason="Reusable portal shell with customer-specific tenant policy and branding." \
  --no-interaction

php artisan mortelos:package-decisions:check --require-reason --no-interaction
```

### 3. Domain Model

Map customer concepts to MortelOS primitives:

| Customer concept | MortelOS primitive |
| --- | --- |
| Customer, project, document, dossier | Entity |
| Customer owns dossier, document belongs to project | Entity link |
| User uploads document, connector syncs invoice | Event |
| Portal-ready dossier overview | Projection or read model |
| Role may view or change something | Policy |
| Customer-specific behavior toggle | Tenant config or package config |

Avoid embedding domain rules directly in Blade or Livewire components. Put them
behind actions, projections, policies, resolvers or package services.

### 4. Projections And Read Models

Use projections when a portal needs a stable read surface built from events,
entities, connector data or workflow state.

Good projection candidates:

- Customer dossier summaries.
- Document review status.
- Connector sync status.
- Revenue, compliance or delivery metrics.
- Audit timelines.

Projection guidance:

- Keep projectors synchronous when consistency is required for the current
  request.
- Provide rebuild and verify commands when the projection can drift.
- Store enough source references to explain how a row was built.
- Keep write behavior in actions or aggregates, not in the projection view.
- Test both event write and projection rebuild paths.

MortelOS host apps already use projection commands such as:

```bash
php artisan mortel:projection:rebuild --type=entity
php artisan mortel:projection:verify --type=entity
```

### 5. Connectors

Use connectors for external systems such as CRM, document storage, finance,
email, meeting transcription, ticketing or customer-specific APIs.

A connector package usually contains:

- A channel driver or provider identifier.
- Setup provider for forms, OAuth or credential help.
- Sync jobs and health checks.
- Data request provider for agent or chat requests.
- Retry and reauth behavior.
- Channel status mapping.
- Policy hooks for setup and data access.

Connector packages should not render portal UI directly unless they own a
reusable setup or status widget. They should expose data, state and actions that
Starter, widgets or customer packages can compose.

### 6. Widgets And Portal Surfaces

Use the smallest surface that matches the workflow:

| Surface | Use for |
| --- | --- |
| Starter page | Core shell page such as dashboard, inbox, users or settings. |
| Package route | A reusable feature workspace owned by a package. |
| Dashboard widget | Dense operational overview or metric card. |
| Chat widget | Interactive task, proposal, graph, form or guided workflow inside chat. |
| Page widget | Reusable Livewire module embedded in a customer portal page. |

Chat widgets are registered through the chat `WidgetRegistry` with a
`WidgetDefinition` or `WidgetManifest`. A widget definition should declare:

- Stable key.
- Label and description.
- Skill name.
- Livewire component.
- Initial state and context schema.
- Optional result schema.
- Optional `policyAbility`.
- Trigger terms.

If a widget changes access, configuration or customer data, give it a policy
ability and cover it through Policy Studio.

### 7. Policies And Governance

Policies define who can see and do things across the OS.

Common policy surfaces:

- Entity visibility and data access.
- Navigation visibility.
- Widget access.
- Agent tool access.
- Connector setup and connector data access.
- Governance and policy changes.

Use policies instead of component-level conditionals whenever the rule affects
tenant data, role capability, navigation, widgets or agent tools.

Policy guidance:

- Deny by default.
- Seed safe defaults during tenant onboarding.
- Route visibility checks through the central access resolver.
- Give every mutating agent tool a policy action.
- Expose policy changes through proposal and approval flows.
- Use Policy Studio for grants, conditions, pending approvals and governance
  review surfaces.

### 8. Workflows And Inbox

Customer portals need workflows, not only pages.

Use inbox and proposal flows for:

- Document review.
- Customer approval.
- Connector reauth or setup.
- AI-proposed changes.
- Policy changes.
- Risky data mutations.

An inbox workflow should define:

- Trigger source.
- Assigned user or role.
- Item type.
- Detail component.
- Approve action.
- Reject action.
- Audit event.
- Follow-up notification or projection update.

### 9. Tenant Identity

A customer portal must be explicit about tenant identity:

- Users.
- Tenant memberships.
- Roles.
- Invitations.
- Tenant selection.
- Super-admin behavior.
- Data isolation.
- Optional customer branding.

Starter provides the shell for login, tenant selection, users and settings. The
host app owns the actual membership model, role mapping and invitation service.

### 10. Observability And Audit

Every customer extension should make operational state inspectable:

- Connector health and last sync.
- Projection drift and rebuild status.
- Policy denials.
- Agent runs and tool calls.
- Widget runs.
- Inbox approvals and rejections.
- Event history and entity audit trail.

If operators cannot answer "why did this appear, disappear or fail?", the
extension is not ready for customer use.

### 11. Release And Versioning

Treat customer portal extensions as package releases:

- Keep migrations additive when possible.
- Document new config keys and default behavior.
- Avoid publishing views unless customization is intentional.
- Register package routes under a package-owned prefix.
- Test host wiring separately from package behavior.
- Record breaking changes in the package README or changelog.
- Provide rollback guidance for config, migrations and route exposure.

## Extension Architecture

### Packages

Build directly in a package when the capability can serve another MortelOS
installation. A package should usually include:

- `composer.json` with Laravel provider discovery.
- A service provider.
- Config file with publish tag.
- Routes only when the package owns URLs.
- Views or Livewire namespace only when it owns UI.
- Migrations only when it owns persistence.
- Tests for package behavior and host wiring.

Use `mortelos/entity-graph` as an example of a package that owns API routes,
views, a Livewire namespace, migrations, extension contracts, a chat widget and
an agent tool. Use `mortelos/policy-studio` as an example of a package that owns
governance widgets, routes and proposal-first policy flows.

### Projections

A projection is a read-optimized representation of domain state. It should be
rebuilt from canonical sources and verified when correctness matters.

Use projections for portal screens that need fast, stable reads across events,
entities, connector records or workflow state.

### Connectors

A connector is an integration boundary around an external system. It should
separate setup, credentials, sync, health, data requests and policy from the UI
that consumes it.

### Widgets

Widgets are reusable interaction surfaces. They should be registered, governed
and testable. Prefer package registration for reusable widgets and config-only
registration for local experiments.

### Policies

Policies are the governance layer that keeps customer portals safe. They should
cover navigation, widgets, agent tools and data access consistently.

### Workflows

Workflows coordinate actions, approvals and consequences. They should write
events, update projections and route human approval through inbox or Policy
Studio surfaces.

### Tenant Identity

Tenant identity defines which users, roles and organizations participate in the
portal. Keep tenant selection and membership behavior host-owned unless a
reusable package boundary is proven.

### Observability

Observability is part of the feature. Connector state, projection state, policy
decisions, widget runs and agent runs must be inspectable by operators.

### Versioning

Version package APIs intentionally. Treat config keys, route names, widget keys,
policy abilities and projection schemas as public contracts.

## Customer Portal Recipe

1. Write the capability map and decide which capabilities are reusable.
2. Record package decisions for all new surfaces.
3. Model customer data as entities, links, events, projections and policies.
4. Add connector packages for external systems.
5. Build portal surfaces as Starter pages, package routes, dashboard widgets,
   chat widgets or page widgets.
6. Register navigation and universal search resolvers.
7. Seed default policies and expose governance through Policy Studio.
8. Add inbox and approval workflows for risky or human-reviewed actions.
9. Add observability for connector health, projection drift, policy denials,
   agent runs and widget runs.
10. Test tenant isolation, policy enforcement, connector failure modes,
    projection rebuilds and approval workflows.
11. Release through package versioning and document host-app wiring changes.

## Reference Implementation

UteqOS is the current reference host application. It demonstrates:

- A route bridge that requires `vendor/mortelos/starter/routes/starter.php`.
- `config/starter.php` that starts from package defaults and injects host
  resolvers.
- Starter layout delegation through `mortelos-starter::layouts.app`.
- Sidebar navigation and universal search backed by host resolvers.
- Dashboard widget slots backed by host Livewire components.
- Governance slots backed by Policy Studio components.
- Inbox detail routing based on host item type resolution.

The reference implementation is useful for shape and intent. It is not a rule
that every host app must use the same class names or tenant model.

## Testing

For the package itself:

```bash
composer validate --strict
```

For a host app consuming Starter, add architecture tests that prove:

- The host route bridge requires the package route file.
- Starter-owned route names do not drift into the host `web.php`.
- The host layout delegates to `mortelos-starter::layouts.app`.
- The package layout renders configured dynamic components.
- Published config still matches the package default contract.
- Starter Livewire components render through the `starter::` namespace.

In UteqOS, the starter wiring is covered by:

```bash
php artisan test \
  tests/Feature/Architecture/MortelosStarterPackageWiringTest.php \
  tests/Unit/Architecture/StarterRouteBoundaryTest.php \
  tests/Unit/Architecture/StarterShellBoundaryTest.php \
  tests/Unit/Architecture/StarterPageCompositionTest.php
```

## Maintainer Notes

- Keep package-owned shell behavior inside `mortelos/starter`.
- Keep customer policy, tenant config, auth controllers and local orchestration
  inside the host app unless a package boundary is documented.
- Prefer config and resolver contracts over hardcoded `App`, `Mortel` or `Uteq`
  dependencies inside this package.
- If service provider discovery metadata changes, update the consuming host app
  with `composer update mortelos/starter`.
- Commit changes in the upstream `mortelos-starter` repository separately from
  host-app changes.

## License

Proprietary.
