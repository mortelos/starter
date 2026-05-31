# Build Plan Template

Phase [4]. Two files, both under `docs/portals/<slug>/`:

- `build-plan.md`: the standalone build plan (this template), derived entirely
  from the confirmed capability map. Self-contained and **complete**: no GSD
  dependency, no `TBD`. The build runs in one pass with no per-slice review, so a
  gap here becomes a gap in the portal.
- `progress.md`: the goal-tracking checklist the build pass updates; its goal line
  is the build's stop condition.

Order **every** capability by value and dependency into a single build order;
capability #1 is the most foundational (capability map "Build order"). The whole
list is built in phase [6], not just the first.

## N/A markers (read this first)

Sections that genuinely do not apply to this portal MUST be marked
`**N/A**, reason: <one short sentence>` instead of left empty. Example: a portal
with no external systems writes `## Connectors (§5)` followed by `**N/A**,
reason: this portal only consumes host-owned data; no external integrations in v1.`
Empty sections read as oversight; explicit N/A reads as a confirmed decision.

## build-plan.md

```markdown
# Build Plan, <Portal name>

- **Goal (north star):** <one sentence>
- **Source:** docs/portals/<slug>/capability-map.md (confirmed <YYYY-MM-DD>)
- **Host test framework:** <pest | phpunit> (from phase [0] detection)

## Package decisions (§2)

| Surface | Decision | Reason |
| --- | --- | --- |
| <…> | <package-now / package-ready / workspace-only> | <…> |

## Domain model (§3)

- **Entities:** <list, with key fields>
- **Links:** <entity, entity relationships>
- **Lifecycle/states:** <stateful entities and their states>
- **Events:** <events to emit>

## Projections / read models (§4)

| Projection | Built from | Sync/async | Rebuild+verify? |
| --- | --- | --- | --- |
| <…> | <events/entities/connector> | <…> | <yes/no> |

## Connectors (§5)

If the capability map records no external systems, write:
`**N/A**, reason: <one sentence>.`

Otherwise, per external system: provider, auth, sync direction/frequency,
failure/reauth, classification, and which capabilities consume it.

## Surfaces & widgets (§6)

| Capability | Surface type | Component / route | Policy ability |
| --- | --- | --- | --- |
| <…> | <…> | <…> | <…> |

## Policies & governance (§7)

- **Abilities to seed (deny-by-default):** <list>
- **Access resolver wiring:** <how visibility routes through governance.access_resolver>
- **Proposal/approval exposure:** <which changes go through Policy Studio>

## Workflows / inbox (§8)

If no capability has an approval moment, write:
`**N/A**, reason: <one sentence>.`

Otherwise:

| Action | Trigger | Assignee | Approve | Reject | Audit | Follow-up |
| --- | --- | --- | --- | --- | --- | --- |
| <…> | <…> | <…> | <…> | <…> | <…> | <notification/projection> |

## Tenant identity (§9), host requirements

What the host must provide: <users, memberships, roles, invitations, tenant
selection, super-admin, isolation, branding>.

## Observability (§10)

What this portal exposes: <connector health, projection drift, policy denials,
agent/widget runs, audit trail; per capability>.

## Tests & release (§11)

- Test framework: <pest | phpunit> (matches the host, from phase [0]).
- Coverage targets: tenant isolation, policy enforcement, connector failure modes
  (if applicable), projection rebuilds, approval workflows (if applicable).
- Public contracts to keep stable: config keys, route names, widget keys, policy
  abilities, projection schemas. Keep migrations additive. Register package routes
  under a package-owned prefix.

## Build order

1. **<Capability #1, most foundational>**, <why first>
2. <Capability #2>
3. <…>

Every capability is built end to end in a single pass per references/build-loop.md.
There is no review checkpoint between capabilities; the plan is approved once, at
the plan gate (phase [5]), then the whole order is built.
```

## progress.md

```markdown
# Portal Progress, <Portal name>

**Goal:** <north star>. The portal is done for this kickoff when every capability
below that the goal requires is built, tested, and green; from there the partner
continues development.

## Foundation
- [ ] Starter wired, app boots login -> tenant-select -> dashboard
- [ ] Deny-by-default policy scaffold seeded
- [ ] Tenant identity documented as host requirement

## Capabilities (build order)
- [ ] 1. <capability #1, most foundational>  (entity · projection · policy · surface · tests)
- [ ] 2. <capability #2>
- [ ] 3. <…>

## Deviations
- <YYYY-MM-DD>, <what diverged from build-plan.md and the choice made>

## Log
- <YYYY-MM-DD>, <what was built / changed>
```

Update `progress.md` as the build pass runs: tick each capability as it goes
green (the resume anchor), append deviations as they happen, and add a closing
log entry at handoff so the build stays auditable.
