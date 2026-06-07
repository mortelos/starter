# AI Architecture

This document defines how AI coding agents should build inside a MortelOS
Starter host app.

Use the public MortelOS documentation for the general platform model. Use this
document for starter-specific build rules, implementation boundaries and the
patterns an agent must follow while editing a host app.

## Scope

MortelOS Starter has two AI contexts:

| Context | Purpose | Owned by |
| --- | --- | --- |
| Build mode | An AI coding agent assembles or extends the portal | This host app |
| Operate mode | A runtime agent operates a live workspace | `mortelos/framework` MCP |

This document is about build mode. Runtime agent tools, OAuth access, tenant
scoping and MCP tool contracts are owned by `mortelos/framework`.

## Architecture Summary

```text
Portal request
  |
  v
Capability map
  |
  v
Package decision
  |
  v
Action
  |
  v
Policy check
  |
  v
Domain event
  |
  v
Projection / workflow / audit
  |
  v
Surface
  |
  v
Pest + starter:doctor
```

The page is not the architecture. A MortelOS portal is a set of capabilities,
policies, workflows, events, projections and surfaces.

## Capability First

Agents must not start by designing pages. Start with the capability:

1. Which role performs the action?
2. Which domain object is involved?
3. Which data is read or changed?
4. Which policy ability controls access?
5. Which domain event records the fact?
6. Which projection or read model powers the surface?
7. Which surface should expose it?
8. Which package boundary applies?

Write the capability map before implementation when the portal request is not
already specified.

## Package Decisions

Every new surface needs a package decision before implementation:

| Decision | Use when |
| --- | --- |
| `package-now` | The capability is reusable across MortelOS installations today |
| `package-ready` | Build host wiring now, keep the extraction boundary explicit |
| `workspace-only` | The behavior is customer-specific by design |

Default to `package-ready` when the capability looks reusable but still needs
host-specific auth, tenancy, policy or branding.

## Action Pattern

Actions are the command boundary for domain behavior.

Livewire components, controllers, console commands and agent tool adapters may
collect input and call an action. They must not own domain rules.

```text
Surface / adapter
  |
  v
Action
  |
  +-- validates command
  +-- resolves tenant / actor context
  +-- checks policy
  +-- applies domain change
  +-- records event
  +-- returns a small result object or identifier
```

Use actions for:

1. Creating or changing domain objects
2. Approvals, rejects, assignments and submissions
3. Connector sync intake that changes domain state
4. User, role, tenant or policy changes when they affect portal behavior
5. Any operation exposed to a runtime agent later

Do not put business rules in Blade, Livewire render methods, controllers or
policy conditionals.

## Event Pattern

Mutating domain behavior should record business facts as events.

An action is the command boundary. An event is the durable fact. A projection is
the read layer.

```text
Command
  |
  v
Action
  |
  v
Policy check
  |
  v
Domain event
  |
  +-- projection update
  +-- workflow transition
  +-- inbox item
  +-- audit trail
  +-- agent context
```

Use events for facts that are visible, audit-worthy, workflow-relevant or useful
to downstream projections and agents.

Good event candidates:

1. `DocumentUploaded`
2. `DocumentApproved`
3. `DocumentRejected`
4. `InvoiceSynced`
5. `PolicyProposed`
6. `PolicyApproved`
7. `TenantSettingsChanged`
8. `UserInvited`
9. `RoleAssigned`

Do not create domain events for:

1. Pure reads
2. Temporary Livewire form state
3. UI filters, tabs or sorting
4. Technical cache refreshes
5. Validation failures without domain value
6. Internal implementation details that should not become public facts

Direct database writes are acceptable for projections, read models, framework
tables, Laravel technical state and short-lived local state. Business facts that
must be explained later should flow through events.

## Projection Pattern

Surfaces read from projections or read models, not from event streams directly.

A projection should exist when the portal needs:

1. A dashboard summary
2. A searchable list
3. An inbox queue
4. A status view
5. A denormalized cross-entity overview
6. Agent-readable context

Projection rebuild and verification commands should exist when the projection
is important enough to operate after deployment.

## Policy Pattern

Policies are deny by default.

Every mutating action needs a policy ability. Every mutating runtime agent tool
needs the same level of policy control. Surface-level conditionals may hide UI,
but they do not replace the policy check in the action.

```text
User clicks approve
  |
  v
Livewire calls ApproveDocument action
  |
  v
Action checks document.approve
  |
  v
Action records DocumentApproved
```

Policy changes themselves should be proposal-first when they affect governance
or runtime agent behavior.

## Surface Pattern

Choose the smallest surface that fits the capability:

| Surface | Use for |
| --- | --- |
| Starter page | Core shell pages such as dashboard, inbox, users, settings and governance |
| Package route | Reusable feature workspace owned by a package |
| Dashboard widget | Dense operational summary, metric or queue |
| Page widget | Reusable embedded block on a page |
| Chat widget | Interactive agent-assisted task inside chat |
| Inbox item | Human approval or review point |

Surfaces orchestrate. Actions decide. Events record. Projections feed the UI.

## Connector Pattern

Connectors are integration boundaries around external systems. A connector may
fetch, normalize and validate external data, but domain state changes still flow
through actions and events.

```text
External system
  |
  v
Connector
  |
  v
Action
  |
  v
Domain event
  |
  v
Projection
```

Reusable connectors belong in packages unless there is a concrete
workspace-only reason.

## Agent Tool Pattern

Runtime agent tools should mirror existing application actions instead of
inventing a second business path.

```text
Human surface
  |
  v
Action

Runtime agent tool
  |
  v
Same action
```

This keeps policy checks, events, audit and projections consistent regardless
of whether a human or agent triggered the operation.

## Host-Owned Boundaries

The host app owns:

1. Auth controller implementations
2. Tenant and membership strategy
3. Local resolver bindings in `config/starter.php`
4. Customer-specific policy defaults
5. Workspace-specific config
6. Local orchestration that is not reusable

MortelOS packages own reusable feature surfaces, connector boundaries, widgets,
package config, extension contracts and package-level tests.

Do not edit `vendor/mortelos/*` or a local `mortelos/*` package worktree from a
starter task unless the task is explicitly a package PR.

## Testing Contract

A capability is not complete until these checks are covered:

1. The action has focused tests for allowed and denied roles
2. The event is recorded for the successful path
3. The projection or read model reflects the new state
4. Approval or inbox paths are tested when present
5. `php artisan starter:doctor` stays green
6. `vendor/bin/pest` stays green

Architecture tests should be added when a rule becomes easy to regress, such as
domain logic leaking into Livewire components or a package importing `App\`.

## Promotion To Public Docs

This file is the starter-local source for AI build behavior. Stable concepts
that apply across MortelOS installations should be promoted to the general docs
package:

1. Capability-first method
2. Action, event and projection pattern
3. Package decision rules
4. Build mode versus operate mode
5. Runtime agent tool pattern

Keep starter-specific file paths, config keys and seeded account details in this
repository.
