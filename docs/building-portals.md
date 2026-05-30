# Building Portals On MortelOS

This document is the build method for portals on `mortelos/starter`.

The README explains what MortelOS Starter is and how to boot it. This document
explains how to turn a portal request into a governed MortelOS extension.

The core rule is simple:

```text
Start with what a role must be able to do.
Do not start with pages.
```

A MortelOS portal is a set of capabilities, policies, workflows and surfaces.
The page is only where a capability appears.

## The Build Flow

```text
Customer or operator asks for a portal
  |
  v
Capability-first interview
  |
  v
Capability map
  |
  v
Package decisions
  |
  v
Domain model
  |
  v
Projections and connector boundaries
  |
  v
Surfaces and widgets
  |
  v
Policies and governance
  |
  v
Workflows, inbox and audit
  |
  v
Tests, verification and release notes
```

For new work, follow this order. For existing work, find the last completed
phase and resume there.

## Operating Model

The user is the director and reviewer. The agent does the wiring.

| The user provides | The agent produces |
| --- | --- |
| Portal goal | Capability map |
| Roles and permissions | Policies and governance defaults |
| Data sources | Entities, links, projections and connectors |
| Approval expectations | Workflows and inbox items |
| Reuse expectations | Package decisions |
| Review feedback | Next vertical slice |

Do not write implementation code until the capability map and package decisions
are clear enough to avoid rework.

## Phase 0: Pre-Flight

Before interviewing or editing code, inspect the host app.

Check:

1. `README.md`, `AGENTS.md` and this document have been read
2. `config/starter.php` exists and required auth contracts are filled
3. `routes/web.php` requires `routes/starter.php`
4. `php artisan starter:doctor` can run
5. `.mortelos/package-decisions.md` exists or can be created
6. `docs/portals/<slug>/` already exists for this portal request
7. The test framework is Pest
8. Any existing capability plan or progress file should be resumed, not replaced

If the host has an existing portal plan, summarize status and ask before
continuing. Do not re-run the interview unless the previous plan is clearly
invalid or the user asks for it.

## Phase 1: Capability-First Interview

Ask questions one at a time. Keep the interview practical. The goal is not a
large strategy document, but enough clarity to build the right first slice.

Start with:

```text
What is the portal called, who is it for, and what is the main outcome it must make possible?
```

Then cover these dimensions:

| Dimension | Question |
| --- | --- |
| Roles | Who uses the portal and what role do they have? |
| Read capabilities | What may each role view or search? |
| Write capabilities | What may each role create, upload, edit, approve or trigger? |
| Data | Which domain objects matter? |
| External systems | Which systems provide or receive data? |
| Approvals | Which actions need human review? |
| Risk | Which data or action is sensitive? |
| Surfaces | Where should the capability appear? |
| Reporting | What should operators be able to explain later? |
| Reuse | Could this serve another MortelOS installation? |

Capture assumptions explicitly. If an answer is unknown, decide whether it
blocks the first slice. Unknowns that do not block the first slice can go into
the plan as follow-ups.

## Phase 2: Capability Map

Write the capability map before implementation.

Recommended file:

```text
docs/portals/<slug>/capability-map.md
```

Use this shape:

```markdown
# <Portal Name> Capability Map

## Goal

<One paragraph describing the outcome.>

## Roles

| Role | Description | Trust level |
| --- | --- | --- |
| Customer | Uploads and views own documents | External |
| Account manager | Reviews customer uploads | Internal |

## Capabilities

| Capability | Roles | Reads | Writes | Approval | Surface | Reuse |
| --- | --- | --- | --- | --- | --- | --- |
| Upload document | Customer | Dossier, required docs | Document upload event | No | Page widget | package-ready |
| Review document | Account manager | Pending uploads | Approve or reject | Yes | Inbox | package-ready |

## Data Sources

| Source | Purpose | Connector needed |
| --- | --- | --- |
| Local upload storage | Stores submitted files | No |
| CRM | Customer metadata | Yes |

## Risks And Controls

| Risk | Control |
| --- | --- |
| Customer sees another tenant's documents | Tenant-scoped policy and projection query |
| Approval bypass | Mutating action only reachable through policy-checked action |

## First Vertical Slice

<The smallest useful end-to-end capability.>
```

