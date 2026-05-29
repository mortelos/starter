# stubs/ — publishable scaffolding for host apps

These files get published into a host app via:

```bash
php artisan vendor:publish --tag=mortelos-starter-stubs
```

What gets published:

| Source | Destination in host | Purpose |
| --- | --- | --- |
| `stubs/Auth/Controllers/PasswordLoginController.php` | `app/Http/Controllers/Auth/PasswordLoginController.php` | Minimal email + password login |
| `stubs/Auth/Controllers/PasskeyAuthenticatedController.php` | `app/Http/Controllers/Auth/PasskeyAuthenticatedController.php` | Passkey login handler stub |
| `stubs/Auth/Controllers/AcceptInvitationController.php` | `app/Http/Controllers/Auth/AcceptInvitationController.php` | Invitation accept stub |
| `stubs/Auth/Controllers/TenantSelectController.php` | `app/Http/Controllers/Auth/TenantSelectController.php` | Tenant picker stub (auto-picks single tenant) |
| `stubs/Auth/Actions/ResolvePostLoginRedirect.php` | `app/Actions/Auth/ResolvePostLoginRedirect.php` | Post-login redirect resolver, defaults to `/dashboard` |
| `stubs/config/starter.php` | `config/starter.php` | Recommended host config shape; merges package defaults |

## Why these stubs exist

The starter requires five non-null `auth.*` config keys to boot. Without
stubs, a fresh host can't reach `dashboard` until the developer writes those
controllers. The stubs let the host boot **the same hour you install the
package**, then get replaced with real implementations as the capability map
specifies the auth flow.

## What's deliberately omitted

- A real passkey library — passkey is host-specific; the stub returns 501
- A tenant model — host-owned (§9 of `docs/building-portals.md`)
- Invitation token persistence — host-owned
- Role-aware post-login routing — the stub returns `/dashboard` for everyone

## After publishing

1. The published files use the host's `App\…` namespace
2. The host's `config/starter.php` already points at them
3. `php artisan starter:doctor` should return green
4. `php artisan serve` and `login → tenant-select → dashboard` should work
   with a seeded user

When you replace a stub with a real implementation, delete the stub-specific
docblock and write a proper class docblock.
