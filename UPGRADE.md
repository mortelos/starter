# Upgrading

## To 0.18.0

The starter gains a change log at `/changelog`: every stored event as a readable line with who, what, part and when,
filters, a CSV export and Ongedaan maken through the `UndoRegistry`. Guide: `docs/change-log.md`. Nothing changes for a
user without the new ability `changelog.view`.

| 0.17 | 0.18 |
| --- | --- |
| no change log | page `changelog` and export `changelog.export` in `routes/starter.php`, a link in the profile menu |
| abilities `governance.manage`, `users.manage`, `invitations.manage`, `horizon.view` | also `changelog.view` (scope Action) |
| `events` with indexes on `created_at` and `event_class` | also `events_created_at_id_index` and `events_actor_created_at_index` |
| one `PolicyCache` per `CheckPolicy` | one per request or job (`scoped` in `StarterServiceProvider`) |
| no outlets of the starter itself | `resources/mortelos/outlets.json`: the registry `ChangeLogDescriptions` (tag `changelog.descriptions`) and the contracts `DescribesChanges`, `ChangeLogAgents`, `ChangeLogAccess`, `ChangeLogArchive` |

What an app built on the starter must do:

1. Take the new files: `app/Actions/ChangeLog/`, `app/Aggregates/ChangeLogAggregate.php`, `app/Events/ChangeLog/`,
   `app/Contracts/ChangeLog/`, `app/Support/ChangeLog/`, `app/Enums/ChangeLogActorKind.php`,
   `app/Enums/ChangeLogUndoState.php`, `app/Exceptions/ChangeAlreadyUndoneException.php`,
   `app/Exceptions/UndoRefusedException.php`, `app/Http/Controllers/ChangeLogExportController.php`,
   `resources/views/pages/changelog/`, `resources/mortelos/outlets.json`, the two migrations `2026_11_13_2000*` and the
   tests `tests/Feature/ChangeLog*`, `tests/Feature/DoctorChangeLogTest.php`, `tests/Browser/ChangeLogVerifyTest.php`
   with `tests/Support/ChangeLog/` (required in `tests/Pest.php`).
2. Take the changes in `routes/starter.php`, `StarterServiceProvider` (bindings, the tag, `PolicyCache`),
   `StarterGovernanceGate` (`SCOPES` and `allowsActor`), `config/event-sourcing.php` (`changelog.change.undone`), the
   topbar, `StarterDoctor` and `tests/Feature/GovernanceSeederTest.php`.
3. Run `php artisan migrate`: it adds the indexes and gives an existing `owner` role `changelog.view`.
4. Register a sentence and a part for each of your own event types in a `DescribesChanges` class tagged
   `changelog.descriptions`, and bind the outlets you need (`docs/change-log.md`). Until then your events read "Wijziging vastgelegd" under "Overig", without undo.
5. Run `php artisan starter:doctor`.

## To 0.17.0

The starter gains two-factor sign-in (Laravel Fortify, two-factor features only), sign-in with Google and Microsoft
(`mortelos/social-login`) and Horizon. Nothing changes for a user who does not use them: no button shows without keys,
two-factor is off until a user sets it up, and Horizon is off until `QUEUE_CONNECTION=redis`. Guides:
`docs/two-factor.md`, `docs/social-login.md`, `docs/horizon.md`.

| 0.14 | 0.17 |
| --- | --- |
| no second factor; `PasswordLoginController` runs `Auth::attempt()` | `PasswordLoginController` checks the password and, with two-factor on, hands over to Fortify's code challenge (route `two-factor.login`) |
| `users` without two-factor columns | `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` (migration `2014_10_12_200000_add_two_factor_columns_to_users_table`, Fortify's own name, skips existing columns) |
| no social login | `mortelos/social-login`, table `social_identities`, buttons in `auth.password-form`, card on `/settings` |
| no Horizon; `QUEUE_CONNECTION` database or sync | `laravel/horizon`, `config/horizon.php` with one supervisor `default`, `/horizon` behind the ability `horizon.view`, `horizon:snapshot` in the scheduler |
| composer: no `laravel/fortify`, `mortelos/social-login`, `laravel/horizon` | `laravel/fortify ^1.42`, `mortelos/social-login ^0.3`, `laravel/horizon ^5.51` |

What an app built on the starter must do:

1. Take the new files: `config/fortify.php`, `config/social-login.php`, `lang/nl/auth.php`, `lang/nl.json`,
   `lang/vendor/social-login/`, the two migrations, `app/Actions/Account/*TwoFactor*.php`, `RegenerateRecoveryCodes.php`,
   `app/Support/PasswordConfirmation.php`, `app/Http/Middleware/SerializeTwoFactorChallenge.php`, the views under `resources/views/auth`, `resources/views/components/settings`
   and the changes in `routes/starter.php`, `AppServiceProvider` (limiter `two-factor`, `Fortify::ignoreRoutes()`),
   `StarterServiceProvider`, `User` (trait `TwoFactorAuthenticatable`) and the `.env.example` keys.
2. Run `composer update -W`, clear the new packages in `vet.json` with `bin/vet-agent`, then `php artisan migrate`.
3. An app that already runs Fortify (CivilGrow) keeps its `users` columns and its encrypted secrets, as long as `APP_KEY`
   stays the same. Keep `fortify.guard` at the default guard.
4. Horizon: take `config/horizon.php`, `app/Providers/HorizonServiceProvider.php` (register it in `bootstrap/providers.php`),
   `app/Support/HorizonAccess.php`, the ability `horizon.view` in `StarterGovernanceGate::SCOPES`, the migration
   `2026_11_13_100000_grant_horizon_access` (gives an existing `owner` role the ability) and `horizon:snapshot` in
   `routes/console.php`. An app with jobs lists them in `queuedJobs()` of `tests/Feature/HorizonConfigTest.php` and gives
   long jobs a supervisor and a connection of their own, with `timeout` and `retry_after` in the order the test checks.
5. Run `php artisan starter:doctor`: it checks the Fortify wiring, the limiter, the routes, the social login table and, on
   Redis, the Horizon supervisors and the connection.

## To 0.14.0

The starter runs on `mortelos/framework` 0.7 and its code is English. Read the framework's
`UPGRADE.md` first (publish and run the new migration); this lists what changed in the starter itself.

| 0.13 | 0.14 |
| --- | --- |
| `mortelos/framework` `^0.6.41` | `^0.7` |
| `mortelos/ui` `^0.5.1`, `mortelos/app-standards` `^0.1.3` | `^0.5.2`, `^0.1.4` |
| dev: `mortelos/dev-tools` `^0.1.7`, `mortelos/agent-standards` `^0.1.14` | `^0.1.8`, `^0.1.15` |
| Livewire event `overzicht-saved` (sidebar listens, universal search dispatches) | `overview-saved` (handler `onOverviewSaved`) |
| route name `overzichten.show` (the sidebar links to it, the host defines it) | `overviews.show` |
| inbox filter types `voorstel`, `notificatie`, `actie` | `proposal`, `notification`, `action` |

What an app built on the starter must do:

1. Rename the `overzichten.show` route to `overviews.show`, and rename listeners of `overzicht-saved` to `overview-saved`.
2. Update stored inbox types and every other renamed value through the framework migration.
3. Run `composer update -W`, then `php artisan mortelos:agent-rules:publish --target=AGENTS.md` (the `post-autoload-dump` script does this) so `AGENTS.md` carries the new "Code Language" rule.
4. `tests/Feature/CodeLanguageGuardTest.php` runs `CodeLanguageGuard`; `composer test:arch` includes it. Customer-facing Dutch copy stays Dutch.
