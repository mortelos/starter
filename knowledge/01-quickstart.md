# 01 — Quickstart (60-second mental model)

## What this repo is

`mortelos/starter` is a **Laravel application template** for AI-driven portal
builds on the TALL stack. One command spins up a runnable Laravel app with the
MortelOS shell already wired:

```bash
composer create-project mortelos/starter mijn-portal
```

The project boots immediately: login, tenant select, dashboard, inbox,
governance, users, settings, plus a seeded admin account. From there an AI
agent assembles portal capabilities on top.

## What you get out of the box

| Layer | What's in place |
| --- | --- |
| Framework | Laravel 13.8, Livewire 4 SFC, Flux UI, Pest, Tailwind via Vite |
| Auth | Working `PasswordLoginController` + stub `Passkey*`, `AcceptInvitation*`, `TenantSelectController`; `ResolvePostLoginRedirect` returns `/dashboard` |
| Shell pages | `/login`, `/dashboard`, `/inbox`, `/governance`, `/users`, `/settings`, `/onboarding`, `/auth/tenant-select`, `/logout` |
| Views | Layouts (`mortelos-starter::layouts.app`, `layouts.guest`), all `starter::pages.*` Livewire SFCs, shared sidebar/topbar/universal-search |
| Config | `config/starter.php` with full contract surface; required keys point at working stubs |
| Diagnostic | `php artisan starter:doctor` reports wiring health |
| Database | SQLite by default, migrations + `DatabaseSeeder` create `admin@example.test` / `password` |
| Tests | Pest baseline: boot smoke + full config-shape coverage |

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
10. Testing host wiring and capability behavior

This is the method in `docs/building-portals.md`. The Claude
[`portal-kickoff` skill](../.claude/skills/portal-kickoff/SKILL.md) runs it as
a guided workflow; other agents follow the same phases manually.

## Bootstrapping from zero

```bash
composer create-project mortelos/starter mijn-portal
cd mijn-portal
npm install --ignore-scripts
npm run build
php artisan starter:doctor    # should be green
php artisan serve             # open http://127.0.0.1:8000
# log in as admin@example.test / password
```

For active dev (server + queue + logs + vite in parallel):

```bash
composer dev
```

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