The map should be short enough for a stakeholder to review.

## Phase 3: Package Decisions

Every new surface, connector, widget, workflow or reusable service starts with a
package decision.

| Decision | Use when |
| --- | --- |
| `package-now` | The capability is reusable across MortelOS installations today |
| `package-ready` | Build in the host first, but keep the package boundary explicit |
| `workspace-only` | The behavior is specific to one customer or workspace |

Default to `package-ready` when unsure. It preserves speed while making the
future package boundary visible.

Use MortelOS dev tools when present:

```bash
php artisan mortelos:package-decision "Document Review Portal" \
  --decision=package-ready \
  --surface=mortelos/document-review \
  --reason="Reusable document review workflow with host-specific branding and policy defaults." \
  --no-interaction

php artisan mortelos:package-decisions:check --require-reason --no-interaction
```

Fallback file:

```markdown
## Document Review Portal

Surface: `mortelos/document-review`
Decision: `package-ready`
Reason: Reusable document review workflow with host-specific branding and policy defaults.
Date: 2026-05-30
```

Store fallback decisions in:

```text
.mortelos/package-decisions.md
```

## Phase 4: Domain Model

Map customer language to MortelOS primitives.

| Customer concept | MortelOS primitive | Example |
| --- | --- | --- |
| Customer, project, dossier, document | Entity | `Document` |
| Customer owns dossier | Entity link | `customer -> owns -> dossier` |
| User uploads document | Event | `DocumentUploaded` |
| Pending document list | Projection | `DocumentReviewStatus` |
| Role can approve document | Policy | `document.approve` |
| Review process | Workflow | Pending -> approved or rejected |
| Human review task | Inbox item | `document_review` |

The core OS primitives are owned by `mortelos/framework`. The current PHP
namespace may still be `Mortel\...` in code during the naming transition.

Rules:

1. Domain behavior lives in actions, projections, policies, workflows or package
   services
2. Blade and Livewire components read state and call actions
3. Write paths emit events when the fact matters for audit, rebuilds or agents
4. Projections are for reading, not for making domain decisions
5. Policies guard tenant data, role capabilities, widgets, navigation and agent
   tools

## Phase 5: Projections And Read Models

A projection is a read-optimized model that powers screens, search, dashboards
or agent tools.

Use a projection when:

1. The screen reads from multiple sources
2. The data must be explainable later
3. The same status appears in several surfaces
4. The state can be rebuilt from canonical facts
5. Runtime agents need a stable read surface

Good projection examples:

| Projection | Built from |
| --- | --- |
| Dossier overview | Customer entity, dossier links, document status |
| Document review status | Upload, approve and reject events |
| Connector health | Sync jobs, failures, reauth events |
| Revenue summary | Finance connector data and account links |
| Audit timeline | Events and workflow transitions |

Projection expectations:

1. Rebuild behavior exists
2. Verify behavior exists when drift matters
3. Rows store enough source references to explain where data came from
4. Tests cover both live writes and rebuilds

Common command pattern:

```bash
php artisan mortel:projection:rebuild --type=<type>
php artisan mortel:projection:verify --type=<type>
```

Use the exact command exposed by the host or package.

## Phase 6: Connectors

A connector is the boundary around an external system. It separates integration
logic from portal UI.

Examples:

| System | Connector purpose |
| --- | --- |
| Moneybird | Finance data, invoices, revenue |
| Google Drive | File discovery and document sync |
| Fireflies | Meeting transcript import |
| CRM | Customer metadata and ownership |
| Email | Notifications and intake |

A connector should usually own:

