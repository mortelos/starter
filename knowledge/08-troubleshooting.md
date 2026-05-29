# 08 — Troubleshooting

Symptoms first, then causes and fixes. The README has the short table; this is
the extended version with concrete commands.

## Boot fails

### `LogicException: Missing starter route class config [starter.auth.controllers.X]`

The route file at `routes/starter.php:13` throws this when an `auth.controllers.*`
key is `null` in the merged config.

**Fix.** Open the host `config/starter.php`. Confirm the auth block contains a
class name for the failing key. Example:

```php
'auth' => [
    'controllers' => [
        'password_login' => App\Http\Controllers\Auth\PasswordLoginController::class,
        // ... others
    ],
],
```

If you don't have a controller yet, publish the stubs:

```bash
php artisan vendor:publish --tag=mortelos-starter-stubs --force
```

That writes `app/Http/Controllers/Auth/*.php` and
`app/Actions/Auth/ResolvePostLoginRedirect.php` into the host. Then point the
config at them.

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

## Config and publish issues

### Stubs not visible after publish

Cached config or wrong tag.

**Fix.**

```bash
php artisan config:clear
php artisan vendor:publish --tag=mortelos-starter-stubs --force
```

If the tag itself doesn't exist, the package version is older than the stubs
feature. Update:

```bash
composer update mortelos/starter
```

### Published views drift from package

You ran `php artisan vendor:publish --tag=mortelos-starter-views` once, edited
nothing, and now the published views are behind the package.

**Fix.** Don't publish views unless you genuinely customize them. Unpublish:

```bash
rm -rf resources/views/vendor/mortelos-starter
php artisan view:clear
```

Laravel will resolve `mortelos-starter::*` to the package views directly.

### Config merge isn't picking up package defaults

The host `config/starter.php` doesn't use `array_replace_recursive`.

**Fix.**

```php
$defaults = require __DIR__.'/../vendor/mortelos/starter/config/starter.php';

return array_replace_recursive($defaults, [
    'auth' => [ /* host overrides */ ],
]);
```

`array_merge` only merges top-level keys; recursive merge preserves nested
defaults like `dashboard.primary_widgets`.

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

## Symlink edit issues

This package is consumed by symlink in dev. Common gotchas:

| Symptom | Cause | Fix |
| --- | --- | --- |
| Edits to the package don't show in the host | Composer copied instead of symlinked | Re-install with `"prefer-stable": true` and an explicit `repositories` entry of type `path` with `"symlink": true` |
| `composer update mortelos/starter` removes your edits | The symlink replaced by a real install | Same fix as above; double-check `composer.json` `repositories` |
| Service provider changes not picked up | Autoload cache stale | `composer dump-autoload` in the host |
| `route:list` doesn't show starter routes | Route bridge not required, OR `routes/starter.php` cached | `php artisan route:clear` then re-check the bridge |

## Tests fail in CI but pass locally

- **Different PHP version**. Package requires `^8.4`. CI on 8.3 → fails.
- **Different Laravel**. Requires `^13.0` Illuminate components.
- **Missing extension**. Check `composer.json` `require` against CI image.
- **Stancl/tenancy not installed in CI**. Some hosts use it; the suggest is in
  `uteq/mortel`. Add to `require-dev` of the host's test environment.

## When you're stuck

1. Check the relevant `routes/starter.php:<line>` (always cited by error)
2. Run `php artisan starter:doctor`
3. Run `php artisan route:list --name=starter` and `--name=auth.`
4. Run `php artisan config:show starter`
5. Open `vendor/mortelos/starter/` and compare against your host wiring
6. If still stuck, fall back to the UteqOS reference host in
   `/Users/uteq/Sites/uteqos/` and diff
