# 07 — Test and verify

Before claiming any change is done. Source method:
`docs/building-portals.md` §11; this file is the practical checklist.

## Levels of verification

| Level | What it proves | Effort |
| --- | --- | --- |
| `vendor/bin/pint --dirty` | Code style is clean on changed files | 1s |
| `php artisan starter:doctor` | Five auth contracts filled, route bridge, layout, namespaces | seconds |
| `vendor/bin/pest` | Boot smoke + config-shape + capability tests pass | seconds |
| `vendor/bin/pest --filter=Architecture` | Architecture-only suite (when present) | seconds |
| Manual smoke: `login → dashboard` | The user can actually sign in | minute |
| Capability-level Pest test in `tests/Feature/<Capability>/` | The capability you built actually works | minutes |

**Never** claim "works" based on `composer validate` alone. Always do at least
the smoke flow or run the baseline Pest suite.

## Baseline tests that ship with the template

`tests/Feature/BootSmokeTest.php` covers:

- `GET /` redirects guests to `/login`
- `GET /login` returns 200 (vite assets must be built)
- `routes/starter.php` throws `LogicException` when an `auth.controllers.*` key
  is `null`
- `mortelos-starter::layouts.app` and `layouts.guest` views resolve
- Shell page Blade files exist on disk under `resources/views/livewire/pages/`
- `php artisan starter:doctor` returns success

`tests/Feature/ConfigShapeTest.php` covers the full contract surface for
`auth`, `layout`, `navigation`, `governance`, `users`, `onboarding`,
`dashboard`, `inbox`, and `chat`. If you add a new contract key, add a row to
this test.

## The doctor command

`php artisan starter:doctor` is the boot-baseline diagnostic. It checks:

- All five required `auth.controllers.*` keys are non-null
- Each configured class actually exists (autoload-resolvable)
- `routes/starter.php` exists at the expected path
- `routes/web.php` requires `routes/starter.php`
- `resources/views/layouts/app.blade.php` is present

Output is a green/red checklist. If red, the row tells you which key to fix.

The source for the command is `app/Console/Commands/StarterDoctor.php`. Extend
it as your portal adds host-owned contracts (tenant model presence, custom
resolvers, connector health, etc.).

## Adding a capability test

Pattern for a new `tests/Feature/Documents/ApproveDocumentTest.php` (after
running `tall-feature` or scaffolding manually):

```php
<?php

declare(strict_types=1);

use App\Actions\Documents\ApproveDocument;
use App\Events\DocumentApproved;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('emits DocumentApproved and updates the projection', function (): void {
    $user = User::factory()->admin()->create();
    $doc  = Document::factory()->pending()->create();

    $this->actingAs($user);

    app(ApproveDocument::class)->execute($doc->id, $user);

    // assertions on event, projection, audit trail
});

it('denies non-admin', function (): void {
    $user = User::factory()->create();
    $doc  = Document::factory()->pending()->create();

    $this->actingAs($user);

    expect(fn () => app(ApproveDocument::class)->execute($doc->id, $user))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});
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

For permission changes, include both a "should see" and a "should NOT see"
role:

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

- An `auth.controllers.*` key becomes `null` in a config edit → caught by the
  doctor command and the `LogicException` test in `BootSmokeTest`
- `resources/views/layouts/guest.blade.php` got removed → caught by the
  `BootSmokeTest` view-existence assertion
- Vite manifest missing in CI → assert the asset build runs before `pest`, or
  test the `/login` 200 only locally
- New Livewire page added accidentally collides with a starter page → surfaced
  by Livewire on boot; add an architecture test if you grow many of these
- Required contract key added but not seeded with a default → `ConfigShapeTest`
  fails on the missing key
