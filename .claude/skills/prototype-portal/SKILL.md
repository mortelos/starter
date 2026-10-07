---
name: prototype-portal
description: "Use when someone wants a clickable prototype, visual demo, or mockup of a MortelOS portal to show a client, BEFORE any backend is built. A throwaway front-end with mock data to validate screens and flow, not a working system. For the real gated build, including turning an approved prototype into a working portal, use setup-portal."
---

# Prototype Portal

Build a clickable, front-end-only prototype of a MortelOS portal on the
`mortelos-starter` host. The goal is to show a client the screens and the flow
and let them feel the direction, before any backend exists.

This is the first stage of the lifecycle:

```
prototype-portal  →  setup-portal
(mock front-end)     (full gated build behind the approved screens)
```

The public docs are the source of truth for TALL conventions and host anatomy.
Read `https://mortelos.nl/docs/0/tall-conventions` and
`https://mortelos.nl/docs/0/host-app-anatomy` before building.

## The one hard rule

**Front-end only. No working backend.**

A prototype demonstrates intent, it does not function. Buttons open screens, they
do not save anything. This is what keeps it cheap and honest, and it is what
separates this skill from `setup-portal`.

**Do NOT, in a prototype:**

- create models, migrations or factories
- create events, projections, aggregates or connectors
- create policies, governance gates or package decisions
- write or run tests (`pest`, `pint --dirty` not required)
- call real external APIs or AI providers (mock the output)
- run `setup-portal`'s capability map / package-decision / approval gates

If you are tempted to do any of the above, you are building the real portal. Stop
and use `setup-portal`. If a prototype already exists, its approved screens are
input for that build (see Handoff).

## Required gate: scope + screen approval

Before building, produce a short screen plan and get explicit approval. Capture:

1. **Source.** Which brief or plan defines the scope (e.g. a client
   `PROTOTYPE-V1-PLAN.md`). If none exists, ask for one. Do not invent scope.
2. **Screens.** The list of screens, each with a one-line purpose and the mock
   data it shows.
3. **Branding.** The client's brand colour (taken from their website or brand
   guide, set as `--mortel-accent` in `resources/css/app.css`; see "Huisstijl"
   in `vendor/mortelos/ui/COMPONENTS.md`) or an explicit "use neutral styling".
4. **Demo framing.** Confirm the PROTOTYPE badge and where the client will see
   it. Default: a password-protected demo, with the password on the web server
   because the prototype routes have no login. This starter ships no deploy
   skill, so agree where it goes online.

Present the screen plan, then stop until the user approves it. Accept only
unambiguous approval (`akkoord`, `bouw maar`). If a plan file already contains an
approved screen list, reference it and proceed.

## How to build it (host mechanics)

MortelOS uses Livewire 4 single-file components (SFC) and builds all UI from `<x-mortel::…>` components (`vendor/mortelos/ui/COMPONENTS.md`), never Flux tags directly: the starter's `tests/Feature/UiGuardTest.php` fails on them, prototype screens included.

1. **Routes.** Add prototype screens in their own group in `routes/web.php`, with
   NO `auth` middleware (the demo is gated at the server, not by login):

   ```php
   Route::prefix('prototype')->name('prototype.')->group(function () {
       Route::livewire('/dashboard', 'pages.prototype.dashboard')->name('dashboard');
       Route::livewire('/projects', 'pages.prototype.projects')->name('projects');
       // one route per screen
   });
   ```

2. **Screens.** One native Livewire 4 SFC per screen at
   `resources/views/livewire/pages/prototype/<name>.blade.php` (this host uses
   native L4 SFC, NOT Volt). Keep mock data hardcoded in the `new class`
   block, or in the shared file from step 4. No DB. Use the `#[Layout]`
   attribute, not an `<x-layout>` wrapper, and end the class statement with a
   semicolon.

   ```blade
   <?php
   use Livewire\Attributes\Layout;
   use Livewire\Attributes\Title;
   use Livewire\Component;

   new
   #[Layout('layouts.prototype')]
   #[Title('Dashboard')]
   class extends Component {
       // Mock data lives here, hardcoded. No models, no queries.
       public array $team = [
           ['name' => 'Rodney', 'pct' => 71],
           ['name' => 'Berry',  'pct' => 58],
       ];
   }; ?>

   <div>
       <x-mortel::heading size="xl" level="1">Dashboard</x-mortel::heading>
       {{-- x-mortel components, static interactions only --}}
   </div>
   ```

3. **Layout.** Create `resources/views/layouts/prototype.blade.php` (referenced as
   `#[Layout('layouts.prototype')]`), a clone of `layouts/app.blade.php` that does
   NOT depend on `Auth::user()` or the starter's config-driven nav components
   (hardcode the demo user and nav links) and renders a fixed PROTOTYPE badge on
   every screen. Keep `@fluxAppearance`, `@livewireStyles` and the `@vite` include
   from the original.

4. **Mock data.** Use real client context (names, projects, packages) so it lands
   with the client. Keep it in the components, or in one shared file
   `resources/views/livewire/pages/prototype/_mock.php` that returns an array.
   Never seed the database.

   ```php
   <?php

   return [
       'team' => [
           ['name' => 'Rodney', 'pct' => 71],
           ['name' => 'Berry',  'pct' => 58],
       ],
   ];
   ```

   Load it in a screen with `resource_path()`:

   ```php
   public array $team = [];

   public function mount(): void
   {
       $mock = require resource_path('views/livewire/pages/prototype/_mock.php');

       $this->team = $mock['team'];
   }
   ```

   Do not use `__DIR__`: Livewire compiles a single-file component to a class
   under `storage/framework/views`, so `__DIR__` points there and the include
   fails. Use `require`, not `require_once`: a second screen in the same request
   would get `true` instead of the array.

5. **Interactions.** Links navigate between prototype screens. Forms and action
   buttons may open modals or toggle local state, but persist nothing. Where the
   real system would call AI, show a pre-written example labelled as such.

## Delivery

Run the app locally to verify it renders (`composer dev`), click through every
screen, then deliver it as the password-protected demo agreed in the screen
plan. Report the demo URL, the screen list, and a one-line "what is mocked"
note so the client expectation is honest.

## Handoff

When the client approves the look and flow, the prototype screens become the
contract. Use `setup-portal` to turn them into a working portal: it runs the full
gates (capability map, package decisions, domain model, projections, connectors,
policies, tests). It has no prototype step of its own, so name the approved
screens as the source of its Capability Map surfaces and Build Plan scope.

Do not quietly start wiring a backend inside a prototype task. If scope shifts
from "show it" to "make it work", stop and switch skills.
