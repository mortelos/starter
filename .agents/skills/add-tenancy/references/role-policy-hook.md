# Role / policy hook & switcher (R4, R7, D11)

Make role-gating tenant-aware without inventing a parallel access layer. The
seam mirrors `mortelos/framework`'s shapes 1:1, so adopting the framework later
is a find-replace, not a rewrite. Roles and policies are **owner-editable DB
data, deny-by-default** (D11) — never hardcoded role names, never config-in-code.

## 1. The access seam — mirror framework 1:1 (R4)

| Template | Destination |
|----------|-------------|
| `templates/shared/Access/AccessActor.php.stub` | `app/Access/AccessActor.php` |
| `templates/shared/Access/ActorContext.php.stub` | `app/Access/ActorContext.php` |
| `templates/shared/Access/SystemActor.php.stub` | `app/Access/SystemActor.php` |
| `templates/shared/Access/DeniedActor.php.stub` | `app/Access/DeniedActor.php` |
| `templates/shared/TrustLevel.php.stub` | `app/Enums/TrustLevel.php` |

These match `Mortel\Access\*` and `Mortel\Enums\TrustLevel` byte-for-byte in
signature (verified against the framework source). **Framework adoption** is a
mechanical find-replace `App\Access → Mortel\Access` (and, inside `ActorContext`,
`App\Models\Role → Mortel\Models\Role`). Do not bind these to framework
interfaces: framework maps `Mortel\* → src/` via PSR-4, so a host shim under
`Mortel\*` would fatal on a duplicate class once framework installs (R4).

## 2. The page gate (R7)

| Template | Destination |
|----------|-------------|
| `templates/shared/GovernanceGate.php.stub` | `app/Contracts/GovernanceGate.php` |
| `templates/shared/TenantGovernanceGate.php.stub` | `app/Access/TenantGovernanceGate.php` |

`governance.blade.php` already calls
`app(config('starter.governance.access_resolver'))->canManage(auth()->user())`
and treats a null resolver as deny. Activate the gate by pointing that config at
the contract (see the bindings snippet from `framework-binding.md`):

```php
// config/starter.php
'governance' => [
    'access_resolver' => \App\Contracts\GovernanceGate::class, // was null
],
```

`TenantGovernanceGate` is fail-closed: no user, no initialized tenant, or no
tenant-scoped role → deny. When framework is present it delegates to
`Mortel\Actions\Policies\CheckPolicy` **unchanged** (R7), so operate-mode keeps
one policy engine; when absent it checks the host `policies` data, where the
absence of an explicit `allow` row is a denial.

The same seam covers the `users` surface (`users.blade.php` already calls
`canManage()`); point its resolver at the gate the same way if the portal gates
user management.

## 3. Roles & policies as DB data (D11)

| Template | Destination | Notes |
|----------|-------------|-------|
| `templates/shared/Role.php.stub` | `app/Models/Role.php` | id/name/description/trust_config |
| `templates/shared/Policy.php.stub` | `app/Models/Policy.php` | role_id/action/effect |
| `templates/shared/create_roles_policies_tables.php.stub` | see placement | roles + policies tables |

**Placement is driver-specific** (the tenant is the scope):

- `database` driver → put the roles/policies migration in
  `database/migrations/tenant/`. Each tenant database holds its own roles and
  policies; no `tenant_id` column needed (the database is the boundary).
- `row` driver → put it in `database/migrations/`, add a `tenant_id` column to
  both tables, and add the `BelongsToTenant` trait to `Role` and `Policy` so
  rows are scoped within the shared database.

Seed at least one role with a `governance.manage` allow policy for the portal
owner, so the governance surface is reachable after wiring (deny-by-default
means nothing is manageable until a policy grants it).

## 4. Membership + switcher (D5)

| Template | Destination | Notes |
|----------|-------------|-------|
| `templates/shared/create_tenant_user_table.php.stub` | `database/migrations/` | CENTRAL pivot (both drivers) |
| `templates/shared/User.tenants.snippet` | merge into `app/Models/User.php` | `tenants()` belongsToMany, pivot `role_id` |
| `templates/shared/tenant-switcher.blade.stub` | `resources/views/livewire/shared/tenant-switcher.blade.php` | shown only when >1 tenant |

The pivot is central (users and tenants are central). `role_id` is a soft
reference resolved only after tenancy initializes (tenant DB for `database`,
scoped rows for `row`) — no cross-connection FK.

Reinstate the switcher by including the partial from `topbar.blade.php`:

```blade
{{-- in resources/views/livewire/shared/topbar.blade.php, before the profile dropdown --}}
@include('starter::shared.tenant-switcher')
```

The partial renders nothing unless the user belongs to >1 tenant, so a
single-tenant user sees no switcher — exactly the stripped-starter behaviour,
restored only where it is earned. With slug-keyed identification, switching is a
link to the other tenant's `/{slug}` home.
