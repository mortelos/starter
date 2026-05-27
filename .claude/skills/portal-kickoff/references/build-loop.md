# Build Loop

Phases [5] and [6]. Build one capability end to end as a vertical slice, checkpoint
with the user, update `progress.md`, then move to the next. This skill owns the
MortelOS-specific shape; the **TALL skills own the Laravel/Livewire implementation**.

## The slice (build one capability fully)

Build in this order so each layer rests on a tested one below it:

1. **Entity + links** — the domain object(s) for this capability and their
   relationships. Use the **`tall-model`** skill for the model + migration + factory.
2. **Events + projection** — emit the events the capability needs; build the
   read model the surface consumes. Keep projectors synchronous if the request
   needs consistency; add rebuild/verify if it can drift.
3. **Policy (deny-by-default)** — add the policy ability for every mutating action
   and sensitive read in this capability. Route visibility through
   `governance.access_resolver`, not component conditionals.
4. **Surface** — build the chosen surface (starter page / package route / dashboard
   widget / chat widget / page widget). Register navigation/search resolver entries
   if the capability adds them. Register chat widgets through the `WidgetRegistry`.
5. **Inbox flow** (only if the capability has an approval moment) — wire trigger →
   assignee → approve/reject → audit event → follow-up.
6. **Connector** (only if the capability touches an external system) — build or wire
   the connector boundary: setup, sync, health, data request, retry/reauth.
7. **Observability** — make this capability's operational state inspectable
   (connector health, last sync, projection status, policy denials, runs, audit).
   If an operator cannot answer "why did this appear, disappear, or fail?", it is
   not done.
8. **Tests** — use **`tall-feature`** (TDD red-green-refactor) to drive the
   implementation and **`tall-test`** to round out coverage. Cover the event-write
   path, the projection rebuild, policy enforcement, tenant isolation, and (if
   present) connector failure modes and the approval workflow.

## TALL hand-off

For the actual code, lean on the existing skills rather than reinventing
conventions:

- **`tall-model`** — scaffold the model, migration, factory, optional states/policy.
- **`tall-feature`** — build the capability via strict TDD (red → green → refactor)
  with code review.
- **`tall-conventions`** — the Livewire 4 SFC, Flux UI, action-class, and state
  machine rules to follow.
- **`tall-test`** — write Pest tests for existing code.

This skill stays the orchestrator: it decides *what* primitive to build and *how it
must behave in MortelOS* (governed, observable, tenant-safe); the TALL skills decide
*how the Laravel/Livewire code is written*. If `uteq-tall-master` is not installed,
build by `tall-conventions` principles manually and say so.

## Checkpoint protocol (director/reviewer)

After each slice, **stop** and hand control back to the user:

1. Summarize what was built, in a short list (not a walkthrough).
2. State the verification you ran and its result — tests green/red, policy denies by
   default, app still boots, observability present. Evidence before claims.
3. Update `progress.md`: tick the capability, append to the log, note any deviation
   from `build-plan.md`.
4. Ask whether to proceed to the next capability, adjust the plan, or stop.

Never chain into the next slice without the user's go-ahead. The goal in
`progress.md` is the stop condition for the whole loop: when every capability the
goal requires is built, tested, and reviewed, the portal is done — report that and
stop.

## Deviations

If reality diverges from the plan (a capability needs an entity the map missed, a
connector behaves differently than assumed), do not silently improvise: note it at
the checkpoint, update `build-plan.md` and `capability-map.md`, and confirm before
building on the change. The plan stays the source of truth.
