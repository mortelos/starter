# 08 — Troubleshooting

Symptoms first, then causes and fixes. The README has the short table; this is
the extended version with concrete commands.

## Boot fails

### `LogicException: Missing starter route class config [starter.auth.controllers.X]`

The route file at `routes/starter.php:13` throws this when an `auth.controllers.*`
key is `null` in the merged config.

**Fix.** Open `config/starter.php` and confirm the auth block contains a class
name for the failing key. Example:

```php
'auth' => [
    'controllers' => [
        'password_login' => App\Http\Controllers\Auth\PasswordLoginController::class,
        // ... others
    ],
],
```

The default config already points at working stubs under
`app/Http/Controllers/Auth/`. If those files got deleted, restore from git
or scaffold replacements before booting.

### Vite manifest not found

Frontend assets haven't been built yet.

**Fix.**

```bash
npm install --ignore-scripts
npm run build
```

For active development use `composer dev` which runs vite alongside the server.

### Routes return 404

The route bridge is not required from `routes/web.php`.

**Fix.**

```php
// routes/web.php
require __DIR__.'/starter.php';
```

```php
// routes/starter.php
require base_path('vendor/mortelos/starter/routes/starter.php');
```

### Blank or unstyled page

The host's `resources/views/layouts/app.blade.php` is not delegating to the
package layout.

**Fix.** Replace the host layout body with:

```blade
@include('mortelos-starter::layouts.app', ['slot' => $slot])
```

…or extend it if you need to add wrapping behavior, but always pass through.

### `Class App\Http\Controllers\Auth\X not found`

Composer autoload is stale, or the stub was edited and namespaced wrong.

**Fix.**

```bash
composer dump-autoload
```

Verify the namespace at the top of the file matches the directory.

### Login loops back to login

The `post_login_redirect_resolver` returns something invalid, or the session
isn't writing `tenant_id`.

**Fix.** Debug in `routes/starter.php:18-31` (the `/` handler):

1. After login, `Auth::check()` must be true. If not, `auth.controllers.password_login` is logging the user out.
2. `session('tenant_id')` must be set by the tenant-select store action. If not, the resolver redirects to tenant-select again. Check `TenantSelectController::store`.
3. `post_login_redirect_resolver->execute($user, $tenantId)` must return a string URL.

## Asset and dependency issues

### `mortelos/ui` cannot be installed

The package is private and requires SSH access to `github.com/mortelos/ui`.
The vcs repository is already declared in `composer.json`.

**Fix.**

```bash
ssh -T git@github.com   # confirm GitHub SSH access
composer install
```

If you need to install via HTTPS with a token, configure `auth.json` with a
Composer GitHub token.

### `View [layouts.guest] not found`

The login page expects `resources/views/layouts/guest.blade.php`.

**Fix.** Restore from git:

```bash
git restore resources/views/layouts/guest.blade.php
```

## Sidebar, search, governance, chat missing

These are **optional** and degrade silently. If `navigation.sidebar_resolver` is
`null`, the sidebar shell renders empty. To fill it:

1. Implement the resolver interface in the host (see contract table in
   `AGENTS.md §3` and `README.md`)
2. Bind it in `config/starter.php`:

```php
'navigation' => [
    'sidebar_resolver' => App\Support\StarterSidebarNavigationResolver::class,
],
```

3. Clear cache: `php artisan config:clear`

## Routing and provider issues

| Symptom | Cause | Fix |
| --- | --- | --- |
| `route:list` doesn't show starter routes | `routes/starter.php` not required from `routes/web.php`, or route cache stale | Confirm `require __DIR__.'/starter.php';` in `routes/web.php`; then `php artisan route:clear` |
| `StarterServiceProvider` changes not picked up | Autoload cache stale | `composer dump-autoload` |
| Config changes not visible | Config cache stale | `php artisan config:clear` |
| Compiled views render old layout | View cache stale | `php artisan view:clear` |

## Tests fail in CI but pass locally

- **Different PHP version**. Package requires `^8.4`. CI on 8.3 → fails.
- **Different Laravel**. Requires `^13.0` Illuminate components.
- **Missing extension**. Check `composer.json` `require` against CI image.
- **Stancl/tenancy not installed in CI**. Some hosts use it; the suggest is in
  `uteq/mortel`. Add to `require-dev` of the host's test environment.

## When you're stuck

1. Check the relevant `routes/starter.php:<line>` (always cited by error)
2. Run `php artisan starter:doctor`
3. Run `php artisan route:list`
4. Run `php artisan config:show starter`
5. Run `vendor/bin/pest` to see which baseline assertion fails
6. If still stuck, fall back to the UteqOS reference host in
   `/Users/uteq/Sites/uteqos/` and diff
