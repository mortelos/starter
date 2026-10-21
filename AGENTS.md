# Agent Instructions

This repository is `mortelos/starter`, a runnable Laravel host application template.

The public MortelOS documentation is the source of truth. Do not maintain local copies of the method, package reference or troubleshooting docs in this repository.

Read these pages before portal work:

| Topic | URL |
| --- | --- |
| Installation | https://mortelos.nl/docs/0/installation |
| Agentic development | https://mortelos.nl/docs/0/agentic-development |
| Building portals | https://mortelos.nl/docs/0/building-portals |
| Host app anatomy | https://mortelos.nl/docs/0/host-app-anatomy |
| Outlets | https://mortelos.nl/docs/0/outlets |
| TALL conventions | https://mortelos.nl/docs/0/tall-conventions |
| Package governance | https://mortelos.nl/docs/0/package-governance |
| MCP runtime | https://mortelos.nl/docs/0/mcp-runtime |
| Troubleshooting | https://mortelos.nl/docs/0/troubleshooting |

## Local Rules

1. Use the `mortelos-tooling-setup` skill before portal work when the developer machine cannot yet create, boot or verify a MortelOS host app. Trigger it for missing Herd, PHP, Composer, Node, GitHub access, MortelOS CLI, DBngin, TablePlus, or `mortelos new` setup.
2. Use the `setup-portal` skill for new MortelOS portal requests when available.
3. Keep portal work host-side unless the task is explicitly a package PR.
4. Do not patch `vendor/mortelos/*` or sibling `mortelos/*` package worktrees from a starter task. To change what a package does, plug into its outlets (`php artisan mortelos:outlets:list <package>`) or open a package pull request that adds the missing one; `tests/Feature/OutletsGuardTest.php` fails on the workarounds.
5. Every write is an Action in `app/Actions/<Domain>/` (final, `handle()`, `ABILITY`); a host fact that must be undoable is an aggregate in `app/Aggregates/`, a stored event in `app/Events/<Domain>/` and a projector in `app/Projectors/`. Never `event(new …)` outside an aggregate, never `app/Livewire/`, never `resources/views/livewire/`: pages are `resources/views/pages/<domain>/⚡<name>.blade.php` (`pages::`), shared components `resources/views/components/<group>/⚡<name>.blade.php`. `tests/Feature/ArchGuardTest.php` fails on it; its allow list may only go down.
6. Record package decisions before adding new surfaces.
7. Verify host behavior with `php artisan starter:doctor`, `composer test:arch`, `composer test`, `composer test:browser` (after `npm run build` and, once per machine, `npx playwright install chromium`) and `vendor/bin/pint --dirty` when applicable.
8. Do not use em-dashes in Dutch user-facing prose.
9. Build UI only from `<x-mortel::…>` components; the catalogue is `vendor/mortelos/ui/COMPONENTS.md`. `tests/Feature/UiGuardTest.php` fails on `<flux:` and on raw `<select>`, `<table>`, `<button>`, `<input>` or `<textarea>`. The same test prints hints without failing — a primary button outside the action bar, a disabled primary — and those are questions to answer, not output to scroll past.
10. `laravel/vet` guards `composer install`, `require` and `update`: a package version that `vet.json` does not trust fails the command. Only a new dependency is a reason to stop and ask; clear everything else yourself.
    - **New dependency: ask first.** Before you `composer require` a package that `composer.json` does not list yet, tell the user which package, what it is for and why the installed packages do not cover it, and wait for a yes. That yes covers the packages it pulls in. Installing or updating a package that is already listed, including `mortelos/*` and `laravel/framework`, needs no question.
    - **Clear it with `bin/vet-agent`.** It runs `./vendor/bin/vet` with "Automatically, with my coding agent reading the changes first" and trusts the packages that review passes. Vet has no non-interactive option for that review; `bin/vet-agent` answers its prompts in a pseudo-terminal.
    - **Non-zero exit: review the flagged packages yourself.** Vet's review often flags a package that is fine, so do not hand this to the user. `WARN` means the review could not finish (files over the budget, not text, no answer); if the reviewing agent itself failed (rate limit, timeout), run `bin/vet-agent` once more first. `FAIL` means the review saw a possible risk, which is often ordinary code the package needs, such as an HTTP client that calls a URL or a CLI tool that starts a process. For each flagged package, read what vet reported and the files it named, and compare the old and new version on the package's own repository (`gh api repos/<owner>/<repo>/compare/<old>...<new>` and its release notes). Trust it when the change fits what the package is for and matches its repository at that tag. Stop and tell the user in plain words only on a real red flag: obfuscated or encoded code, code that reads `.env`, keys or credentials and sends them somewhere, a download or shell command at install time, a host that has nothing to do with the package, code that is not in its repository at that tag, or a finding you cannot explain. When every flagged package passes, run `bin/vet-agent --trust-all` and list each one in the pull request with what vet flagged and why it is safe. `--trust-all` trusts every package left in the list, so if you stop on one, do not run it for the others.
    - Then run `composer install` again and commit `vet.json` with `composer.lock`. Never edit `vet.json` or `vendor/composer/installed.json` by hand, and never run `vet --init` or `vet --fresh` to get past a failure.

