# Init a Host App

Empty Laravel project → working MortelOS host in one session. Each step is
runnable; nothing relies on prior MortelOS knowledge. See
[host-app-anatomy.md](host-app-anatomy.md) for the end state this produces.

## Prerequisites

| Tool | Version |
| --- | --- |
| PHP | `^8.4` |
| Composer | `^2.7` |
| Node | `^20` (for Vite + Tailwind) |
| Laravel | `^13.0` (when ready) — until then, latest Laravel that ships with `illuminate/*` `^13` compatibility |

```bash
php --version       # must be 8.4+
composer --version  # must be 2.7+
node --version      # must be 20+
```

If any of those fail, set them up first. On macOS with Homebrew:

```bash
brew install php@8.4 composer node@20
```

## Step 1 — Create the Laravel skeleton

```bash
composer create-project laravel/laravel my-host
cd my-host
```

## Step 2 — Install starter and core

```bash
composer require mortelos/starter
composer require uteq/mortel        # core: entities, events, projections, MCP
composer require mortelos/ui        # design primitives (consumed by starter views)
composer require livewire/flux-pro  # Flux UI Pro; if you have the auth token already
```

Don't have a Flux Pro token yet? Skip the last line and the layout will still
load, but you'll be on the Flux community components. Add Flux Pro before
shipping; it's the design system.

## Step 3 — Publish defaults and stubs

```bash
php artisan vendor:publish --tag=mortelos-starter           # publishes config/starter.php
php artisan vendor:publish --tag=mortelos-starter-stubs     # publishes auth controller stubs
```

After this, the host has:

- `config/starter.php` — defaults from the package, ready for your overrides
- `app/Http/Controllers/Auth/PasswordLoginController.php`
- `app/Http/Controllers/Auth/PasskeyAuthenticatedController.php`
- `app/Http/Controllers/Auth/AcceptInvitationController.php`
- `app/Http/Controllers/Auth/TenantSelectController.php`
- `app/Actions/Auth/ResolvePostLoginRedirect.php`

The stubs are deliberately minimal — they let you boot. Replace them with real
implementations once the portal capability map specifies your auth flow.

## Step 4 — Wire the route bridge

Create `routes/starter.php`:

```php
<?php

declare(strict_types=1);

require base_path('vendor/mortelos/starter/routes/starter.php');
```

Edit `routes/web.php` to require it:

```php
<?php

declare(strict_types=1);

require __DIR__.'/starter.php';
```

## Step 5 — Delegate the layout

Replace `resources/views/layouts/app.blade.php` with:

```blade
@include('mortelos-starter::layouts.app', ['slot' => $slot])
```

## Step 6 — Edit the published `config/starter.php`

Replace the published file with the merge pattern:

```php
<?php

declare(strict_types=1);

use App\Actions\Auth\ResolvePostLoginRedirect;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\PasskeyAuthenticatedController;
use App\Http\Controllers\Auth\PasswordLoginController;
use App\Http\Controllers\Auth\TenantSelectController;

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
]);
```

The merge pattern preserves package defaults you don't override (dashboard
widgets, intake detail types, etc.).

## Step 7 — Seed at least one user

Edit `database/seeders/DatabaseSeeder.php`:

```php
public function run(): void
{
    User::factory()->create([
        'name'     => 'Admin',
        'email'    => 'admin@example.test',
        'password' => bcrypt('password'),
    ]);
}
```

```bash
php artisan migrate
php artisan db:seed
```

> If your User model needs a tenant column, add it via a migration before
> seeding. The starter stubs assume `session('tenant_id')` is set after
> tenant-select; the actual model is host-owned.

## Step 8 — Add the doctor command

```bash
php artisan make:command StarterDoctor
```

Paste the body from
[knowledge/07-test-and-verify.md](../knowledge/07-test-and-verify.md#the-doctor-command).

```bash
php artisan starter:doctor
```

If it returns green, you're booted.

## Step 9 — Smoke test

```bash
php artisan serve
```

Open `http://localhost:8000`. Expected flow:

1. `/` redirects to `/login`
2. Log in as `admin@example.test` / `password`
3. Redirects to `/auth/tenant-select`
4. Pick or create a tenant (host-owned UI)
5. Lands on `/dashboard`

If any step fails, see [knowledge/08-troubleshooting.md](../knowledge/08-troubleshooting.md).

## Step 10 — Record the first package decision

Even before any portal-specific code, record the host itself as
`workspace-only`:

```bash
mkdir -p .mortelos
cat >> .mortelos/package-decisions.md <<'EOF'
## Host App

- Surface: this app
- Decision: `workspace-only`
- Reason: Concrete customer host; portal-specific surfaces will be recorded as `package-ready` or `package-now` per the portal-kickoff skill.
- Date: $(date -I)
EOF
```

If the host has `mortelos/dev-tools` installed, use the artisan command instead:

```bash
php artisan mortelos:package-decision "Host App" \
  --decision=workspace-only \
  --surface=app \
  --reason="Concrete customer host." \
  --no-interaction
```

## Step 11 — Symlink the portal-kickoff skill

So Claude Code triggers it from this host:

```bash
mkdir -p .claude/skills
ln -s vendor/mortelos/starter/.claude/skills/portal-kickoff .claude/skills/portal-kickoff
```

## Step 12 — Add architecture tests

```bash
php artisan make:test Architecture/StarterWiringTest
```

Use the contract checklist in
[knowledge/07-test-and-verify.md](../knowledge/07-test-and-verify.md#host-level-architecture-tests-in-the-host-repo-eg-uteqos)
as the test body. Run:

```bash
vendor/bin/pest --filter=Architecture
```

## Step 13 — Add the agent contract files

Create the host's own `AGENTS.md` (host-specific rules) and `CLAUDE.md`:

```markdown
# AGENTS.md (host-specific)

This is a MortelOS host app on top of `mortelos/starter` + `uteq/mortel`. The
package-level conventions live in `vendor/mortelos/starter/AGENTS.md`; read
that first.

Host-specific:
- Tenancy library: <stancl/tenancy or other>
- Identity provider: <Passport or other>
- Test accounts: see TestAccountSeeder
- Branding: <host>
```

```markdown
# CLAUDE.md (host-specific)

See AGENTS.md. The portal-kickoff skill is symlinked at
`.claude/skills/portal-kickoff`; it triggers on any "build a portal for X"
request.
```

## You're done

At this point:

- The host boots
- The doctor command returns green
- One smoke test passes manually
- The skill is symlinked for Claude
- Package-first governance is in place
- Architecture tests gate the wiring

Next: run the `portal-kickoff` skill (Claude) or follow
`docs/building-portals.md` §1 (other agents) to start the first portal.
