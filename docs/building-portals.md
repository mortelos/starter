# Building Customer Portals on MortelOS

This is the design handbook for building customer portals on top of
`mortelos/starter`. The README covers installation and wiring. This document
covers how to think about a portal before you write code.

The core principle: start with the customer capability, not with a page.
Customer portals should be assembled from reusable, governed capabilities
instead of one-off page code.

```text
Customer asks for a portal
  |
  +-- What can each role do?
  +-- Which data is shown or mutated?
  +-- Which external systems are involved?
  +-- Which actions require approval?
  +-- Which parts are reusable across customers?
  |
  v
MortelOS extension plan:
  Capability map
    -> Package decision
    -> Domain model
    -> Projection / read model
    -> Connector / data source
    -> Widget / page surface
    -> Policy / governance
    -> Workflow / inbox approval
    -> Observability / audit
    -> Tests / release
```

## 1. Capability Map

Write the customer-facing capabilities first. Examples:

- Customers can view their dossiers.
- Customers can upload documents.
- Account managers can review uploaded documents.
- Finance users can request fresh revenue data from Moneybird.
- Operators can approve AI-proposed policy changes.

For each capability, record:

- Primary users and roles.
- Read data and write data.
- External data sources.
- Approval moments.
- Portal surfaces.
- Audit and reporting needs.
- Reuse potential across other MortelOS installations.

## 2. Package Decision

Before implementation, choose the package boundary:

| Decision | Use when |
| --- | --- |
| `package-now` | The capability is reusable across MortelOS installations now. |
| `package-ready` | The host app needs local wiring first, but the package boundary is explicit. |
| `workspace-only` | The behavior is customer-specific by design. |

In UteqOS host apps with MortelOS dev tools installed, record decisions with:

```bash
php artisan mortelos:package-decision "Customer Portal" \
  --decision=package-ready \
  --surface=mortelos/customer-portal \
  --reason="Reusable portal shell with customer-specific tenant policy and branding." \
  --no-interaction

php artisan mortelos:package-decisions:check --require-reason --no-interaction
```

When you do build in a package, it should usually include:

- `composer.json` with Laravel provider discovery.
- A service provider.
- Config file with a publish tag.
- Routes only when the package owns URLs.
- Views or a Livewire namespace only when it owns UI.
- Migrations only when it owns persistence.
- Tests for package behavior and host wiring.

Use `mortelos/entity-graph` as an example of a package that owns API routes,
views, a Livewire namespace, migrations, extension contracts, a chat widget and
an agent tool. Use `mortelos/policy-studio` as an example of a package that owns
governance widgets, routes and proposal-first policy flows.

## 3. Domain Model

Map customer concepts to MortelOS primitives:

| Customer concept | MortelOS primitive |
| --- | --- |
| Customer, project, document, dossier | Entity |
| Customer owns dossier, document belongs to project | Entity link |
| User uploads document, connector syncs invoice | Event |
| Portal-ready dossier overview | Projection or read model |
| Role may view or change something | Policy |
| Customer-specific behavior toggle | Tenant config or package config |

Avoid embedding domain rules directly in Blade or Livewire components. Put them
behind actions, projections, policies, resolvers or package services.

## 4. Projections And Read Models

A projection is a read-optimized representation of domain state, rebuilt from
canonical sources and verified when correctness matters. Use projections when a
portal needs a stable read surface built from events, entities, connector data
or workflow state.

Good projection candidates:

- Customer dossier summaries.
- Document review status.
- Connector sync status.
- Revenue, compliance or delivery metrics.
- Audit timelines.

Projection guidance:

- Keep projectors synchronous when consistency is required for the current
  request.
- Provide rebuild and verify commands when the projection can drift.
- Store enough source references to explain how a row was built.
- Keep write behavior in actions or aggregates, not in the projection view.
- Test both event write and projection rebuild paths.

MortelOS host apps already use projection commands such as:

```bash
php artisan mortel:projection:rebuild --type=entity
php artisan mortel:projection:verify --type=entity
```

## 5. Connectors

A connector is an integration boundary around an external system such as CRM,
document storage, finance, email, meeting transcription, ticketing or a
customer-specific API. It should separate setup, credentials, sync, health, data
requests and policy from the UI that consumes it.

A connector package usually contains:

- A channel driver or provider identifier.
- Setup provider for forms, OAuth or credential help.
- Sync jobs and health checks.
- Data request provider for agent or chat requests.
- Retry and reauth behavior.
- Channel status mapping.
- Policy hooks for setup and data access.

