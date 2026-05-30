# Build Loop

Phases [5] and [6]. Build one capability end to end as a vertical slice, checkpoint
with the user, update `progress.md`, then move to the next. This skill owns the
MortelOS-specific shape; the **TALL skills own the Laravel/Livewire implementation**
when available. When they are not callable, use the [headless fallback](#headless-fallback)
below.

## The slice (build one capability fully)

Build in this order so each layer rests on a tested one below it:

1. **Entity plus links.** The domain object(s) for this capability and their
   relationships. Use the **`tall-model`** skill for the model plus migration plus
   factory.
2. **Events plus projection.** Emit the events the capability needs; build the
   read model the surface consumes. Keep projectors synchronous if the request
   needs consistency; add rebuild/verify if it can drift.
3. **Policy (deny-by-default).** Add the policy ability for every mutating action
   and sensitive read in this capability. Route visibility through
   `governance.access_resolver`, not component conditionals.
4. **Surface.** Build the chosen surface (starter page / package route / dashboard
   widget / chat widget / page widget). Register navigation and search resolver
   entries if the capability adds them. Register chat widgets through the
   `WidgetRegistry`.
5. **Inbox flow** (only if the capability has an approval moment). Wire trigger
   to assignee to approve/reject to audit event to follow-up.
6. **Connector** (only if the capability touches an external system). Build or
   wire the connector boundary: setup, sync, health, data request, retry/reauth.
7. **Observability.** Make this capability's operational state inspectable:
   connector health, last sync, projection status, policy denials, runs, audit.
   If an operator cannot answer "why did this appear, disappear, or fail?", it
   is not done.
8. **Tests.** Use the host's test framework as detected in phase [0]
   (Pest or PHPUnit). When Pest is present, use **`tall-feature`** (TDD
   red-green-refactor) to drive the implementation and **`tall-test`** to round
   out coverage. Cover the event-write path, the projection rebuild, policy
   enforcement, tenant isolation, and (if present) connector failure modes and
   the approval workflow.

## TALL hand-off

When TALL helper skills are installed and the runtime supports invoking other
skills, lean on the existing skills rather than reinventing conventions:

- **`tall-model`.** Scaffold the model, migration, factory, optional states and
  policy.
- **`tall-feature`.** Build the capability via strict TDD (red, green, refactor)
  with code review.
- **`tall-conventions`.** The Livewire 4 SFC, Flux UI, action-class, and state
  machine rules to follow.
- **`tall-test`.** Write Pest tests for existing code.

This skill stays the orchestrator: it decides *what* primitive to build and *how
it must behave in MortelOS* (governed, observable, tenant-safe). The TALL skills
decide *how the Laravel/Livewire code is written*.

## Headless fallback

When TALL helper skills are not installed, or when you are running in a context
where invoking other skills is not possible (headless runs, automation,
evaluation harnesses), scaffold manually following the conventions below. The
goal is parity with what `tall-feature` would produce.

### Layout

- Portal code under `app/Portals/<PortalName>/`, namespaced
  `App\Portals\<PortalName>\…`. Subdirs: `Models/`, `Events/`, `Actions/`,
  `Listeners/`, `Projectors/`, `Policies/`, `Http/`, `Inbox/`, `Connectors/<System>/`,
  `Support/`, `Providers/`.
- Migrations under `database/migrations/portals/<portal_slug>/`. Register the path
  in your `<Portal>ServiceProvider::boot()` via
  `$this->loadMigrationsFrom(__DIR__.'/../../database/migrations/portals/<portal_slug>')`,
  or add the path to the host's `migrateFreshUsing` so tests pick it up.
- Views under `resources/views/livewire/portals/<portal_slug>/` for SFC pages, or
  follow the host's `tall-conventions` page layout if it differs (some hosts use
  `resources/views/pages/<portal_slug>/`).
- Routes registered from the portal service provider, never directly in the host
  `routes/web.php`, so the boundary stays explicit.

### Models, migrations, factories

- Eloquent model per entity. Use Laravel 13 `casts()` method (not the array
  property). Include `$fillable` or `$guarded`, relationships, and a static
  `factory()` returning the matching factory class.
- One migration per entity, additive only. Use portable column types so the
  schema works under both sqlite (tests) and the production driver. Avoid
  pgvector or vendor-specific types in portal tables; if you need them, isolate
  them in a connector-owned migration that the production driver handles.
- Factory under `database/factories/Portals/<PortalName>/` matching the host's
  factory namespace convention.

### State machines

If an entity has a lifecycle (for example `pending_review → approved | rejected`),
use `spatie/laravel-model-states` with a State base class plus concrete States
plus a `StateConfig::default(...)->allowTransition(...)`. Never store the state
as a free-text string column.

