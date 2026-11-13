# Upgrading

## To 0.15.0

The starter gains two-factor sign-in (Laravel Fortify, two-factor features only) and sign-in with Google and Microsoft
(`mortelos/social-login`). Nothing changes for a user who does not use them: no button shows without keys, and
two-factor is off until a user sets it up. Guides: `docs/two-factor.md`, `docs/social-login.md`.

| 0.14 | 0.15 |
| --- | --- |
| no second factor; `PasswordLoginController` runs `Auth::attempt()` | `PasswordLoginController` checks the password and, with two-factor on, hands over to Fortify's code challenge (route `two-factor.login`) |
| `users` without two-factor columns | `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` (migration `2014_10_12_200000_add_two_factor_columns_to_users_table`, Fortify's own name, skips existing columns) |
| no social login | `mortelos/social-login`, table `social_identities`, buttons in `auth.password-form`, card on `/settings` |
| composer: no `laravel/fortify`, `mortelos/social-login` | `laravel/fortify ^1.42`, `mortelos/social-login ^0.3` |

What an app built on the starter must do:

1. Take the new files: `config/fortify.php`, `config/social-login.php`, `lang/nl/auth.php`, `lang/nl.json`,
   `lang/vendor/social-login/`, the two migrations, `app/Actions/Account/*TwoFactor*.php`, `RegenerateRecoveryCodes.php`,
   `app/Support/PasswordConfirmation.php`, the views under `resources/views/auth`, `resources/views/components/settings`
   and the changes in `routes/starter.php`, `AppServiceProvider` (limiter `two-factor`, `Fortify::ignoreRoutes()`),
   `StarterServiceProvider`, `User` (trait `TwoFactorAuthenticatable`) and the `.env.example` keys.
2. Run `composer update -W`, clear the new packages in `vet.json` with `bin/vet-agent`, then `php artisan migrate`.
3. An app that already runs Fortify (CivilGrow) keeps its `users` columns and its encrypted secrets, as long as `APP_KEY`
   stays the same. Keep `fortify.guard` at the default guard.
4. Run `php artisan starter:doctor`: it checks the Fortify wiring, the limiter, the routes and the social login table.

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