For current contracts, routes, install steps and troubleshooting, use the documentation site instead of this repository.

<!-- mortelos-agent-rules:start -->
Generated by `php artisan mortelos:agent-rules:publish`. Manual edits inside this block are overwritten.

<!-- mortelos-agent-rules:package=mortelos/agent-standards hash=f1c43db8bd6ff81d6f4e9d7200553cb2be81b545 -->
# mortelos/agent-standards
## MortelOS Agent Standards

These rules are MortelOS-owned. Treat upstream Laravel, Spatie and community conventions as useful references, but prefer the MortelOS docs, package boundaries and local code patterns when they differ.

### Working Method

- Read the active package `CLAUDE.md` before editing inside a MortelOS package.
- Keep host app work host-side unless the request explicitly targets a reusable package.
- Record package decisions before adding new reusable surfaces.
- Use direct tools for executable actions. Do not report a tool, OS, app, package or browser action as complete until the direct command or tool has succeeded.
- Keep changes scoped to the requested behavior and the package boundary that owns it.
- Verify with the narrowest meaningful commands first, then broaden when behavior crosses package boundaries.

### Outlets First

Rules for changing a `mortelos/*` package for a host need. Cite a rule by its name in reviews and pull requests; each rule ends with how it is checked.

- **outlet-first.** Before changing a `mortelos/*` package for a host need, run `php artisan mortelos:outlets:list <package>` and climb the ladder: config, event, registry, contract, view outlet. Only when none fits, open a package pull request that adds the missing outlet (additive, patch release) and plug in from the host afterwards; the behaviour itself stays in the host. Change the package's own behaviour only with a package decision (`mortelos:package-decision`) and only when a second host needs it, it is a bug or security fix, or it concerns the package's own default screens or defaults. Check: `OutletsGuard` (`unknown-outlet`, `concrete-subclass`, `config-set`, `vendor-require`, `vendor-patch`, `vendor-view-copy`).
- **outlet-no-subclass.** Extend only interfaces and abstract classes from a package. A concrete package class is `final`; a host that needs different behaviour binds or decorates the contract outlet. Check: `OutletsGuard` (`concrete-subclass`).
- **outlet-ship.** A package change that adds a public surface (an interface, a registry, an event, a config file, an `<x-mortel::outlet>`) lists it in `resources/mortelos/outlets.json` in the same change, with a one-sentence description and, for a view outlet, its data and default. The manifest entry is the package decision for that surface. Removing or renaming an outlet is breaking. Check: `OutletsManifest::assertValid` in the package tests.
- **outlet-config.** Package settings live in one config file registered with `mergeConfigFrom` and published under a tag; new config files publish under `<package>-config`, existing tags stay. A host overrides by publishing that file or through `.env`, never with `config()->set` or by requiring the vendor file. Check: `OutletsGuard` (`config-set`, `vendor-require`).
- **outlet-view.** A package screen marks each place a host may add to or replace with `<x-mortel::outlet name="<package>.<screen>.<place>" :data="…">` with the default inside; a host plugs in with `Outlet::view`, `Outlet::livewire` or `Outlet::render` in a provider, `replace: true` to drop the default. Data that a Livewire component does not declare as a property is dropped silently, so declare every key you pass. Hosts never copy or publish package views. Check: `OutletsGuard` (`vendor-view-copy`, `unknown-outlet`); `UiGuard` hint for an outlet name without the package prefix.
- **outlet-package-pr.** When a package needs a new outlet, the pull request contains only the outlet, its manifest entry and a test; it does not carry the host feature. Check: review.