1. Provider or channel identifier
2. Setup flow or credential contract
3. OAuth or credential handling
4. Sync jobs
5. Health state
6. Retry and reauth behavior
7. Data request provider for chat and agent use
8. Policy hooks for setup and data access

Do not bury API calls inside Livewire components. Components consume connector
state through actions, projections or package services.

## Phase 7: Surfaces

Pick the smallest surface that fits the workflow.

| Surface | Use for | Typical owner |
| --- | --- | --- |
| Starter page | Core shell page such as dashboard, inbox, users, settings | Starter host |
| Package route | Reusable feature workspace | Feature package |
| Dashboard widget | Dense status, metric or queue summary | Host or package |
| Page widget | Reusable block embedded in a portal page | Host or package |
| Chat widget | Guided task inside `mortelos/chat` | Package |
| Inbox item | Human approval or review point | Host or workflow package |

Guidance:

1. Do not add a new page when a widget on dashboard or inbox is enough
2. Use Flux UI before custom Tailwind or Alpine
3. Use Livewire 4 single-file components for new UI
4. Keep policy checks outside component-only conditionals
5. Register reusable widgets through package configuration

## Phase 8: Policies And Governance

Policies are the safety layer of MortelOS. They decide who can see or do
something across web UI, widgets, workflows and agent tools.

Policy surfaces:

| Surface | Example ability |
| --- | --- |
| Entity visibility | `document.view` |
| Mutating action | `document.approve` |
| Navigation item | `documents.open` |
| Connector setup | `connector.moneybird.configure` |
| Connector data | `connector.moneybird.read-revenue` |
| Chat widget | `widget.document-review.run` |
| Agent tool | `agent.document.approve` |
| Governance change | `policy.propose` |

Rules:

1. Deny by default
2. Seed safe defaults during onboarding
3. Give every mutating action a policy ability
4. Give every mutating agent tool a policy ability
5. Use a central access resolver for visibility
6. Route policy changes through proposal and approval flows

Do not use component-only checks such as "if admin" for domain access. They are
too easy to bypass through search, chat, agents or future surfaces.

## Phase 9: Workflows And Inbox

Portals need workflows, not just pages. A workflow coordinates state,
assignment, approvals and consequences.

Use inbox or proposal flows for:

1. Document review
2. Customer approval
3. Connector setup or reauth
4. AI-proposed changes
5. Policy changes
6. Risky data mutations

Define every workflow with:

| Field | Example |
| --- | --- |
| Trigger | `DocumentUploaded` |
| Assigned role | Account manager |
| Item type | `document_review` |
| Detail surface | Inbox detail component |
| Approve action | `ApproveDocument` |
| Reject action | `RejectDocument` |
| Audit event | `DocumentApproved` or `DocumentRejected` |
| Follow-up | Update projection, notify customer |

State transitions should be testable. Approval should go through an action that
enforces policy and emits auditable facts.

## Phase 10: Tenant Identity

Tenant identity is host-owned. Starter gives you shell routes and stubs. The
host decides the real membership model.

Specify:

1. Tenant model
2. Membership model
3. Roles
4. Invitation flow
5. Tenant selection behavior
6. Super-admin behavior
7. Data isolation rules
8. Branding rules

Do not invent a tenant model casually inside a feature package. If a reusable
package needs tenant context, pass it through contracts, resolvers or config.

## Phase 11: Observability And Audit

A capability is not finished until operators can explain what happened.

Make these inspectable:

| Area | Questions operators should answer |
| --- | --- |
| Events | What happened, when and by whom? |
| Projections | Why does this row show this status? |
| Connectors | When did sync last run and did it fail? |
| Policies | Why was this user denied? |
| Workflows | Who approved, rejected or reassigned this item? |
| Agents | Which tool ran and under which grant? |
| Widgets | Which user triggered the widget and what changed? |

If the answer is "we would have to inspect raw database rows", add better audit
or status surfaces.

## Phase 12: Build Plan

After the capability map and package decisions, write a build plan.

Recommended file:

