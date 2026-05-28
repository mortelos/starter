# Foundation Wiring

Phase [3]. Wire the Starter contracts so the app boots and every later capability
plugs into governed primitives. This is the checklist; the canonical contract spec
is the README contract tables plus `config/starter.php` defaults and
`routes/starter.php`. Detect what is already done in phase [0] and only fill
gaps; do not clobber an already-wired host.

## A. Make the app boot (required)

These three edits + the five auth contracts are the minimum. The app boots as
soon as the auth contracts are filled; everything else degrades silently.

1. **Route bridge**, `routes/starter.php` in the host requires the package routes,
   and `routes/web.php` requires that bridge:
   ```php
   // routes/starter.php
   require base_path('vendor/mortelos/starter/routes/starter.php');
   // routes/web.php
   require __DIR__.'/starter.php';
   ```
2. **Layout delegation**, `resources/views/layouts/app.blade.php` delegates to the
   package layout:
   ```blade
   @include('mortelos-starter::layouts.app', ['slot' => $slot])
   ```
3. **Config from package defaults**, `config/starter.php` starts from the package
   defaults and overrides only host bindings:
   ```php
   $defaults = require __DIR__.'/../vendor/mortelos/starter/config/starter.php';
   return array_replace_recursive($defaults, [ /* host overrides */ ]);
   ```
   Publish defaults once with `php artisan vendor:publish --tag=mortelos-starter`.

### Auth contracts (required to boot)

| Key | Expected shape |
| --- | --- |
| `auth.post_login_redirect_resolver` | `execute(User $user, string $tenantId): string` |
| `auth.controllers.password_login` | Invokable controller for password login |
| `auth.controllers.passkey_authenticated` | Controller for passkey login POST |
| `auth.controllers.accept_invitation` | Controller with `show()` and `store()` |
| `auth.controllers.tenant_select` | Controller with `show()` and `store()` |
| `auth.controllers.passkey_authentication_options` | Optional, passkey options GET |
| `auth.passkey_form_component` / `auth.password_form_component` | Optional Blade form components |

These controllers and the redirect resolver live in the **host** (`App\Http\
Controllers\Auth\…`, `App\Actions\Auth\…`). If a required key is empty, the route
file throws `LogicException: Missing starter route class config [...]`
(`routes/starter.php:13`), that means an `auth.controllers.*` key is still null.

**Checkpoint:** confirm `login → tenant-select → dashboard` works before moving on.

## B. Navigation, search, shell (optional, fill as the portal needs)

| Key | Expected shape |
| --- | --- |
| `layout.sidebar_nav_component` / `topbar_component` / `universal_search_component` | Livewire component names rendered into the shell slots |
| `navigation.sidebar_resolver` | `sections(Model $user): array`, `inboxCount(Model $user): int`, `overviews(Model $user): array` |
| `navigation.universal_search_resolver` | `results(string $query, ?Model $user): array`, `navigation(?Model $user): array`, `saveOverview(string $query, string $name, string\|int\|null $createdBy): void` |

- `sections()` returns sections of items shaped like `['label','icon','route','permission','badge']`.
  Action items set `type => 'action'` and `action => 'browser-event-name'`.
- `results()` returns `entities`, `inboxItems`, `chatItems` arrays of items shaped
  like `['id','name'/'title','url','icon', ...]`.

The package ships `StarterSidebarNavigationResolver` and
`StarterUniversalSearchResolver` references in `CLAUDE.md`; the host fills the real
resolvers (often backed by entities, the inbox count, and saved overviews).

## C. Governance, users, onboarding, dashboard, inbox, chat (optional)

| Key | Expected shape |
| --- | --- |
| `governance.resolver` / `access_resolver` | `roles(): array`, `canManage(User $user, ?string $tenantId): bool` |
| `governance.*_component` | Policy Studio slots: proposal queue, stats, trust config, learning patterns, channel status |
| `users.resolver` | `canManage(): bool`, `members(): array`, `pendingInvites(): array`, `invite(string $email, string $role): void`, `revokeInvite(string $inviteId): void` |
| `onboarding.resolver` | `resolve(): array`, `complete(): void` |
| `dashboard.primary_widgets` / `secondary_widgets` | Arrays of Livewire widget component names |
| `dashboard.proud_message_resolver` | `resolve(): string` |
| `inbox.item_type_resolver` | `resolve(string $itemId): string` |
| `inbox.intake_detail_types` | Item types that render `livewire:inbox.intake-detail` |
| `chat.settings_service` | Service with `enabled(): bool` (chat panel renders only when set, user authed, and enabled) |
| `chat.conversation_panel_component` | Defaults to `chat::conversation-panel` |

Wire these as the capability map calls for them, e.g. a governance-heavy portal
fills the `governance.*` slots via Policy Studio; a portal with no chat leaves
`chat.settings_service` null.

## D. Seed deny-by-default policies (§7)

Governance is the safety layer. During this phase, lay down the policy scaffold,
do not wait until the end:

- Deny by default. Add a policy ability for every mutating action and sensitive
  surface the capability map listed (entity visibility/data access, navigation
  visibility, widget access, agent-tool access, connector setup/data access,
  governance changes).
- Route visibility checks through the central access resolver
  (`governance.access_resolver`), not component-level conditionals.
- Seed safe defaults during tenant onboarding; expose changes through proposal /
  approval flows (Policy Studio).

## E. Document tenant identity as a host requirement (§9)

Tenant/membership/roles are **host-owned**. Do not invent them in the portal.
Capture, in `docs/portals/<slug>/capability-map.md` and the build plan, what the
host must provide: users, tenant memberships, roles, invitations, tenant
selection, super-admin behavior, data isolation, optional branding. Keep tenant
selection and membership host-owned unless a reusable package boundary is proven.

## Troubleshooting (from the README)

| Symptom | Cause |
| --- | --- |
| `LogicException: Missing starter route class config [...]` | A required `auth.controllers.*` key is empty |
| Routes return 404 | The starter route bridge is not required from `routes/web.php` |
| Blank/unstyled page | `layouts/app.blade.php` does not delegate to `mortelos-starter::layouts.app` |
| Sidebar/search/chat missing | The matching resolver or component is still `null` (optional, degrades silently) |

Reference host app: **UteqOS** demonstrates the full wiring (route bridge,
defaults-based `config/starter.php` with host resolvers, layout delegation,
dashboard/governance slots, inbox detail routing). Use it for shape and intent,
not as a rule that every host uses the same class names or tenant model.