### Laravel And PHP

- Use strict types in PHP files.
- Prefer typed constructor promotion, readonly value objects and `final` classes; a class is non-final only when it is an abstract class or interface listed as a contract outlet in `outlets.json`.
- Every write path is an Action in `app/Actions/<Domain>/`: a `final` class with one `handle()` and a `public const ABILITY` it checks before it mutates anything. It takes the actor as a `Mortel\Support\Actor` parameter and returns a `Mortel\Actions\ActionResult`. Call framework Actions through `handle()` only: `execute()` is the old name that still exists on 23 framework Actions, and new code never calls it. A Livewire method, controller, job or console command validates input, calls the Action and shows its result; it never writes a model, fires an event or calls an external API itself. Check: `ArchGuard` (`write-outside-action`, `action-shape`).
- `app/Support` holds pure classes only: value objects, readers, formatters and resolvers. Nothing there calls `save`, `create`, `update`, `delete`, `DB::statement` or `event(`. Behaviour that writes is an Action; behaviour that reacts to a stored event is a projector or reactor. Check: `ArchGuard` (`write-outside-action`, `event-outside-aggregate`).
- Keep Eloquent models strict: define relationships, casts and fillable or guarded behavior intentionally.
- Prefer immutable dates for domain data and time-window calculations.
- Fail loudly for impossible states with small exceptions or validation results instead of silent fallbacks.
- Avoid global helpers for domain logic unless the helper is already an established local convention.

### Host State And Events

- A host fact that must be undoable or auditable is an aggregate in `app/Aggregates/`, a stored event in `app/Events/<Domain>/` and a projector in `app/Projectors/` that writes the read table. The read table is a projection: a replay (`php artisan event-sourcing:replay "App\Projectors\…"`, or `Spatie\EventSourcing\Projectionist::replay()` in a test) must reproduce it from the events alone, so nothing but the projector writes it. Check: a Feature test that rebuilds the projection and compares the rows.
- Record a stored event only through `recordThat()` in an aggregate. Never `event(new …)` a `ShouldBeStored` event from an Action, a Livewire component, a job or a controller. Never fire a framework event by hand (`ChannelUpdated`, `EntityUpdated`, `RoleCreated`, …): call the framework Action that owns it (`Mortel\Actions\…`), which goes through its aggregate. Check: `ArchGuard` (`event-outside-aggregate`) and the `GovernedModelWriteRule` in PHPStan (`vendor/mortelos/dev-tools/extension.neon` with a published `config/spine.php`).
- Every stored event implements `Mortel\Contracts\ProvidesStoredEventMetadata` (`storedEventType()` as `<domain>.<thing>.<past tense>`, `storedAggregateType()`) and is registered under that type in `event-sourcing.event_class_map`, so a renamed class does not break a replay. Check: `ArchGuard` (`event-metadata`).
- Every mutating Action returns a `Mortel\Actions\ActionResult` with status `done`, `approval_needed`, `failed` or `skipped` (constructors `ActionResult::done()`, `approvalNeeded()`, `failed()`, `skipped()`). When the change can be reversed, the result carries a `Mortel\Actions\Undo`: `Undo::of(CounterAction::class, [...params], validUntil)`, where `action` is the FQCN of the counter-Action. When it cannot be reversed, the docblock says why (sending, paying, publishing). Register the counter-Action in a service provider with `app(UndoRegistry::class)->register(CounterAction::class, fn (array $params, Actor $actor): ActionResult => …)` (`Mortel\Actions\UndoRegistry`); the screen renders the message and the undo button from the result, never from its own state, and rolls back with `app(UndoRegistry::class)->undo($result->undo, Actor::current())`. The undo recipe lives in the result, not in the event metadata: the counter-Action records its own stored event, and that event is the audit. Check: a Feature test asserts the result status and, for a reversible change, runs the undo through `UndoRegistry`.
- Every actor is a `Mortel\Support\Actor`, with four forms: `Actor::user($user)` (the actor text is the bare user id, no prefix), `Actor::agent($runId, $onBehalfOf)` (`agent:<run-id>`), `Actor::system('scheduler')` (`system:<process>`) and `Actor::bridge('telegram')` (`bridge:<name>`). `Actor::current()` is the logged-in user. Fill `Mortel\Support\EventContext` (actor, reason, org, branch) around every Action that records an event with `app(Mortel\Support\EventContext::class)->within($actor, $reason, fn () => …)`, which restores the previous context afterwards so a later step in the same request does not inherit it. Never call `fill()` and restore by hand. An empty actor is a bug, not "the system did it". Check: a Feature test asserts the actor on the stored event.
- A host ability (the `ABILITY` constant of a host Action, for example `invitations.manage`) gets its policy in scope `Mortel\Enums\PolicyScope::Action`, where `Mortel\Actions\Policies\CheckPolicy` evaluates it. Never use `PolicyScope::AgentTool` for a host ability. Check: a Feature test that checks the ability with and without the policy.

