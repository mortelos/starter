# Optional audit via event-sourcing (R6, D12)

Wire this **only if the user wants an audit trail** (e.g. who changed which role
or policy, when). It is independent of tenancy isolation; skip it and the
tenancy still works. Its one defining property: it is **connection-agnostic**.

## Why connection-agnostic matters

Framework's `Mortel\Models\UteqStoredEvent` sets **no `$connection`**, so it
follows `config('database.default')`:

- single-tenant / central context → records into the central `events` table;
- multi-tenant, tenancy initialized → stancl has swapped the default
  connection, so the **same model** records into the tenant's `events` table.

One model, one `events` table shape, **no event migration** at the
single→multi-tenant transition. A bespoke audit log with a fixed connection
would re-create the "two stores" problem the moment the portal goes
multi-tenant. So mirror the framework convention exactly.

## Wiring (host-side, no framework change)

Add the dependency only when audit is wanted:

```bash
composer require "spatie/laravel-event-sourcing:^7.15"
```

| Template | Destination | Purpose |
|----------|-------------|---------|
| `templates/audit/UteqStoredEvent.php.stub` | `app/Models/UteqStoredEvent.php` | Stored-event model, **no `$connection`**, table `events` |
| `templates/audit/StoredEventObserver.php.stub` | `app/Observers/StoredEventObserver.php` | Fills actor/reason/aggregate columns on insert |
| `templates/audit/event-sourcing.php.snippet` | `config/event-sourcing.php` | Point `stored_event_model` at the host model |
| `templates/audit/create_events_table.php.stub` | `database/migrations/` | The `events` table on the **default** connection |
| `templates/audit/RolePolicyAuditObserver.php.stub` | `app/Observers/RolePolicyAuditObserver.php` | Append an audit row on every Role/Policy change |

Placement rule (same logic as roles/policies):
- `database` driver → the `events` migration goes in `database/migrations/` so
  the central DB gets it, **and** in `database/migrations/tenant/` so each
  tenant DB gets its own (the swap means tenant writes land in the tenant DB).
- `row` driver → one `events` table in `database/migrations/`; add a
  `tenant_id` column + `BelongsToTenant` if you want the audit itself scoped.

Register both observers in `StarterServiceProvider::boot()`:

```php
\App\Models\UteqStoredEvent::observe(\App\Observers\StoredEventObserver::class);
\App\Models\Role::observe(\App\Observers\RolePolicyAuditObserver::class);
\App\Models\Policy::observe(\App\Observers\RolePolicyAuditObserver::class);
```

## Framework adoption

When `mortelos/framework` is later installed it ships its own
`Mortel\Models\UteqStoredEvent` against the **same `events` table**. Drop the
host `stored_event_model` override (or point it at the framework model) and the
existing history is read unchanged — that is the payoff of mirroring the
convention rather than diverging.
