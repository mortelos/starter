---
name: setup-portal
description: "Use this FIRST whenever someone wants to set up, start, bootstrap, or build a customer portal on MortelOS / mortelos-starter. Enforces a transaction-style portal kickoff before implementation: public docs check, host verification, capability map, package decisions, build plan, explicit approval, controlled build, and verification evidence."
---

# Setup Portal

Use this skill for new MortelOS portal, workspace or customer-extension kickoff work.

The public docs are the source of truth. Do not use local copies of the MortelOS
method and do not reconstruct the method from memory.

## Required Public Docs

Minimum for every new portal:

| Topic | URL |
| --- | --- |
| Installation | https://mortelos.nl/docs/0/installation |
| Agentic development | https://mortelos.nl/docs/0/agentic-development |
| Building portals | https://mortelos.nl/docs/0/building-portals |
| Host app anatomy | https://mortelos.nl/docs/0/host-app-anatomy |
| TALL conventions | https://mortelos.nl/docs/0/tall-conventions |
| Package governance | https://mortelos.nl/docs/0/package-governance |
| Troubleshooting | https://mortelos.nl/docs/0/troubleshooting |

Conditional:

| Topic | Use when | URL |
| --- | --- | --- |
| MCP runtime | Operating mode, MCP routes or runtime agent tools are in scope | https://mortelos.nl/docs/0/mcp-runtime |

If the public docs cannot be accessed and the user has not provided the needed
docs content in the thread, stop.

## Required Gate Artifact

Before implementation, create or update one approved plan artifact, preferably
`.mortelos/portal-plan.md`. This is planning work, not implementation.

The artifact must contain these top-level sections:

1. `Docs Checked`
2. `Host Verification`
3. `Capability Map`
4. `Package Decisions`
5. `Build Plan`
6. `Approval`
7. `Implementation Log`
8. `Verification`

Do not implement until all gates through `Approval` are complete. If a gate
cannot be satisfied, stop and ask for the missing input. Do not continue by
assumption.

## Gate 1: Host Verification

Record concrete evidence that the target is a MortelOS Starter host app:

1. `composer.json` exists and identifies the host as a Laravel/MortelOS Starter
   app.
2. `artisan` exists.
3. Starter config, starter routes or the starter doctor command exists.
4. `php artisan starter:doctor --no-interaction` ran, or was skipped with a
   concrete reason.

Stop if the target is a package worktree, a vendor package or a non-MortelOS app.

## Gate 2: Docs Checked

For every required public docs page, record:

1. URL.
2. Date checked.
3. Relevant sections.
4. 1-2 constraints applied to this portal.

Read the conditional MCP runtime page when MCP, operate mode or runtime agent
tools are part of the request.

## Gate 3: Capability Map

Before code changes, record the capability map in the gate artifact. It must
include:

1. Roles and actors.
2. Capabilities per role.
3. Domain entities and relationships.
4. Data sources and connectors.
5. Approval or governance points.
6. Surfaces and entrypoints.
7. Verification criteria.

Missing any item means the map is incomplete. Ask targeted questions and wait.

## Gate 4: Package Decisions

Record a package decision before adding any new user-facing,
integration-facing or domain-facing entrypoint, or any reusable behavior.

Covered work includes routes, pages, widgets, Livewire components, actions,
policies, models, migrations, projections, services, jobs, events,
notifications, connectors, settings and MCP tools.

Classify each item as one of:

1. `package-now`
2. `package-ready`
3. `workspace-only`

Use the project mechanism from the public package-governance docs. If no helper
exists, append to `.mortelos/package-decisions.md` with:

1. Date.
2. Surface or behavior.
3. Classification.
4. Reason.
5. Related files or expected paths.
6. Decision owner.
7. Docs reference.

Before implementation and handoff, verify that every planned entrypoint or
reusable behavior has a recorded decision.

## Gate 5: Build Plan Approval

The build plan must address every item below. Use `N/A, reason: ...` when an
item does not apply.

1. Scope and non-scope.
2. Role summary.
3. Package decisions.
4. Domain model.
5. Projections.
6. Connectors.
7. Surfaces.
8. Policies and governance.
9. Workflows and inbox paths.
10. Tenant identity impact.
11. Observability.
12. Release notes and rollback.
13. First vertical slice.
14. Irreversible or costly decisions.
15. Tests and manual verification.
16. Risks and open questions.

After presenting the plan, stop until the user explicitly approves it. Accept
only unambiguous approval such as:

1. `Akkoord met dit plan`
2. `Goedgekeurd`
3. `Voer dit plan uit`

If approval is ambiguous, ask one confirmation question.

## Gate 6: Build

Execute only the approved plan. If scope changes, stop, update the gate artifact
and request approval again.

## Implementation Rules

1. Build host-side first unless the task is explicitly a package PR.
2. Do not patch `vendor/mortelos/*` or sibling `mortelos/*` package worktrees
   from a starter task.
3. Keep domain rules out of Blade and Livewire components.
4. Use actions, projections, policies, resolvers or package services for domain
   behavior.
5. Prefer existing MortelOS primitives and configured resolvers over new local
   abstractions.
6. Use deny-by-default policies for new abilities.
7. Keep user-facing Dutch prose free of em-dashes.

## Gate 7: Verification

For portal behavior changes, run:

```bash
php artisan starter:doctor --no-interaction
vendor/bin/pest
vendor/bin/pint --dirty
```

Also record manual smoke evidence:

1. Login to dashboard.
2. New or changed capability surface.
3. Approve/reject path when approvals exist.
4. Expected result versus actual result.

If a check fails, fix and rerun it. If blocked, report the failure, likely
cause and next step. If a check cannot be run, state the reason explicitly.

## Handoff

Report only:

1. What changed.
2. Package decisions recorded.
3. Verification results.
4. Manual smoke result.
5. Any deferred work or blocked item.