Worked example in the starter (v0.11.0+): `app/Actions/Invitations/InviteUser.php`, `app/Aggregates/InvitationAggregate.php`, `app/Events/Invitations/InvitationCreated.php`, `app/Projectors/InvitationProjector.php` and the users page that calls them.

### TALL And UI

- UI is built exclusively from `<x-mortel::…>` components (package `mortelos/ui`). Never write `<flux:…>` directly and never write raw `<select>`, `<table>`, `<button>`, `<input>` or `<textarea>`. Icons are `<x-mortel::icon name="…" />`.
- Before building any UI, read `vendor/mortelos/ui/COMPONENTS.md`. If a component you need is missing, compose it from existing `<x-mortel::…>` components (in a package, as its own component with the package prefix, for example `x-overviews::…`). A component moves to `mortelos/ui` once a second package needs it; propose it then. The same second-user rule decides rung 7 of the outlet ladder.
- The action that moves a screen forward is its only `variant="primary"` and lives in `<x-mortel::page-actions>`, or in the `actions` slot of `<x-mortel::page-header>` on a list page. A form may have its own `type="submit"` primary. Optional actions (send a message, add a document, export) are never primary.
- Row actions in a list or table are never `variant="primary"`: use the default, `filled` or `ghost` variant, so the screen keeps its one primary action.
- A blocked primary action explains, at the button, why it is blocked and what unlocks it: use `<x-mortel::page-actions blocked="…">` and put the unlocking action in its `unlock` slot. Never render a disabled primary button without a reason.
- Status copy and the primary action must agree: do not write "ready" or "done" while the next step is still blocked.
- Pick the purpose-built component before composing wrappers: a choice with more than about seven options or free-text search is `<x-mortel::select searchable>` (never a plain select); multi-choice is `multiple`; an empty list is `x-mortel::empty`; a page title with buttons is `x-mortel::page-header`; key figures are `x-mortel::stat.grid`; a filter row is `x-mortel::filters`; tabs are `x-mortel::tabs`. Section B of `COMPONENTS.md` lists them.
- The `UiGuard` test fails the build on violations; do not add `{{-- mortel-guard: ignore --}}` without saying so in the PR description. It also prints hints without failing (a primary button outside the action bar, a disabled primary): answer the hint or say why it does not apply, never leave it unread.
- Alpine bindings on an `<x-mortel::…>` tag use the long form (`x-bind:class`, `x-bind:id`, `x-bind:aria-selected`); the shorthand `:attr` is a Blade dynamic prop there and breaks the page. Never reference Flux views as strings either (`flux::…` in `@include` or `x-dynamic-component`).
- Keep Blade components presentational; move authorization, orchestration and state transitions into PHP classes.
- Build dense operational screens for portal workflows. Avoid marketing-page composition for admin or back-office tools.
- Preserve accessibility basics: labels, keyboard paths, visible focus states and meaningful empty states.
- Do not add visible instructional text about implementation details, styling systems or agent workflow.

### Livewire 4

Rules for Livewire 4.4 views. Cite a rule by its name in reviews and pull requests; each rule ends with how it is checked.

