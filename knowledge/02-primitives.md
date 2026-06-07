# 02 — MortelOS primitives

These are the only building blocks you should be assembling a portal from.
Anything domain-specific lives behind one of these. Source method:
`docs/building-portals.md` §3–§7.

## Entity

A **first-class noun** in the customer's domain: customer, project, document,
dossier, invoice, contract, employee. Backed by `mortelos/framework`'s entity registry,
addressable by stable id, type, and tenant. Entities carry attributes; they do
not carry behavior. Behavior lives in actions, projections and policies.

Use an entity when:

- Multiple surfaces (chat, inbox, dashboard, agent) need to reference the same
  noun by id
- You need governance, audit and access control on the noun
- You need to link it to other nouns

Don't use an entity for ephemeral state (form drafts, UI toggles, filter
preferences).

## Entity link

A **typed relation** between two entities. `customer→owns→dossier`,
`document→belongs_to→project`, `invoice→issued_by→tenant`. Links are first-class:
they have type, direction, optional metadata, and an audit trail.

Use a link when:

- The relation is part of the domain (not an implementation detail of a query)
- Other surfaces need to traverse it
- Cardinality or direction matters

Don't model every foreign key as a link; reserve it for domain-meaningful
relations.

## Event

An **immutable record that something happened**. `DocumentUploaded`,
`InvoiceSynced`, `PolicyProposed`, `MembershipInvited`. Under the hood:
`spatie/laravel-event-sourcing` stores MortelOS events in the `events` table.

Use an event when:

- The fact is interesting to projections, audit, downstream workflows or other
  packages
- You'll want to rebuild a read model from the canonical history
- You need an audit trail of who/what/when

Write paths produce events. Projections subscribe to events to update read
models. Surfaces read from projections.

## Projection / read model

A **read-optimized representation** of domain state, rebuilt from canonical
sources (events, entities, connector data). Synchronous when the current request
needs consistency; otherwise queued.

Good projection candidates:

- Customer dossier summaries
- Document review status
- Connector sync status
- Revenue, compliance, delivery metrics
- Audit timelines

Required properties:

- A **rebuild** command (`php artisan mortel:projection:rebuild --type=<…>`)
- A **verify** command (`php artisan mortel:projection:verify --type=<…>`)
- Source references stored on each row so you can explain how it was built
- Tests for both the event-write path and the projection-rebuild path

## Connector

An **integration boundary** around an external system: CRM, document storage,
finance (Moneybird), email, meeting transcription (Fireflies), ticketing, a
customer-specific API.

A connector package typically owns:

- A channel/provider identifier
- A setup provider (forms, OAuth, credentials, health check)
- Sync jobs and a sync health endpoint
- A data-request provider for agent and chat usage
- Retry and reauth behavior
- A channel status mapping
- Policy hooks for setup and data access

A connector exposes data, state, and actions. It does **not** render portal UI
unless it owns a reusable setup or status widget. Portal surfaces compose
connectors via projections and actions.

## Policy

A **governance ability** that controls who can see or do something. Policies are
the safety layer for the entire portal: entity visibility, navigation
visibility, widget access, agent-tool access, connector setup, governance
changes.

Rules:

- **Deny by default**. Seed safe defaults during tenant onboarding.
- Route all visibility checks through the central
  `governance.access_resolver`, not component-level `@if` conditionals.
- Every mutating agent tool gets a policy ability.
- Policy changes flow through proposal + approval (Policy Studio), not direct
  config edits.

## Surfaces (where things appear)

| Surface | Use for | Lives in |
| --- | --- | --- |
| Starter page (`starter::pages.*`) | Core shell: dashboard, inbox, users, settings, governance | this package |
| Package route | Reusable feature workspace owned by a package | dedicated package |
| Dashboard widget | Dense operational overview or metric card | host or package, registered in `dashboard.primary_widgets` / `secondary_widgets` |
| Chat widget | Interactive task, proposal, graph, form, guided workflow inside chat | package, registered via `WidgetRegistry` |
| Page widget | Reusable Livewire block embedded in a portal page | package or host |

Pick the smallest surface that fits. A workflow is rarely a new page; it's
usually a widget on an existing surface.

## Anti-patterns

- Domain rules in Blade or Livewire components → move to actions, projections,
  policies, resolvers, or package services
- Direct DB writes from a controller → write events, let projections catch up
- Bypassing policies with `@if (auth()->user()->isAdmin())` → use the access resolver
- New tenant/membership model in a portal → that's host-owned, document it as a
  requirement
- Connector that renders custom portal UI → expose state/data only, build the UI
  in a widget
