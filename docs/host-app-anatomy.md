# Host App Anatomy

What a working MortelOS host app looks like on disk. Use this when scaffolding
a new host or auditing an existing one. The reference implementation is
**UteqOS**; this document generalizes the shape.

## Directory layout

```
your-host-app/
├── app/
│   ├── Actions/
│   │   ├── Auth/
│   │   │   └── ResolvePostLoginRedirect.php       # impl of auth.post_login_redirect_resolver
│   │   └── <Domain>/                              # one folder per domain area
│   ├── Console/
│   │   └── Commands/
│   │       └── StarterDoctor.php                  # host-side diagnostic (see knowledge/07)
│   ├── Http/
│   │   └── Controllers/
│   │       └── Auth/
│   │           ├── AcceptInvitationController.php
│   │           ├── PasskeyAuthenticatedController.php
│   │           ├── PasswordLoginController.php
│   │           └── TenantSelectController.php
│   ├── Models/                                    # host-owned: User, Tenant, Membership, Invitation
│   ├── Policies/
│   ├── Projections/
│   ├── Providers/
│   └── Support/                                   # host-owned resolvers
│       ├── StarterSidebarNavigationResolver.php
│       ├── StarterUniversalSearchResolver.php
│       ├── StarterGovernanceResolver.php
│       ├── StarterGovernanceAccessResolver.php
│       ├── StarterUsersResolver.php
│       ├── StarterOnboardingResolver.php
│       ├── StarterInboxItemTypeResolver.php
│       └── StarterDashboardProudMessageResolver.php
├── config/
│   ├── starter.php                                # merges package defaults + host bindings
│   └── <package>.php                              # one per consumed package
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
│       └── TestAccountSeeder.php                  # seeded users for dev verification
├── docs/
│   └── portals/<slug>/                            # one folder per portal
│       ├── capability-map.md
│       ├── build-plan.md
│       └── progress.md
├── packages/                                      # path-repo packages developed alongside the host
│   ├── entity-graph/
│   ├── policy-studio/
│   └── document-studio/
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php                      # delegates to mortelos-starter::layouts.app
│       └── shared/                                # host-owned shared components (sidebar-nav etc.)
├── routes/
│   ├── ai.php                                     # mounts UteqOSServer (MCP)
│   ├── starter.php                                # bridge: requires the package routes
│   └── web.php                                    # requires routes/starter.php
├── tests/
│   ├── Feature/
│   │   ├── Architecture/                          # host wiring contract tests
│   │   └── Auth/
│   └── Unit/
│       └── Architecture/
├── .mortelos/
│   └── package-decisions.md                       # one entry per surface
├── .claude/
│   └── skills/
│       └── portal-kickoff -> ../../vendor/mortelos/starter/.claude/skills/portal-kickoff
├── AGENTS.md                                      # host-specific agent rules; can refer to package AGENTS.md
├── CLAUDE.md                                      # pointer to AGENTS.md + host specifics
├── composer.json
└── phpunit.xml
```

## What lives where (cheat sheet)

| Concern | Lives in | Why |
| --- | --- | --- |
| Layout shell | `vendor/mortelos/starter` | Package owns it; host delegates |
| Auth pages (login forms) | `vendor/mortelos/starter` views | Package owns the shell |
| Auth controllers (logic) | `app/Http/Controllers/Auth/` | Host knows password hashing, passkey lib, invitation tokens |
| Post-login redirect | `app/Actions/Auth/ResolvePostLoginRedirect.php` | Host knows where users go (often based on roles) |
| User / Tenant / Membership / Invitation models | `app/Models/` | Host-owned (§9) |
| Tenant resolution (e.g. `stancl/tenancy`) | `app/Support/StanclTenantResolver.php` | Host-specific tenancy choice |
| Navigation tree (sidebar) | `app/Support/StarterSidebarNavigationResolver.php` | Host knows which sections each role sees |
| Universal search results | `app/Support/StarterUniversalSearchResolver.php` | Host knows where to look |
| Governance access (canManage) | `app/Support/StarterGovernanceAccessResolver.php` | Host owns role mapping |
| User management actions | `app/Support/StarterUsersResolver.php` | Host owns membership and invitations |
| Dashboard widgets | host `app/Livewire/Dashboard/` OR a package | Choose per `package-now` / `-ready` |
| Policy abilities | `app/Policies/` | Host seeds defaults; Policy Studio reviews changes |
| Connectors (Moneybird, Fireflies, etc.) | each in their own package under `packages/` | Reusable across MortelOS installs |
| Portal capability docs | `docs/portals/<slug>/` | Per-portal; tracked in git |
| MCP server mount | `routes/ai.php` | Host mounts; server is provided by `uteq/mortel` |

