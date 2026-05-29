# 07 — Test and verify

Before claiming any change is done. Source method:
`docs/building-portals.md` §11; this file is the practical checklist.

## Levels of verification

| Level | What it proves | Effort |
| --- | --- | --- |
| `composer validate --strict` | The package manifest is valid | 1s |
| `vendor/bin/pint --dirty` | Code style is clean on changed files | 1s |
| `vendor/bin/pest` (this repo) | Package boot, route bridge, config merge work in isolation | seconds |
| `php artisan starter:doctor` (in host) | Five auth contracts filled, layout delegated, route bridge required | seconds |
| Architecture tests (host) | Tests under `tests/Feature/Architecture` + `tests/Unit/Architecture` pass | seconds |
| Manual smoke: `login → tenant-select → dashboard` | The user can actually sign in | minute |
| Capability-level Pest test in host | The capability you built actually works | minutes |

**Never** claim "works" based on `composer validate` alone. Always do at least
the smoke flow.

## Package-level tests (this repo)

Run them from `mortelos-starter/`:

```bash
composer install
vendor/bin/pest                 # full suite
vendor/bin/pest --filter=Architecture   # architecture only
composer validate --strict
vendor/bin/pint --test          # check-only; --dirty to fix changed files
```

The package ships:

- A boot test that loads the service provider via Orchestra Testbench
- A route test that asserts `LogicException` when `auth.controllers.*` is empty
- A config-merge test that asserts package defaults are preserved
- A views test that asserts `mortelos-starter::layouts.app` resolves
- A namespace test that asserts `starter::` Livewire components register

Add a new test whenever you change a contract.

## Host-level architecture tests (in the host repo, e.g. UteqOS)

Required architecture coverage in any host that consumes starter:

1. The route bridge is required from `web.php`
2. No starter route names leak into host `routes/web.php`
3. The host `layouts/app.blade.php` delegates to `mortelos-starter::layouts.app`
4. The layout renders the configured dynamic shell components
5. Published config matches the package default contract
6. Starter Livewire components resolve through the `starter::` namespace
7. Every required `auth.controllers.*` key resolves to an existing class

In UteqOS these live under `tests/Feature/Architecture/` and
`tests/Unit/Architecture/`. Steal the shapes for a new host.

## The doctor command

`php artisan starter:doctor` is the host-side diagnostic. It checks:

- Are all five `auth.controllers.*` keys non-null?
- Do the configured class names exist and implement the expected shape?
- Is the route bridge required from `routes/web.php`?
- Does the host layout delegate to `mortelos-starter::layouts.app`?
- Is `starter::` Livewire namespace registered? (Should be, via the package
  service provider — but verify in case `composer dump-autoload` is stale.)
- Are optional resolvers either filled or explicitly null?

Output is a green/red checklist. If red, the row tells you which key to fix.

If the host doesn't yet have a doctor command, ask the user to scaffold one
(it's a one-file Artisan command; pattern below):

```php
// app/Console/Commands/StarterDoctor.php
public function handle(): int
{
    $required = [
        'starter.auth.post_login_redirect_resolver',
        'starter.auth.controllers.password_login',
        'starter.auth.controllers.passkey_authenticated',
        'starter.auth.controllers.accept_invitation',
        'starter.auth.controllers.tenant_select',
    ];

    $missing = collect($required)->reject(fn ($k) => filled(config($k)))->all();

    foreach ($required as $key) {
        $this->{filled(config($key)) ? 'info' : 'error'}($key);
    }

    return $missing === [] ? self::SUCCESS : self::FAILURE;
}
```

## Manual verification template

When a change affects user-visible behavior, hand back a checklist the user can
run in under a minute:

```markdown
## Verification

URL: http://localhost:8000/dashboard
Account: admin@example.test / password

Steps:
1. Open the URL
2. You should land on the dashboard after passing login + tenant-select
3. Click [feature you built]
4. Expected: [what should happen]
5. Check Mailpit (http://localhost:8025) if an email/notification is triggered
```

For permission changes, include both a "should see" and a "should NOT see" role:

```markdown
## Verification

1. Log in as admin → expects [X] visible
2. Log in as customer → expects [X] hidden (or 403)
```

## When NOT to ship a verification checklist

Pure refactors with no behavior change, formatting only, doc edits, internal
helpers with no UI impact. Say so explicitly: "no behavior change, no manual
verification required."

## Common failure modes to catch in tests

- `auth.controllers.*` becomes `null` in a host config edit → caught by the
  doctor command and the `LogicException` test
- Layout customization drops the `mortelos-starter::layouts.app` include →
  caught by the host architecture test on layout delegation
- Published view in `resources/views/vendor/mortelos-starter/` drifts from the
  package version → optional architecture test that diffs the two; usually
  unnecessary, unpublish instead
- New Livewire page added under host `App\Livewire\` accidentally overrides a
  starter page → namespace conflict; surfaced by Livewire on boot
