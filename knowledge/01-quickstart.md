# 01 — Quickstart (60-second mental model)

## What this package is

`mortelos/starter` is a **Laravel library package** that ships the app shell
(layout, routes, auth pages, dashboard, inbox, governance, users, settings) so
host apps don't rebuild it. It is consumed via Composer as
`vendor/mortelos/starter` and does not boot standalone.

## What a host app provides

| The package | The host |
| --- | --- |
| Layout `mortelos-starter::layouts.app`, `starter::` Livewire pages | Brand + which shell slots are active |
| Routes for login, tenant-select, dashboard, inbox, governance, users, settings, onboarding, invitations, logout | Auth controllers + post-login redirect rule |
| Publishable config + views | Resolvers for navigation, search, governance, inbox |
| Stubs for fastest boot | Tenant/membership/roles/invitation model |

## What "building a portal" means here

A **portal** is a customer-facing extension of MortelOS — a workspace where
specific roles can view, upload, approve, sync, and operate on customer data.
You build a portal by:

1. Interviewing for the **capability map** (what each role can do)
2. Recording a **package decision** for each new surface
3. Modelling the domain as **entities + links + events**
4. Adding **projections** for the read surfaces
5. Adding **connectors** for external systems
6. Building **surfaces** (starter pages, package routes, widgets)
7. Seeding **deny-by-default policies**
8. Routing risky actions through the **inbox**
9. Wiring **observability**
10. Testing host wiring and package behavior

This is the method in `docs/building-portals.md`. The Claude
[`portal-kickoff` skill](../.claude/skills/portal-kickoff/SKILL.md) runs it as
a guided workflow; other agents follow the same phases manually.

## Bootstrapping a host (the short version)

```bash
# 1. Pull in the package
composer require mortelos/starter

# 2. Publish defaults + working stubs
php artisan vendor:publish --tag=mortelos-starter
php artisan vendor:publish --tag=mortelos-starter-stubs

# 3. Wire the three required edits (see AGENTS.md §3)
#    - routes/web.php requires routes/starter.php (which requires the package routes)
#    - resources/views/layouts/app.blade.php delegates to mortelos-starter::layouts.app
#    - config/starter.php merges package defaults with host auth bindings

# 4. Verify
php artisan starter:doctor      # green = ready
php artisan serve               # login -> tenant-select -> dashboard works
```

Detailed step-by-step: [`docs/init-host-app.md`](../docs/init-host-app.md).

## Decisions you do not have to make

These are already decided; don't re-litigate them:

- **TALL stack** (Tailwind, Alpine, Livewire 4, Laravel) — fixed
- **Flux UI** as the design system — always check Flux first before custom
- **Pest** as the test framework
- **spatie/laravel-event-sourcing** under `uteq/mortel`'s events
- **OAuth 2.1 + Passport** for MCP
- **Policy Studio** for governance review surfaces
- **Capability-first, never page-first**

If you find yourself reaching for Filament, Jetstream, Inertia, Vue/React,
plain PHPUnit, class-based Livewire v3 — stop. That's not this stack.