## `config/starter.php` (host shape)

The complete shape any host follows (UteqOS pattern, generalized):

```php
<?php

declare(strict_types=1);

use App\Actions\Auth\ResolvePostLoginRedirect;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\PasskeyAuthenticatedController;
use App\Http\Controllers\Auth\PasswordLoginController;
use App\Http\Controllers\Auth\TenantSelectController;
use App\Support\StarterDashboardProudMessageResolver;
use App\Support\StarterGovernanceAccessResolver;
use App\Support\StarterGovernanceResolver;
use App\Support\StarterInboxItemTypeResolver;
use App\Support\StarterOnboardingResolver;
use App\Support\StarterSidebarNavigationResolver;
use App\Support\StarterUniversalSearchResolver;
use App\Support\StarterUsersResolver;

$defaults = require __DIR__.'/../vendor/mortelos/starter/config/starter.php';

return array_replace_recursive($defaults, [
    'auth' => [
        'post_login_redirect_resolver' => ResolvePostLoginRedirect::class,
        'controllers' => [
            'accept_invitation'     => AcceptInvitationController::class,
            'passkey_authenticated' => PasskeyAuthenticatedController::class,
            'password_login'        => PasswordLoginController::class,
            'tenant_select'         => TenantSelectController::class,
        ],
    ],

    'layout' => [
        'sidebar_nav_component'      => 'shared.sidebar-nav',
        'universal_search_component' => 'starter::shared.universal-search',
    ],

    'navigation' => [
        'sidebar_resolver'          => StarterSidebarNavigationResolver::class,
        'universal_search_resolver' => StarterUniversalSearchResolver::class,
    ],

    'governance' => [
        'resolver'        => StarterGovernanceResolver::class,
        'access_resolver' => StarterGovernanceAccessResolver::class,
    ],

    'users' => [
        'resolver' => StarterUsersResolver::class,
    ],

    'onboarding' => [
        'resolver' => StarterOnboardingResolver::class,
    ],

    'dashboard' => [
        'proud_message_resolver' => StarterDashboardProudMessageResolver::class,
    ],

    'inbox' => [
        'item_type_resolver' => StarterInboxItemTypeResolver::class,
    ],
]);
```

Optional keys (`chat`, `passkey_authentication_options`, governance widget
components) get added when the capability map calls for them.

## `routes/starter.php` and `routes/web.php`

```php
// routes/starter.php
<?php

declare(strict_types=1);

require base_path('vendor/mortelos/starter/routes/starter.php');
```

```php
// routes/web.php
<?php

declare(strict_types=1);

require __DIR__.'/starter.php';

// host-specific routes follow
```

## `resources/views/layouts/app.blade.php`

```blade
{{-- Minimum delegation: --}}
@include('mortelos-starter::layouts.app', ['slot' => $slot])
```

If the host needs wrapping behavior (e.g. a marketing band above the shell),
wrap the include rather than replacing it. Don't fork the layout.

## Test accounts (host seeded)

Hosts ship a `TestAccountSeeder` with predictable users for verification:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@<host>.test` | `password` |
| Account manager | `am@<host>.test` | `password` |
| Customer | `customer@<host>.test` | `password` |

The verification checklist (see `knowledge/07-test-and-verify.md`) refers to
these accounts. Seed in `DatabaseSeeder::run()`:

```php
$this->call(TestAccountSeeder::class);
```

## What the agent should produce when scaffolding a new host

A correct new host has, after the `portal-kickoff` skill or
[`docs/init-host-app.md`](init-host-app.md) recipe runs:

- [ ] Composer requires `mortelos/starter`
- [ ] Composer requires `uteq/mortel` (or `mortelos/framework` when migrated)
- [ ] `routes/starter.php` exists with the bridge
- [ ] `routes/web.php` requires it
- [ ] `resources/views/layouts/app.blade.php` delegates
- [ ] `config/starter.php` merges defaults + fills auth contracts
- [ ] `app/Http/Controllers/Auth/*` exist (published from stubs initially)
- [ ] `app/Actions/Auth/ResolvePostLoginRedirect.php` exists
- [ ] `app/Models/User` (with passkey/tenant traits as the host requires)
- [ ] At least one seeded admin account
- [ ] `.mortelos/package-decisions.md` exists (even if empty initially)
- [ ] `app/Console/Commands/StarterDoctor.php` exists and `php artisan starter:doctor` returns green
- [ ] `tests/Feature/Architecture/StarterWiringTest.php` exists and passes
- [ ] Manual flow: `login → tenant-select → dashboard` returns 200
