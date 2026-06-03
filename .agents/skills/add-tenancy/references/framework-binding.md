# Framework binding & tenant identification (R1, R3)

Both drivers run this. It does two things: (1) fills framework's
`TenantResolver` seam from the host so framework reads the live stancl tenant,
and (2) supplies the identification layer that initializes a tenant per request.

## 1. Declare the framework as a suggestion — never a requirement

Add to `composer.json` (do **not** add to `require`):

```jsonc
"suggest": {
    "mortelos/framework": "Operate-mode: AI governance, policy engine and tenant runtime. Required only when the portal graduates from build-only to operate-mode."
}
```

R1: the resolver is written against `Mortel\Contracts\TenantResolver` but is
bound **guarded**, so a framework-absent install runs on stancl alone and never
fatals on the missing interface.

## 2. The resolver + binding

| Template | Destination |
|----------|-------------|
| `templates/shared/StanclTenantResolver.php.stub` | `app/Support/StanclTenantResolver.php` |
| `templates/shared/StarterServiceProvider.bindings.snippet` | merge into `app/Providers/StarterServiceProvider.php` |

`StanclTenantResolver` implements the real contract (`id()` = active stancl
tenant key or null, `initialized()` = `tenancy()->initialized`, `data()` reads
tenant attributes). The binding is wrapped in
`if (interface_exists(\Mortel\Contracts\TenantResolver::class))`:

- **Framework present:** the host binding overrides framework's
  `NullTenantResolver` (framework binds it only `if (! bound)`, and we bind
  unconditionally inside the guard, so the host always wins).
- **Framework absent:** the guard is false, the class is never autoloaded, the
  container never resolves it. **Never typehint or instantiate
  `StanclTenantResolver` unguarded** — that would autoload the missing
  interface and fatal. The generated tests cover both states.

## 3. Tenant identification (R3)

Framework is ambient: it never parses host/subdomain/path/header — it reads the
already-initialized tenant. So the host owns identification. Two middlewares,
both ending in `tenancy()->initialize()`:

| Template | Destination | Use |
|----------|-------------|-----|
| `templates/shared/IdentifyTenant.php.stub` | `app/Http/Middleware/IdentifyTenant.php` | Web — slug-keyed |
| `templates/shared/IdentifyTenantByToken.php.stub` | `app/Http/Middleware/IdentifyTenantByToken.php` | MCP/API — token `tenant_id` claim |

### Registration & ordering — the hard rule

Register the middleware in `bootstrap/app.php` and put the identifier on the
tenant route group **before** any framework access middleware. Framework's
chain, when installed, is `Mortel\Http\Middleware\ResolveRole` →
`EnforcePolicy` → `EnforceTrustLevel`; all three assume an initialized tenant.

```php
// bootstrap/app.php  -> withMiddleware(function (Middleware $middleware) { ... })
$middleware->alias([
    'tenant'       => \App\Http\Middleware\IdentifyTenant::class,
    'tenant.token' => \App\Http\Middleware\IdentifyTenantByToken::class,
]);
```

Then apply `tenant` (web) / `tenant.token` (MCP) FIRST in the relevant group, so
the order is: **identify tenant → (framework ResolveRole → EnforcePolicy →
EnforceTrustLevel, when present) → controller**. Because the identifiers depend
only on stancl + the host `Tenant` model, they work standalone; when framework
is absent there simply are no downstream access middlewares, and that degrades
cleanly.

- **Web routes** that are tenant-scoped get `tenant`. With slug-keyed routing,
  put tenant pages under a `/{tenant}/...` prefix (the middleware also falls
  back to the first path segment) so the slug is available to resolve.
- **MCP/API routes** (`routes/ai.php` when present) get `tenant.token`.

### `row` driver note

`row` uses the **same** identification middleware — it still needs an
initialized tenant for `TenantScope` to filter. Only the isolation mechanism
differs (no connection swap). Do not skip identification on `row`; an
un-identified request to a scoped model leaks across tenants (see
`row-driver.md` §5).
