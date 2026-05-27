# MortelOS Starter

Starter gives you the ready-made app shell for a MortelOS workspace: login,
tenant selection, dashboard, inbox, users, settings and governance pages. You
only build your own auth, data and rules, and wire them in through config.

The package is intentionally not a complete product. It is the stable shell that
a host application wires into its own tenant model, auth controllers, policies,
connectors, widgets and customer-specific packages.

For designing portals on top of this shell, see
[docs/building-portals.md](docs/building-portals.md).

## What you get vs what you provide

| Starter provides | You provide |
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

## Quickstart

### 1. Install and publish config

```bash
composer require mortelos/starter
php artisan vendor:publish --tag=mortelos-starter
```

### 2. Load the starter routes

Create a thin route bridge and include it from your web routes:

```php
// routes/starter.php
<?php

declare(strict_types=1);

require base_path('vendor/mortelos/starter/routes/starter.php');
```

```php
// routes/web.php
require __DIR__.'/starter.php';
```

### 3. Delegate your app layout

```blade
{{-- resources/views/layouts/app.blade.php --}}
@include('mortelos-starter::layouts.app', ['slot' => $slot])
```

### 4. Wire the minimum config

The app boots as soon as the auth contracts are filled in. Everything else has a
default or is optional, so start with this and grow:

```php
// config/starter.php
<?php

declare(strict_types=1);

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

You now have a working `login` -> tenant select -> `dashboard` flow. Add the
optional resolvers below to light up navigation, search, governance and widgets.

Publish views only when the host app needs explicit overrides; most apps keep the
package views upstream and customize through config and resolvers:

```bash
php artisan vendor:publish --tag=mortelos-starter-views
```

## Configuration

Starter config is merged from the package `config/starter.php`. A host app
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

## Customizing

- **Navigation and search:** implement the resolvers above and point
  `layout.*_component` at `starter::shared.sidebar-nav` / `universal-search`.
- **Dashboard:** list your own Livewire widget components in
  `dashboard.primary_widgets` / `secondary_widgets`.
- **Governance:** bind the `governance.*_component` slots to Policy Studio
  (`policy-studio::governance.proposal-queue`).
- **Inbox details:** add custom item types to `inbox.intake_detail_types`.

For the full method of designing a portal (capabilities, packages, projections,
connectors, policies, workflows, observability), see
[docs/building-portals.md](docs/building-portals.md).

## Reference implementation

UteqOS is the current reference host app. It demonstrates the route bridge, a
`config/starter.php` that starts from package defaults and injects host
resolvers, layout delegation, host-backed navigation and search, dashboard and
governance slots, and inbox detail routing. Use it for shape and intent, not as a
rule that every host must use the same class names or tenant model.

## Troubleshooting

| Symptom | Cause |
| --- | --- |
| `LogicException: Missing starter route class config [...]` | A required `auth.controllers.*` key is empty. Fill the auth block (step 4). |
| Routes return 404 | The starter route bridge is not required from `routes/web.php`. |
| Blank or unstyled page | `resources/views/layouts/app.blade.php` does not delegate to `mortelos-starter::layouts.app`. |
| Sidebar, search or chat missing | The matching resolver or component is still `null` in config. These are optional and degrade silently. |

## Testing

For the package itself:

```bash
composer validate --strict
```

For a host app, add architecture tests that prove the route bridge requires the
package route file, starter route names do not drift into host `web.php`, the
host layout delegates to `mortelos-starter::layouts.app`, the layout renders the
configured dynamic components, published config matches the package default
contract, and starter Livewire components render through the `starter::`
namespace. In UteqOS this is covered by tests under
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
