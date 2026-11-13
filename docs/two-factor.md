# Two-factor sign-in

The starter asks for a code from an authenticator app after the password, and after a sign-in with Google or
Microsoft, for every user who has set it up. It uses Laravel Fortify's two-factor features only; the starter keeps
its own login, password and invitation flow.

## Set it up

1. The user opens **Instellingen**, card **Tweestaps**, enters their password and chooses **Tweestaps instellen**.
2. They scan the QR code (or type the key) in an authenticator app and enter the first code. Only then does it count
   (`two_factor_confirmed_at`), so a half-finished setup never locks anyone out. **Annuleren** drops an unconfirmed setup
   without a password.
3. The page shows eight recovery codes once (the display flag is a locked Livewire property: the browser cannot switch it on, only the actions after the password do). Each works one time, instead of the app. **Toon herstelcodes** shows them
   again, **Nieuwe herstelcodes** replaces them all, **Tweestaps uitzetten** deletes the secret and the codes. All three
   ask for the password.

## What happens at login

- Password: `PasswordLoginController` checks the password. With two-factor on it does not sign the user in. It leaves
  Fortify's challenge state in the session (`login.id`, `login.remember`) and sends them to `/two-factor-challenge`
  (route `two-factor.login`). Fortify signs them in when the code or a recovery code is right, so the `Login` event
  fires once, after the code.
- Social login: `mortelos/social-login` does the same hand-over (see [social-login.md](social-login.md)).
- One code check at a time per waiting user: Fortify reads the recovery codes, accepts a code and only then saves the
  replacement, so two requests at the same moment could both accept the same recovery code. The route runs
  `app/Http/Middleware/SerializeTwoFactorChallenge.php`, which holds `Cache::lock('two-factor-challenge:<user id>')`
  around the whole check. The second request then starts after the first replaced the code and is refused; one that
  cannot get the lock within `starter.auth.two_factor_lock_wait` seconds (5) gets a 429 and is not checked. The lock
  needs a cache store that can lock (the starter's `database` store, Redis, `array`; not `file` over several servers).
  Chosen over a row lock on the user because it also covers the code check, which does not lock the row.
- The limiter `two-factor` allows five attempts a minute per waiting user and IP (`AppServiceProvider`). Without a limiter
  Fortify's challenge route answers every code with a 500.

## Code

| Part | File |
| --- | --- |
| Settings card | `resources/views/components/settings/⚡two-factor.blade.php` |
| Actions (password as proof, own account only) | `app/Actions/Account/EnableTwoFactor.php`, `ConfirmTwoFactor.php`, `DisableTwoFactor.php`, `RegenerateRecoveryCodes.php` |
| Challenge page and routes | `resources/views/auth/two-factor-challenge.blade.php`, `routes/starter.php`, `app/Http/Middleware/SerializeTwoFactorChallenge.php` |
| Fortify config | `config/fortify.php` (only the two-factor feature; `fortify.guard` must stay `web`, the default guard) |
| Columns on `users` | `database/migrations/2014_10_12_200000_add_two_factor_columns_to_users_table.php` |

Fortify's own routes are switched off (`Fortify::ignoreRoutes()` in `AppServiceProvider`); a test fails when another one
appears. The starter registers the two it needs.

## Compatible with Fortify apps

The columns are Fortify's own (`two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`), the
migration has Fortify's own name and skips columns that exist, and the secret and the recovery codes are encrypted with
`APP_KEY` the way Fortify does it. An app that already runs Fortify two-factor (CivilGrow) keeps its users: moving
them over needs the same `APP_KEY` or the stored secrets cannot be read. Recovery codes are encrypted, not hashed, as in
Fortify: they can be shown again.

## Check it

`php artisan starter:doctor` checks the feature, the guard, the columns, the limiter and the two routes.
`composer test` has the feature tests; `npm run build && composer test:browser` runs `tests/Browser/TwoFactorLoginTest.php`
(password and code, recovery code, Google and code, linking Google, setting it up).
