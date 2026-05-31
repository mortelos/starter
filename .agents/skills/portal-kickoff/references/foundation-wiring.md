# Foundation Wiring

Phase [3]. Wire the Starter contracts so the app boots and every later capability
plugs into governed primitives. This is the checklist; the canonical contract spec
is the README contract tables plus `config/starter.php` defaults and
`routes/starter.php`. Detect what is already done in phase [0] and only fill
gaps; do not clobber an already-wired host.

**Two host shapes.** Normally you build a portal in a project *created from*
`mortelos/starter`, so the package lives in `vendor/mortelos/starter` and section A
below applies as written. If instead you are working *inside the starter template
itself* (rare, e.g. an eval host), there is no `vendor/mortelos/starter`, the
bridge, layout and config are already the local source, so section A is "confirm
it boots", not "require a vendor bridge". In both cases the rule is the same: the
base is assumed sound, verify and fill gaps, do not rebuild what already boots.

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

**Verify:** confirm `login → tenant-select → dashboard` works before continuing.
A non-booting base is the one hard stop before the plan gate (phase [5]); fix it,
then carry the boot status into the gate rather than asking for a separate
approval here.

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

## F. Assets, Vite and the Herd `.test` domain (required)

On a Herd-served `.test` domain the layout silently breaks (no CSS, blank or
unstyled shell) when the asset wiring assumes a different URL/protocol than the
browser actually uses. `npm run build` does **not** fix any of these. Verify in a
real browser, not just `artisan serve` (which serves http on a port and hides the
problem).

**Most common cause, and the one to check first: the Flux stylesheet is not
imported.** `resources/css/app.css` must contain
`@import '../../vendor/livewire/flux/dist/flux.css';` directly after
`@import 'tailwindcss';`, plus `@source` globs for the blade views
(`@source '../**/*.blade.php';`, the Flux stubs, and package views) and the
surface theme vars the shell uses (`--color-surface`, `--color-surface-alt`,
`--color-surface-hover`, `--color-surface-active`). Without the Flux import,
Tailwind compiles without Flux's component CSS, so `flux:sidebar` / `flux:main`
degrade to unstyled stacked blocks (sidebar on top, content flowing below it)
even though plain Tailwind utilities still work. `npm run build` will not reveal
this; only a real browser shows the broken shell. Mirror a known-good host's
`resources/css/app.css`.

**Second silent killer: Livewire is never started, so every button is dead.**
The starter layout uses `@livewireScriptConfig` (config-only, manual mode) and
ships an empty `resources/js/app.js`. The Livewire runtime is then never loaded:
pages render fine but `wire:click` / `wire:model` do nothing, no
`/livewire/update` request fires, and there is no console error (`window.Livewire`
is `undefined`). Fix in `resources/js/app.js`:

```js
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
window.Alpine = Alpine;
Livewire.start();
```

then `npm run build`. Verify in a real browser that `window.Livewire` is an
object and a `wire:click` button fires `POST /livewire/update` (200).

Checklist:

1. **Secure the site in Herd.** A parked-but-unsecured site has no https vhost and
   no cert, yet the browser still resolves `https://<slug>.test` (typed-URL default
   or HSTS) and falls through to Herd's default handler: a cert error and, in
   practice, a 500 (the default handler can route to a different PHP runtime than
   the one your dependencies require). Run `herd secure <slug>`. This is a
   Herd-environment change (a generated nginx vhost + cert), not a repo change, so
   it is not committed with the portal. Confirm
   `~/Library/Application Support/Herd/config/valet/Nginx/<slug>.test` exists and
   uses `fastcgi_pass $herd_sock;` (routes to the active PHP, e.g. 8.4).
2. **Set `APP_URL=https://<slug>.test`.** Laravel generates in-request asset URLs
   (`@vite`/`asset()`) from the incoming request scheme, so a stale
   `http://localhost` does **not** by itself break CSS on a secured https domain —
   the page still renders once the site is secured. Set it anyway as hygiene for
   absolute URLs built outside a request context (mail, queued jobs, redirects,
   signed URLs), which fall back to `APP_URL`. (`ASSET_URL` only when assets live on
   a separate host; leave unset otherwise.)
3. **No stale `public/hot`.** If a `public/hot` file exists (left by a previous
   `npm run dev`), `@vite` loads every asset from the Vite dev-server
   (`https://<slug>.test:<port>`); when that server is not running you get no CSS.
   `npm run build` does **not** delete it. Stop the dev-server and `rm public/hot`,
   or keep `npm run dev` running. (A running dev-server with a `public/hot` is fine
   — that is the normal hot-reload mode.)
4. **Verify assets load over `/build/`.** `curl -sk https://<slug>.test/login` and
   confirm the `<link>`/`<script>` tags point at `https://<slug>.test/build/...`
   (built files), not at a dev-server port; then confirm each returns `200`
   (`text/css` / `text/javascript`). Flux serves its own assets at
   `https://<slug>.test/flux/flux.js` — that must be `200` too.

## Troubleshooting (from the README)

| Symptom | Cause |
| --- | --- |
| `LogicException: Missing starter route class config [...]` | A required `auth.controllers.*` key is empty |
| Routes return 404 | The starter route bridge is not required from `routes/web.php` |
| Blank/unstyled page | `layouts/app.blade.php` does not delegate to `mortelos-starter::layouts.app` |
| Sidebar/search/chat missing | The matching resolver or component is still `null` (optional, degrades silently) |

Complete internal MortelOS host apps demonstrate the full wiring (route bridge,
defaults-based `config/starter.php` with host resolvers, layout delegation,
dashboard/governance slots, inbox detail routing). Use them for shape and
intent, not as a rule that every host uses the same class names or tenant model.