```text
docs/portals/<slug>/build-plan.md
```

Required sections:

1. Goal
2. Roles
3. Capability map summary
4. Package decisions
5. Domain model
6. Projections
7. Connectors
8. Surfaces
9. Policies
10. Workflows and inbox
11. Tenant identity
12. Observability
13. Test plan
14. Release notes and rollback
15. First vertical slice

Use `N/A, reason: ...` for sections that genuinely do not apply. Do not leave
`TBD` in the plan unless the user has accepted that unknown as a blocker.

## Phase 13: First Vertical Slice

Build the smallest useful capability end to end.

For a document review portal, a good first slice is:

```text
Customer uploads a document
  |
  v
DocumentUploaded event is recorded
  |
  v
DocumentReviewStatus projection shows pending
  |
  v
Account manager sees an inbox item
  |
  v
Account manager approves or rejects
  |
  v
Projection updates and audit history explains the decision
```

The slice should include:

1. Action class for the write path
2. Event for the important fact
3. Projection or read model for the surface
4. Policy ability, deny by default
5. Livewire or package surface
6. Inbox item when review is required
7. Test for allowed and denied roles
8. `starter:doctor` and Pest verification

Stop after the first slice and ask for review before continuing to the next
capability.

## Technical Conventions

Use the existing stack:

| Concern | Convention |
| --- | --- |
| Backend | Laravel 13 |
| UI | TALL stack |
| Components | Livewire 4 single-file components |
| Design system | Flux UI first |
| Tests | Pest |
| Formatting | Pint |
| Chat | `mortelos/chat` |
| OS primitives | `mortelos/framework` |

Naming:

| Item | Example |
| --- | --- |
| Action | `App\Actions\Documents\ApproveDocument` |
| Event | `App\Events\DocumentApproved` |
| Projection | `App\Projections\DocumentReviewStatus` |
| Policy | `App\Policies\DocumentPolicy` |
| Livewire page | `resources/views/livewire/pages/documents/index.blade.php` |
| Resolver | `App\Support\StarterSidebarNavigationResolver` |

## Verification Checklist

Before claiming a portal change is done:

```bash
vendor/bin/pint --dirty
php artisan starter:doctor
vendor/bin/pest
```

Also run a manual smoke test:

```text
Open http://127.0.0.1:8000
Log in as admin@example.test / password
Pass tenant select
Reach dashboard
Open the new capability surface
Verify allowed and denied role behavior
```

For a capability with approvals, verify both approve and reject paths.

## Release And Versioning

Treat reusable portal additions as package releases. Config keys, route names,
widget keys, policy abilities and projection schemas are public contracts.

Release guidance:

1. Keep migrations additive when possible
2. Document new config keys and defaults
3. Register package routes under a package-owned prefix
4. Test package behavior separately from host wiring
5. Record breaking changes in README or changelog
6. Provide rollback steps for config, migrations and routes

## AI Agent Checklist

When acting as the coding agent, do this:

1. Read `AGENTS.md`
2. Read `README.md`
3. Read this document
4. Check existing portal docs under `docs/portals/`
5. Interview before planning
6. Write or update the capability map
7. Record package decisions
8. Write the build plan
9. Build one vertical slice
10. Verify with doctor, tests and manual smoke
11. Stop for user review

Do not:

1. Invent a tenant model without explicit host requirements
2. Put domain rules in Blade or Livewire components
3. Add a new surface without a package decision
4. Bypass policies with local UI checks
5. Expose mutating agent behavior without a policy ability
6. Claim the portal works without verification

## Example Prompt For A Portal Kickoff

```text
We want a MortelOS customer portal for document review.

Customers can upload requested documents and see review status.
Account managers can review, approve or reject uploads.
Rejected documents need a reason and should notify the customer.
Admins can configure which document types are required.

Use a capability-first interview first.
Record package decisions.
Use entities, links, events, projections, policies and inbox workflows.
Build the first vertical slice only after I approve the build plan.
```
