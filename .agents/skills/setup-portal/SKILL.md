---
name: setup-portal
description: Use this FIRST whenever someone wants to set up, start, bootstrap, or build a customer portal on MortelOS / mortelos-starter. This skill uses the public MortelOS documentation as the source of truth, interviews for the capability map, records package decisions, writes the build plan, gets approval, builds the portal and verifies the result.
---

# Setup Portal

Use this skill for new MortelOS portal, workspace or customer-extension kickoff work.

Do not use local copies of the MortelOS method. The public docs are the source
of truth:

| Topic | URL |
| --- | --- |
| Agentic development | https://mortelos.nl/docs/0/agentic-development |
| Building portals | https://mortelos.nl/docs/0/building-portals |
| Host app anatomy | https://mortelos.nl/docs/0/host-app-anatomy |
| TALL conventions | https://mortelos.nl/docs/0/tall-conventions |
| Package governance | https://mortelos.nl/docs/0/package-governance |
| MCP runtime | https://mortelos.nl/docs/0/mcp-runtime |
| Troubleshooting | https://mortelos.nl/docs/0/troubleshooting |

## Hard Gates

These gates are mandatory. If a gate cannot be satisfied, stop and ask for the
missing input. Do not continue by assumption.

1. Verify this is a MortelOS Starter host app before changing files.
2. Read the relevant public docs pages before planning or editing.
3. Produce a capability map before writing code.
4. Record a package decision before adding any surface.
5. Produce a build plan and get explicit user approval before implementation.
6. Verify the implementation before claiming the portal works.

If the public docs cannot be accessed and the user has not provided the needed
docs content in the thread, stop. Do not reconstruct the method from memory.

## Required Capability Map

Before code changes, state the capability map in the thread or in the approved
work artifact. It must include:

1. Roles and actors.
2. Capabilities per role.
3. Domain entities and relationships.
4. Data sources and connectors.
5. Approval or governance points.
6. Surfaces needed, such as dashboard widgets, pages, chat widgets or inbox
   workflows.
7. Verification criteria.

Missing any item means the map is incomplete. Ask targeted questions and wait.

## Required Package Decision

Before adding a route, page, widget, workflow or connector, classify it as one
of:

1. `package-now`
2. `package-ready`
3. `workspace-only`

Record the decision with surface name and reason. Use the project mechanism from
the public package-governance docs. If no artisan helper exists in the host,
write the decision to `.mortelos/package-decisions.md`.

Do not add a surface with an unrecorded package boundary.

## Required Plan Approval

The build plan must include:

1. Scope and non-scope.
2. Files or areas expected to change.
3. Data model and migration impact.
4. Policies or governance checks.
5. Tests and manual verification.
6. Risks or open questions.

After presenting the plan, stop until the user explicitly approves it. A vague
positive reaction is enough only when it clearly approves the plan.

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

## Verification Gate

Before handoff, run the checks that match the touched behavior. For portal
behavior changes, the default gate is:

```bash
php artisan starter:doctor
vendor/bin/pest
vendor/bin/pint --dirty
```

If a check cannot be run, state that explicitly with the reason.

## Handoff

Report only:

1. What changed.
2. Package decisions recorded.
3. Verification results.
4. Any deferred work or blocked item.