### Actions

Business logic in `Actions/` classes, one per use case. Invokable
(`__invoke($input)`) returning the affected entity. Emit domain events from the
action. No business logic in Livewire components, controllers, or Blade.

### Events plus listeners

- `Events/` PHP classes, plain (no Laravel base class needed, but using
  `Illuminate\Foundation\Events\Dispatchable` is fine). Public readonly properties
  for payload.
- `Listeners/` register in the portal service provider via `Event::listen(...)` or
  via `$listen` on an `EventServiceProvider`.

### Projections

- `Projectors/<Name>Projector.php` with `apply(<Event> $event)` per event type.
  Expose `rebuildForTenant(string $tenantId)` and `rebuildAll()` for verification
  and drift recovery.
- Sync vs async: register the projector listener with or without `ShouldQueue`
  based on the consistency need recorded in the build plan.

### Policies

- One policy per primary entity in `Policies/`, plus a service provider
  registration `Gate::policy(<Model>::class, <Policy>::class)`.
- Each ability also seeded with `Gate::define($ability, fn () => false)` in the
  portal provider, so the deny-by-default scaffold is provable when no policy is
  resolved.
- Tenant scoping in every policy method; do not trust controller-level tenant
  filtering alone.

### Surfaces

- Livewire 4 SFC pages following the host's existing `tall-conventions` layout.
- For each route, define it in the portal provider's `boot()` via a `Route::group`
  with the `auth` middleware (and tenancy middleware when the host enforces it).

### Inbox flow

- Define an item type string for the portal (for example
  `<portal_slug>_inbox_item.<event>`). Register it through the host's
  `inbox.item_type_resolver` so the host renders the right detail component.
- The detail component lives in the portal's Livewire SFC tree and calls
  Approve/Reject actions.

### Connectors (when present)

- Define a `Connectors/<System>/<System>Client` PHP contract (interface) for the
  HTTP boundary. Implement `Http<System>Client` for production (only if you
  actually call the API in this slice) and `Fake<System>Client` for tests, both
  bound in the portal provider behind the contract.
- Wrap the connector in a `<System>Connector` class with `setup`, `triggerSync`,
  `health` methods. Handle 5xx with exponential backoff and 401/403 by marking
  `needs_reauth` plus emitting a `Sync<System>Failed` event that materializes an
  inbox item for admin.

### Tests

- Match the host's framework as detected in phase [0]. If `vendor/bin/pest`
  exists, write Pest. If only `vendor/bin/phpunit` exists, write PHPUnit-class
  tests; the build plan §11 follows the same.
- Place portal tests under `tests/Feature/Portals/<PortalName>/`. Add the portal
  migration path to `tests/TestCase.php`'s `migrateFreshUsing` so test DBs include
  it.
- Cover at minimum: deny-by-default policy enforcement, the happy-path action
  flow (upload to inbox to approve, or sync to projection update), projection
  rebuild idempotency, tenant isolation, and (when present) connector
  failure/reauth.

### Symlinked vendor caveat

If the host has `mortelos/starter` (or this portal) loaded via a symlinked
`vendor/` (eval worktrees, monorepos, local path repositories), Laravel's
`Application::inferBasePath()` resolves the symlink target instead of the actual
working directory. Tests then bootstrap the wrong app. Fix by adding to
`phpunit.xml` (or `phpunit.dist.xml`):

```xml
<php>
    <env name="APP_BASE_PATH" value="/absolute/path/to/host"/>
</php>
```

And, when needed, prepend a worktree-local PSR-4 autoloader entry in
`bootstrap/app.php` so the portal namespace resolves locally. This is only
relevant in symlinked layouts; ignore it in normal composer installs.

## Checkpoint protocol (director/reviewer)

After each slice, **stop** and hand control back to the user:

1. Summarize what was built, in a short list (not a walkthrough).
2. State the verification you ran and its result: tests green/red, policy denies
   by default, app still boots, observability present. Evidence before claims.
3. Update `progress.md`: tick the capability, append to the log, note any
   deviation from `build-plan.md`.
4. Ask whether to proceed to the next capability, adjust the plan, or stop.

Never chain into the next slice without the user's go-ahead. The goal in
`progress.md` is the stop condition for the whole loop: when every capability the
goal requires is built, tested, and reviewed, the portal is done. Report that
and stop.

## Deviations

If reality diverges from the plan (a capability needs an entity the map missed,
a connector behaves differently than assumed), do not silently improvise. Note
it at the checkpoint, update `build-plan.md` and `capability-map.md`, and confirm
before building on the change. The plan stays the source of truth.
