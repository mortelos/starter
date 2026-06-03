# Handoff Template

Phase [7]. After the one-pass build is green, write
`docs/portals/<slug>/handoff.md` so the partner can continue development without
replaying the whole kickoff. It is the bridge from "the agent built the planned
portal" to "the partner extends it".

Keep it factual and short. It complements, does not duplicate, `build-plan.md`
(the design) and `progress.md` (the checklist). The handoff answers: *what exists
now, what was left, and where do I plug in next?*

```markdown
# Handoff, <Portal name>

- **Goal (north star):** <one sentence>
- **Status:** built and green on <YYYY-MM-DD>. <N> capabilities, <N> tests <pass>.
- **Plan:** docs/portals/<slug>/build-plan.md · **Progress:** docs/portals/<slug>/progress.md

## What was built

| Capability | Entities | Surface(s) | Policy abilities | Tests |
| --- | --- | --- | --- | --- |
| <…> | <…> | <route / widget> | <…> | <n, green> |

- **Events / projections:** <events emitted, read models and sync/async>
- **Connectors:** <system + boundary, or "none">
- **Inbox / workflows:** <reviewed actions wired, or "none">
- **Observability:** <what an operator can inspect>

## Verification (evidence)

- Test suite: `<command>` -> **<n/n green>** (<framework>).
- App boots `login → dashboard`: <yes>.
- Deny-by-default holds: <how confirmed, e.g. policy test asserts deny with no grant>.

## Deferred / fast-follows

Things deliberately left for the partner, with the reason each was deferred.

- [ ] <fast-follow> — <why deferred> (<where it would go>)
- [ ] <…>

## Deviations from the plan

What was built differently from `build-plan.md`, and why (mirrors the deviations
log in `progress.md`).

- <deviation> — <reason / impact>

## How to add the next capability

Follow `references/build-loop.md`: entity → events/projection → deny-by-default
policy → surface → inbox/connector (if needed) → observability → tests, then tick
`progress.md`. Reuse the patterns already in this portal:

- **Model + migration + factory:** see `<example path>` (or run `tall-model`).
- **Action + event + listener:** see `<example path>`.
- **Projection + rebuild:** see `<example path>`.
- **Policy (deny-by-default):** see `<example path>` + the Gate registration in
  `<provider>`.
- **Surface (Livewire 4 SFC):** see `<example path>`.
- **Tests:** see `<example test path>`; run with `<command>`.

## Where things live

- **Namespace / code root:** `App\Portals\<PortalName>\…` (`app/Portals/<PortalName>/`).
- **Migrations:** `<path>`.
- **Routes:** registered in `<provider>` (<route names / prefixes>).
- **Config keys:** `<config/starter.php keys this portal fills>`.
- **Tests:** `tests/Feature/Portals/<PortalName>/`.
- **Host requirements (tenant identity):** <what the host must provide; see build-plan §9>.
```

Fill every section. If a section is genuinely empty (no connectors, no
deviations), write `none` rather than deleting it, so the partner can tell the
difference between "nothing here" and "forgotten".
