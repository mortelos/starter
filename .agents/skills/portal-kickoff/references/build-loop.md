# Build Loop

Phase [6]. Build **every** capability end to end, in the planned dependency order,
in a single pass. No per-capability review stops, the user already approved the
whole plan at the gate (phase [5]). Keep `progress.md` ticking as you go so an
interrupted run can resume, then verify and hand off once at the end (phase [7]).
This skill owns the MortelOS-specific shape; the **TALL skills own the
Laravel/Livewire implementation** when available. When they are not callable, use
the [headless fallback](#headless-fallback) below.

## Build each capability fully (in order)

For each capability, build in this order so each layer rests on a tested one
below it, then move to the next capability without stopping for review:

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
model the states and their allowed transitions explicitly, do not let any string
flow into the column. When `spatie/laravel-model-states` is already in the host,
use it: a State base class plus concrete States plus a
`StateConfig::default(...)->allowTransition(...)`. When it is **not** installed,
do not add a dependency just for this, use a backed PHP enum cast on the column
plus a guarded transition method on the model (or in the Action) that rejects
illegal moves and emits the transition event. Either way the rule is the same:
the set of states and the legal transitions are enforced in code, never an
unconstrained string.

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
- Tenant scoping in every policy method; do not trust controller-level tenant
  filtering alone.
- **Deny-by-default scaffold, two kinds of abilities, do not conflate them.**
  Seed `Gate::define($ability, fn () => false)` ONLY for **standalone gates** that
  have no model policy, navigation visibility, widget access, connector setup/sync,
  agent-tool access. For an ability a **model policy** answers, make the **policy
  itself** deny-by-default instead (every method returns false unless a rule grants
  access); do not also scaffold it with a false closure. Provability then comes
  from a test that asserts the policy denies with no grant.
- **Why, and the real trap: dotted ability names.** Laravel resolves a matching
  **policy method before** any `Gate::define`d ability, so a false closure on a
  *bare* name like `view` does not shadow `InvoicePolicy::view()`, the policy still
  wins (the false define is just dead code there). The bite comes with **dotted**
  ability names. The capability map usually writes `invoice.view` / `invoice.download`,
  but the policy has methods `view` / `download`, so `Gate::allows('invoice.view',
  $invoice)` finds **no** matching policy method, falls through, and lands on the
  `Gate::define('invoice.view', fn () => false)` you seeded, which denies and your
  policy never runs. That is the surprise 403 on policy-backed surfaces. Fix it one
  of two ways, and never seed a dotted policy ability false:
  - check the **bare** ability against the model (`Gate::allows('view', $invoice)` /
    `$user->can('download', $invoice)`) and keep the dotted name only as documentation, or
  - register an explicit alias so the dotted name reaches the policy method:
    `Gate::define('invoice.view', [InvoicePolicy::class, 'view'])`.

### Surfaces

A surface is only built when it actually **renders over its route**. Registering a
Livewire component class is not enough, the most common failure here is a page
that 500s with `No hint path defined for [...]` because the component's Blade view
was never made discoverable. Wire all three pieces:

- **Component + its view.** Livewire 4 SFC pages following the host's existing
  `tall-conventions` layout. If the portal keeps its views in its own tree
  (`resources/views/livewire/portals/<portal_slug>/` or the host's page path),
  **register that path as a view namespace in the portal provider's `boot()`**
  with `$this->loadViewsFrom(__DIR__.'/../resources/views', '<portal_slug>')` (or
  the host's equivalent) so `view('<portal_slug>::...')` resolves. Mirror an
  existing host page to copy the exact SFC shape, do not invent one.
- **Data the host's way.** Follow the host's SFC data pattern rather than assuming
  a classic `render()` method, modern Livewire 4 SFC hosts expose data through
  `#[Computed]` properties and an inline view, and a stray `render()` can fight the
  host's page mechanism. Check `tall-conventions` (or an existing page) for the
  pattern this host uses before writing the component.
- **Route.** Define each route in the portal provider's `boot()` via a
  `Route::group` with the `auth` middleware (and tenancy middleware when the host
  enforces it). Register navigation/search resolver entries if the capability adds
  them.
- **Prove it renders.** Add a feature test that hits the route and asserts a real
  render (`get('/<route>')->assertOk()` for an allowed user, `assertForbidden()`
  for a denied one). See the Tests section, this is mandatory coverage, not
  optional, precisely because a green policy/action suite can otherwise hide a
  500-ing page.

### Inbox flow

- Define an item type string for the portal (for example
  `<portal_slug>_inbox_item.<event>`). Register it through the host's
  `inbox.item_type_resolver` so the host renders the right detail component.
- The detail component lives in the portal's Livewire SFC tree and calls
  Approve/Reject actions.

### Connectors (when present)

- Define a `Connectors/<System>/<System>Client` PHP contract (interface) for the
  HTTP boundary. Implement `Http<System>Client` for production (only if you
  actually call the API for this capability) and `Fake<System>Client` for tests, both
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
  rebuild idempotency, tenant isolation, **every surface renders over its route**
  (`assertOk` for an allowed user, `assertForbidden` for a denied one), and (when
  present) connector failure/reauth. The surface-render assertion is non-negotiable:
  without it a fully green policy/action suite can still ship pages that 500.

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

## During the build pass (no per-capability stop)

The user approved the whole plan at the gate (phase [5]), so the build runs
straight through. Between capabilities you do **not** hand control back; you keep
the build honest instead:

1. **Tick `progress.md`** as each capability goes green (entity · projection ·
   policy · surface · tests). This is the resume anchor, not a review request.
2. **Keep tests green as you go.** Run the capability's tests before moving on; a
   red suite is a build problem to fix now, not something to defer to the user.
3. **Log deviations, keep building.** See [Deviations](#deviations) below.

The portal goal in `progress.md` is the stop condition: when every capability the
goal requires is built and green, the build pass is done, move to handoff
(phase [7]).

## Handoff (phase [7])

Once the whole portal is built, verify and hand off, once:

1. Summarize what was built, in a short list (not a walkthrough).
2. State the verification you ran and its result: full suite green, policy denies
   by default, app still boots `login → tenant-select → dashboard`, observability
   present. Evidence before claims.
3. Write `docs/portals/<slug>/handoff.md` from `references/handoff-template.md`:
   built · deferred/fast-follows · how to add the next capability · where things
   live. Tick the final items in `progress.md` and append a closing log entry.
4. Hand the portal to the partner: state what they should pick up first. Stop.

## Deviations

If reality diverges from the plan (a capability needs an entity the map missed,
a connector behaves differently than assumed), do not silently improvise and do
not stop the build to ask. Make the smallest sound choice that keeps the plan's
intent, update `build-plan.md` and `capability-map.md` to match what you built,
and append a one-line note to the deviations log in `progress.md`. Surface every
deviation together at the handoff so the partner inherits an accurate picture.
Only stop mid-build if a deviation changes the portal's scope or contradicts a
confirmed assumption, that is a plan decision the user owns, not a build detail.