Connector packages should not render portal UI directly unless they own a
reusable setup or status widget. They should expose data, state and actions that
Starter, widgets or customer packages can compose.

## 6. Widgets And Portal Surfaces

Use the smallest surface that matches the workflow:

| Surface | Use for |
| --- | --- |
| Starter page | Core shell page such as dashboard, inbox, users or settings. |
| Package route | A reusable feature workspace owned by a package. |
| Dashboard widget | Dense operational overview or metric card. |
| Chat widget | Interactive task, proposal, graph, form or guided workflow inside chat. |
| Page widget | Reusable Livewire module embedded in a customer portal page. |

Chat widgets are registered through the chat `WidgetRegistry` with a
`WidgetDefinition` or `WidgetManifest`. A widget definition should declare:

- Stable key.
- Label and description.
- Skill name.
- Livewire component.
- Initial state and context schema.
- Optional result schema.
- Optional `policyAbility`.
- Trigger terms.

If a widget changes access, configuration or customer data, give it a policy
ability and cover it through Policy Studio. Prefer package registration for
reusable widgets and config-only registration for local experiments.

## 7. Policies And Governance

Policies define who can see and do things across the OS, and are the governance
layer that keeps customer portals safe.

Common policy surfaces:

- Entity visibility and data access.
- Navigation visibility.
- Widget access.
- Agent tool access.
- Connector setup and connector data access.
- Governance and policy changes.

Use policies instead of component-level conditionals whenever the rule affects
tenant data, role capability, navigation, widgets or agent tools.

Policy guidance:

- Deny by default.
- Seed safe defaults during tenant onboarding.
- Route visibility checks through the central access resolver.
- Give every mutating agent tool a policy action.
- Expose policy changes through proposal and approval flows.
- Use Policy Studio for grants, conditions, pending approvals and governance
  review surfaces.

## 8. Workflows And Inbox

Customer portals need workflows, not only pages. Workflows coordinate actions,
approvals and consequences. They should write events, update projections and
route human approval through inbox or Policy Studio surfaces.

Use inbox and proposal flows for:

- Document review.
- Customer approval.
- Connector reauth or setup.
- AI-proposed changes.
- Policy changes.
- Risky data mutations.

An inbox workflow should define:

- Trigger source.
- Assigned user or role.
- Item type.
- Detail component.
- Approve action.
- Reject action.
- Audit event.
- Follow-up notification or projection update.

## 9. Tenant Identity

A customer portal must be explicit about tenant identity:

- Users.
- Tenant memberships.
- Roles.
- Invitations.
- Tenant selection.
- Super-admin behavior.
- Data isolation.
- Optional customer branding.

Starter provides the shell for login, tenant selection, users and settings. The
host app owns the actual membership model, role mapping and invitation service.
Keep tenant selection and membership behavior host-owned unless a reusable
package boundary is proven.

## 10. Observability And Audit

Observability is part of the feature. Every customer extension should make
operational state inspectable:

- Connector health and last sync.
- Projection drift and rebuild status.
- Policy denials.
- Agent runs and tool calls.
- Widget runs.
- Inbox approvals and rejections.
- Event history and entity audit trail.

If operators cannot answer "why did this appear, disappear or fail?", the
extension is not ready for customer use.

## 11. Release And Versioning

Treat customer portal extensions as package releases, and version package APIs
intentionally. Treat config keys, route names, widget keys, policy abilities and
projection schemas as public contracts.

- Keep migrations additive when possible.
- Document new config keys and default behavior.
- Avoid publishing views unless customization is intentional.
- Register package routes under a package-owned prefix.
- Test host wiring separately from package behavior.
- Record breaking changes in the package README or changelog.
- Provide rollback guidance for config, migrations and route exposure.

## Customer Portal Recipe

1. Write the capability map and decide which capabilities are reusable.
2. Record package decisions for all new surfaces.
3. Model customer data as entities, links, events, projections and policies.
4. Add connector packages for external systems.
5. Build portal surfaces as Starter pages, package routes, dashboard widgets,
   chat widgets or page widgets.
6. Register navigation and universal search resolvers.
7. Seed default policies and expose governance through Policy Studio.
8. Add inbox and approval workflows for risky or human-reviewed actions.
9. Add observability for connector health, projection drift, policy denials,
   agent runs and widget runs.
10. Test tenant isolation, policy enforcement, connector failure modes,
    projection rebuilds and approval workflows.
11. Release through package versioning and document host-app wiring changes.
