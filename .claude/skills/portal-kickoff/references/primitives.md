# MortelOS Primitives Cheat Sheet

Map each customer concept to the right primitive (building-portals.md §3–§6).
Keep domain rules behind actions, projections, policies, resolvers, or package
services, never inside Blade or Livewire components.

## Concept → primitive (§3)

| Customer concept | Primitive |
| --- | --- |
| Customer, project, document, dossier | **Entity** |
| Customer owns dossier, document belongs to project | **Entity link** |
| User uploads document, connector syncs invoice | **Event** |
| Portal-ready dossier overview | **Projection / read model** |
| Role may view or change something | **Policy** |
| Customer-specific behavior toggle | **Tenant config / package config** |

## Projections & read models (§4)

A read-optimized representation rebuilt from canonical sources. Use one when the
portal needs a stable read surface built from events, entities, connector data, or
workflow state. Good candidates: dossier summaries, document review status,
connector sync status, revenue/compliance/delivery metrics, audit timelines.

- Keep projectors synchronous when the current request needs consistency.
- Provide rebuild + verify commands when a projection can drift
  (`php artisan mortel:projection:rebuild --type=<…>` / `:verify`).
- Store enough source references to explain how a row was built.
- Keep write behavior in actions/aggregates, not in the projection view.
- Test both the event-write path and the projection-rebuild path.

## Connectors (§5)

An integration boundary around an external system (CRM, document storage, finance,
email, transcription, ticketing, a customer API). Separate setup, credentials,
sync, health, data requests, and policy from the UI that consumes it. A connector
package usually contains: a channel/provider id, a setup provider (forms/OAuth),
sync jobs + health checks, a data-request provider (agent/chat), retry/reauth,
channel status mapping, policy hooks for setup and data access.

Connectors **expose data, state, and actions**, they do not render portal UI
unless they own a reusable setup/status widget.

## Surfaces & widgets (§6)

Use the smallest surface that matches the workflow:

| Surface | Use for |
| --- | --- |
| **Starter page** | Core shell: dashboard, inbox, users, settings |
| **Package route** | A reusable feature workspace owned by a package |
| **Dashboard widget** | Dense operational overview or metric card |
| **Chat widget** | Interactive task/proposal/graph/form/guided workflow in chat |
| **Page widget** | Reusable Livewire module embedded in a portal page |

**Chat widgets** register through the chat `WidgetRegistry` with a
`WidgetDefinition`/`WidgetManifest` declaring: a stable key, label + description,
skill name, Livewire component, initial state + context schema, optional result
schema, optional `policyAbility`, and trigger terms. If a widget changes access,
config, or customer data, give it a policy ability and cover it through Policy
Studio. Prefer package registration for reusable widgets, config-only registration
for local experiments.

## Reference packages

- `mortelos/entity-graph`, owns API routes, views, a Livewire namespace,
  migrations, extension contracts, a chat widget, and an agent tool.
- `mortelos/policy-studio`, owns governance widgets, routes, and proposal-first
  policy flows.

Use them as worked examples of what a well-bounded package owns.