- **island-when.** Wrap a part that has its own actions or poll in `@island(name: '…')` when nothing outside it has to change after those actions. Check: one interaction there sends 1 POST whose response holds only that island (`islandFragments`, no `effects.html`).
- **island-stale.** Never render outside an island, as `{{ $property }}`, a property that an action inside the island writes: bind it with `wire:model` or `$wire`, or move that markup into the island. An `<x-mortel::…>` component that shows such state (`page-actions` with `blocked`, `stat`, `page-header`) sits inside the island. Check: grep every property the island's actions write.
- **island-always.** Give an island `always: true` when anything that renders the whole component changes what the island shows: an action outside the island, an `#[On]` listener, or `Livewire::test()->call()` in a test (without it the island comes back empty with `mode=skip` and `assertSee` fails). Check: a Feature test calls the action (or dispatches the event) and asserts the island's text.
- **island-place.** Never put `@island` inside `@foreach` or `@if`, and never in the template of an `x-mortel::…` or other anonymous Blade component; islands live in the Livewire view (inside the slot of an `<x-mortel::…>` tag there, such as `card` or `tabs.panel`, is fine). Inside an island use only `$this`, public properties and variables defined in the island. Check: the `UiGuard` hint for an island in an anonymous component; no hint covers `@foreach`, `@if` or outside variables, so read each `@island` for them.
- **island-trigger.** Trigger an island from outside with `wire:island="name"` on the element; from Alpine code that is not a button click (a key handler, a callback) call `$wire.$island('name').method()`. A bare `$wire.method()` from Alpine renders the whole component and skips the island, so the island does not update. Check: the `UiGuard` hint for unknown island names, and an island-only POST in the browser test.
- **island-concurrency.** An island action runs in its own request queue and does not wait for actions outside the island (measured on Livewire 4.4.6: an island action answered after 1201 ms while a 2530 ms outer action still ran), so two requests of one component can write the same row at once and the last write wins. When an action stores a value computed from data it just read, read and write in `DB::transaction()` with `lockForUpdate()`, or use one atomic query: `increment()`, or an `update()` whose `where` holds the expected old value. Wrong: `$doc->version++; $doc->save();`. Right: `Document::whereKey($id)->increment('version');`. Check: grep the actions of each component with an `@island` for a stored value computed from a read outside a locking transaction.
- **click-server.** A button that runs PHP uses `wire:click` (Flux shows the loading state and the island follows from the button's position), not `x-on:click="$wire…"`. A click that only changes the browser never calls PHP: use Alpine state, `$dispatch` or a `$js` action. Check: the browser test counts 0 POSTs for browser-only actions.
- **lazy-placeholder.** A slow part (a query or API call in `mount()` or render) is `defer` when it is in view on load and `lazy` below the fold, and always has a placeholder: `<x-mortel::placeholder height="…">` at the height of the content; it renders a `div`, so the component root is a `div` too (Livewire needs the same root tag). No select, tabs or form in a placeholder. Content tests of a lazy component use `Livewire::withoutLazyLoading()`; a lazy or deferred island always renders its placeholder first, so assert its content in the browser test. Check: layout shift below 0.01 in the browser test.
- **loop-key.** The outermost element of each iteration of a `@foreach` in a Livewire view has a `wire:key` (on a plain element, not on an `x-mortel::select` with a `label`, whose key lands on an inner element) built from a stable record id, never `$loop->index`, and `wire:model` inside a loop binds by that id (`rows.{{ $row->id }}.field`), not by position. Check: grep the loops.
- **poll.** `wire:poll` has an integer and a unit (`.10s`, `.750ms`), the longest interval that works, `.visible` when it can scroll out of view, and sits inside an island. When the polled data is shown outside any possible island, poll a `#[Renderless]` check action that calls `$this->js('$wire.$refresh()')` only when something changed. No untargeted `wire:loading` in a polling component. Check: the `UiGuard` poll hints, and the POSTs with `poll: true` per interval in `LivewireMonitor::summary()` in the browser test.
- **renderless-async.** Put `#[Renderless]` and `#[Async]` only on actions that change nothing shown in Blade. Use `#[Computed(cache: true)]` only for data that is the same for every user and tenant: its cache key is the component name and method, or a constant `key:`, shared by everyone. For per-user data use `persist: true` or `Cache::remember()` with the user or tenant id in the key. Check: a `#[Renderless]` action's response has no `effects.html`; an `#[Async]` action writes no property shown in Blade (grep); grep `cache: true` and confirm the data is global.
- **snapshot.** No public property for data that only `mount()` needs (keep it a `mount()` parameter) or for large derived HTML (make it `#[Computed]`): public properties travel both ways in every request, polls included. Check: a Feature test asserts the size of `json_encode($component->snapshot)`.
- **loading-state.** `<x-mortel::button>` already shows a loading state for `wire:click` and `type="submit"`. Elsewhere prefer `data-loading:` classes that do not move layout (`opacity`, `pointer-events`); a `wire:loading` for an island action sits inside that island or has `wire:target`. In a long list of identical row buttons, `:loading="false"` (a Blade prop, not an Alpine binding) leaves the spinner markup out. Check: layout shift around the click stays below 0.01.
- **root.** A Livewire view renders exactly one root element with nothing before it, not even `<style>` or `@once`; component CSS goes in `@assets`. Check: a Feature test finds `wire:id` on the intended root element.
- **navigate.** Init code that must run again after `wire:navigate` listens to `livewire:navigated`; JS that mounts a third-party widget on Livewire DOM destroys it on `livewire:navigating`. `$this->stream()` always names its target (`to:` or `->to()`) and escapes its content with `e()`. Check: after navigating away and back the widget exists once and the console is clean; grep `stream(` for a target.
- **package-component.** Package components are single-file components in `resources/views/components/⚡<name>.blade.php` of the package (sub-folders allowed), registered with `Livewire::addNamespace(namespace: '<unique>', viewPath: '<package>/resources/views/components')`; the component name stays `<namespace>::<name>`. No `resources/views/livewire/` in a package. Check: grep `addNamespace(` in the workspace and in `vendor/` of the host apps; the namespace occurs once and `viewPath` points to the package's `resources/views/components`; grep `resources/views/livewire` in packages returns no matches.
- **layout.** A host page is a single-file component in `resources/views/pages/<domain>/⚡<name>.blade.php`, routed as `Route::livewire('/path', 'pages::<domain>.<name>')`; a shared component is `resources/views/components/<group>/⚡<name>.blade.php`; layouts stay in `resources/views/layouts/` (`layouts::app`). No `resources/views/livewire/`, no classes or traits under `app/Livewire/`, no `Livewire::addNamespace()` in a host for its own views. A page above about 300 lines splits into components, never into traits. Check: `ArchGuard` (`livewire-path`, `livewire-class`, `livewire-zap`).

### Verify UI Changes

- A UI change is done when its verify run is green: run `vendor/bin/pest --filter='UiGuard|ArchGuard|OutletsGuard'`, then the browser test of every screen you touched. Fix what fails and run again until green; never report a UI change as done before that. `UiGuard` also prints Livewire hints without failing: fix each hint, or name it and the reason in the pull request.
- Every screen you touch has one browser test that opens it with `Mortelos\Ui\Testing\LivewireMonitor::visit()` and declares each interaction with `LivewireMonitor::expect()`: the exact number of Livewire POSTs (0 for an action that only changes the browser) and the island when one is used (with `islandMode: 'append'` or `'prepend'` for an island that adds to its content instead of replacing it). `expect()` counts until the page has been quiet for `quietMs` (750 by default): on a page with a debounce, pass a `quietMs` larger than the longest debounce, or the debounced POST is not counted and a budget of 0 passes falsely. The helper also requires 0 console errors, warnings and JS errors, every Livewire POST of the interaction answered 200 (assert `status` of load and poll POSTs from `summary()`), layout shift below 0.01, no ARIA reference to a missing id, no duplicate id, and a screenshot. Add or update that test in the same change.
- `expect()` does not count requests that only run `wire:poll`. Check a poll with `LivewireMonitor::summary()`: during a measured window each poll sends at most one POST with `poll: true` per interval, plus one (tell polls apart by `calls`). Response size is reported, not budgeted; an island response larger than the full render means the island does not pay off.
- Put the helper's summary lines and the screenshot paths in the pull request description. If a budget cannot be met, name the budget and the reason; never loosen a budget silently.
- A repository without `pestphp/pest-plugin-browser` (no browser job) runs only `UiGuard`, skips the browser test and names in the pull request the checks that did not run.

### Security

- Validate and authorize at the boundary before mutating state.
- Treat tenant, organization and branch identifiers as scoped data. Never trust request input to select a tenant boundary without authorization.
- Treat user identity, tenant membership and tenant role assignment as separate authorization facts. When `tenant_user` exists, require an explicit row for the active tenant; never infer membership from `users`, `role_user` or a default `member` role.
- Never commit secrets, customer data, API tokens, private keys or local environment values.
- Fake external HTTP, mail, queue, notification and AI calls in tests unless the test explicitly verifies an integration boundary.
- Keep destructive Artisan and database operations guarded in production.

### Version Control And Review

- Keep commits and pull requests focused on one behavior change.
- Explain package-boundary decisions in the change when code moves between host and package.
- Prefer tests that describe user-visible behavior or package contracts over snapshots of incidental implementation.
- Do not rewrite unrelated user changes in a dirty worktree.

### External Sources And Personal Data

- A package that imports a user's own content from an external service (recorder, mailbox, notes, calendar) owns no tables. Compose framework primitives: the connection is an owned `channels` row, the item is an entity created through `EntityAggregate` events with `owner_user_id`, `visibility=private` and `classification=confidential`, and the text is a `TranscriptImported` event projected into `transcripts`.
- Content is never a hand-written table. Store it in an event and project it; delete it with `TranscriptPurged` plus `RedactStoredEvents`, so a projection rebuild cannot resurrect it.
- Keep provider specifics (OAuth or API client, driver, jobs, pages) in the package and nothing else. Ask before adding any migration to a channel package.
- Reference: `docs/personal-source-channel.md` in `mortelos/framework`.

### Dependency And Versioning

- Publish each MortelOS package as semantic-version tags; bump the minor for breaking changes while the package is below 1.0.
- Require MortelOS packages with caret ranges (for example `^0.5`) resolved from the MortelOS registry (`https://packages.mortelos.com`). Applications commit `composer.lock` so installs are reproducible on CI and in production. Packages keep `composer.lock` out of git so CI tests the newest allowed versions, and state every version floor they rely on in `composer.json`.
- Do not commit local path repositories (such as `../../mortelos/*`) in `composer.json`; they break dependency resolution wherever the sibling checkouts are absent. Develop sibling packages through a local, uncommitted override instead.
- Bump a consumer to a new package version only after that version has a matching release tag.

### Code Language

- All code is English: names of classes, methods, functions, variables, properties, constants, enum cases, namespaces, files, database tables and columns, array keys, config keys, environment variables, routes, events, Livewire and Blade components, test names, code comments, commit messages and pull requests.
- Use the established English term of the domain, not a word-for-word translation: a Dutch `concept` is a `draft`, the `inhoud` of a mail is its `body`.
- Dutch is allowed only in: text an end user sees (UI copy, mail, notifications, `lang/nl`); the words on the fixed list in `mortelos/dev-tools` (`resources/code-language/allowed-dutch.txt`), such as BSN, KvK, BTW and AVG; and string literals that recognise Dutch input, marked with `code-language: dutch-input <reason>`.
- A session or user language setting decides how you talk to the user. It never applies to code or code comments.
- `CodeLanguageGuard` from `mortelos/dev-tools` enforces this in every repo. Do not weaken it, do not add words to the allowed list in a consumer, and do not use the `dutch-input` marker for names or comments.
- Renaming a public name in a released package is a breaking change: bump the minor version (0.x) and add an upgrade note.

### Dutch Product Copy

- Write Dutch user-facing copy plainly and concretely.
- Avoid em-dashes in Dutch UI, docs and proposal prose.
- Avoid loose bullet fragments when a short complete sentence is clearer.
<!-- mortelos-agent-rules:endpackage -->

<!-- mortelos-agent-rules:package=mortelos/framework hash=3d096b371bbe889754ff78600dda76b24575d749 -->
# mortelos/framework
## Agent Runtime Completion Contract

- Do not report an executable OS, app, or package action as completed until the direct tool has succeeded or the runtime has paused for the required approval.
- Do not use `inbox.create` to simulate execution of a missing capability. Use the direct tool when it is listed.
- If the direct tool is unavailable, hidden by policy, or missing a folder grant, give an honest final answer that names the missing tool or permission.
- Treat approval-gated tools as the correct execution path. Request the tool call and let the runtime pause for approval instead of creating a proposal that claims the work is done.
- When `inbox.create` is genuinely appropriate, write clean Dutch proposal copy: a result-oriented title, one complete-sentence summary, and at most two concise body paragraphs. Do not start with "Gebaseerd op" and avoid loose hyphen bullet lists for simple proposals.
<!-- mortelos-agent-rules:endpackage -->

<!-- mortelos-agent-rules:end -->
