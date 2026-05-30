# Capability Map Template

The structured output of phase [1]. Write it to
`docs/portals/<slug>/capability-map.md` and have the user confirm it before any
wiring. It is the single source the build plan (phase [4]) is derived from, so it
must contain no `TBD`, only answers or confirmed assumptions.

Fill the template below. Drop sections that genuinely do not apply (e.g. no
connectors), but never leave a placeholder.

```markdown
# Capability Map, <Portal name>

- **Slug:** <slug>
- **Goal (north star):** <one sentence, what the working portal must let people do>
- **Tenancy:** <single-tenant | multi-tenant; isolation + branding notes (host-owned)>
- **Date:** <YYYY-MM-DD>

## Roles

| Role | Description | Internal/Customer |
| --- | --- | --- |
| <role> | <who they are> | <internal | customer> |

## Capabilities

For each capability:

### <Capability name>
- **Role(s):** <who>
- **Reads:** <data shown + where it comes from>
- **Writes:** <what they create/change + effect>
- **External sources:** <system(s) or "none">  → see Connectors
- **Approval:** <none | reviewed by <role> before effect>  → see Workflows
- **Surface:** <starter page | package route | dashboard widget | chat widget | page widget>
- **Audit/reporting:** <what history/report is needed, or "none">
- **Reuse:** <package-now | package-ready | workspace-only>, <one-line reason>

## Domain model

| Concept | Primitive | Notes (links, lifecycle/states, key events) |
| --- | --- | --- |
| <customer concept> | <entity | link | event | projection | policy> | <…> |

## Connectors

| System | Read/Write | Auth | Direction & frequency | Failure/reauth | Classification |
| --- | --- | --- | --- | --- | --- |
| <e.g. Moneybird> | <…> | <OAuth | API key> | <on-demand | scheduled | on-event> | <…> | <…> |

## Policy matrix (deny-by-default)

| Subject (entity/action/surface) | Allowed for | Ability to seed |
| --- | --- | --- |
| <…> | <role(s)> | <policy ability name> |

## Workflows / inbox

| Reviewed action | Trigger | Assignee | Approve → | Reject → | Audit event | Follow-up |
| --- | --- | --- | --- | --- | --- | --- |
| <…> | <…> | <role/user> | <…> | <…> | <…> | <notification/projection> |

## Observability

What operators must be able to answer: <connector health · projection drift ·
policy denials · agent/widget runs · audit trail, pick what applies>.

## Non-functional

- **Volume/scale:** <…>
- **Consistency:** <sync projection where required | eventual elsewhere>
- **GDPR/retention:** <personal data, retention period, deletion>
- **Trust level / data classification:** <…>

## First vertical slice

- **Capability:** <chosen first capability>
- **Why:** <foundational / highest value / unblocks the most>

## Assumptions (confirm before building)

- [ ] <assumption recorded during the interview>
```
