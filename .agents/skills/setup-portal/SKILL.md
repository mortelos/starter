---
name: setup-portal
description: Use this FIRST whenever someone wants to set up, start, bootstrap, or build a customer portal on MortelOS / mortelos-starter, even when they only describe a capability ("customers should be able to upload documents", "account managers approve invoices", "show clients their dossiers") without saying the word "portal". This skill interviews for the capability map one question at a time, wires the Starter foundation correctly, records package decisions, produces one complete standalone build plan, gets the user's approval on that plan once, then builds the entire portal in a single pass with full test coverage and hands off to the partner to continue development, leaning on the TALL skills for implementation. Trigger it for any new MortelOS portal, workspace, or customer-extension kickoff. Do NOT trigger when the portal is built on a different stack such as Jetstream, Filament, or plain Laravel without mortelos/starter.
---

# Setup Portal

The first skill a user runs when building a customer portal on MortelOS. It turns
the design handbook in `docs/building-portals.md` into a guided, goal-directed
workflow: set the foundation primitives up the governed way, produce one complete
standalone build plan, get the plan approved at a single gate, then build the
whole portal in a single pass and hand it off ready for the partner to extend.

**The operating model is plan-once, build-all, hand off.** You (the agent)
interview, wire the foundation, and write one complete build plan. The user
approves that plan once, at a single gate. Then you build the entire portal in
one pass, with full tests, no per-capability review stops. When it is green you
hand off to the partner with a short handoff doc so they can keep developing. The
user is still the director: they direct by approving the plan up front, not by
reviewing every slice. Assume the Starter foundation is sound; verify it boots,
do not rebuild it.

`docs/building-portals.md` is the canonical method. This skill is the execution,
interview, plan, build, and handoff layer on top of it. Read that document early
and treat its section numbers (§1–§11) as the source of truth; the references
here only add *how to ask*, *how to wire*, *how to plan*, and *how to build*.

## Availability in a host app

This skill ships inside the host app at `.agents/skills/setup-portal/`.
Portal work writes into the host app (`App\...`, `config/starter.php`,
`routes/web.php`, host policies), so run the skill from the host-app working
directory. `.claude/skills/setup-portal/` is a symlink for Claude Code
discovery. Treat `.agents/skills/setup-portal/` as the single source of truth.

## Workflow

```text
setup-portal  ("ik wil een klantportal voor ...")
        |
        v
[0] PRE-FLIGHT, host context & idempotency
     detect: is Starter wired? auth contracts filled? .mortelos/ present?
     existing portal plan under docs/portals/? -> fresh bootstrap OR resume
     detect host test framework (pest vs phpunit) for the build pass
        |
        v
[1] INTERVIEW -> CAPABILITY MAP            (§1 · references/interview.md)
     DEEP, ONE QUESTION AT A TIME, driven by the coverage map.
     first: portal name + GOAL -> <slug> + north star.
     then capability-first until every coverage dimension is closed.
     gap-free: every unknown becomes a confirmed assumption, never a TBD,
     because there is no per-slice loop downstream to catch a missed gap.
     -> docs/portals/<slug>/capability-map.md
        |
        v
[2] PACKAGE DECISIONS                       (§2)
     per surface: package-now / package-ready / workspace-only
     -> `php artisan mortelos:package-decision ...` if available,
        else append to .mortelos/package-decisions.md
        |
        v
[3] SET UP THE FOUNDATION                   (§7, §9 · references/foundation-wiring.md)
     route bridge · layout delegation · config/starter.php (auth contracts +
     navigation/search/governance/users/onboarding resolvers) ·
     deny-by-default policy scaffold · document host-owned tenant identity
     TENANCY FORK (from the interview): one customer -> nothing (single-tenant
     default); many customers in one deployment -> run the `add-tenancy` skill
     (database | row) before planning, so call-sites bind onto its seam.
     -> verify the app boots login -> dashboard.
        the base is assumed sound; only fill gaps. A non-booting base is the
        one hard stop before the plan gate; otherwise carry the status forward.
        |
        v
[4] COMPLETE BUILD PLAN                      (§3–§11 · references/build-plan-template.md)
     domain model · projections · connectors · widgets/surfaces · policies ·
     workflows/inbox · observability · tests/release · the FULL build order of
     every capability + the portal GOAL as north star. No TBDs.
     -> docs/portals/<slug>/build-plan.md  +  progress.md
        |
        v
[5] PLAN APPROVAL GATE  (the single review gate)
     present: foundation status (boots) + the complete plan + the build order.
     STOP and get an explicit go-ahead. This is the one place the user directs.
     adjust the plan on feedback; re-present; do not start building without "go".
        |
        v
[6] FULL BUILD, ONE PASS                     (references/build-loop.md)
     build EVERY capability end to end, in the planned dependency order:
     entity -> projection -> policy(deny-default) -> widget/surface ->
     inbox flow (if needed) -> connector (if needed) -> observability ->
     full tests in the host framework. Do NOT stop between capabilities.
     IMPLEMENT via TALL skills when available; otherwise headless fallback.
     log deviations as you go; surface them all at handoff, never mid-build.
        |
        v
[7] HANDOFF
     verify: all tests green, app still boots, deny-by-default holds.
     write docs/portals/<slug>/handoff.md (built · deferred/fast-follows ·
     how to extend · where things live). tick progress.md. hand to the partner.
```

Create one todo per phase so progress is visible, then work the phases in order.
You may resume mid-way (see phase [0]); you do not always start at [1].

## Phase [0]: Pre-flight

Before asking anything, read the host context so you neither clobber existing
work nor re-ask answered questions.

- **Starter wiring check.** Look for the route bridge required from
  `routes/web.php`, a host `config/starter.php` that fills `auth.controllers.*`,
  and a layout that delegates to `mortelos-starter::layouts.app`. See
  `references/foundation-wiring.md`.
- **Package decisions check.** Read `.mortelos/package-decisions.md` if present
  and note recorded decisions.
- **Resume detection (hard rule).** If `docs/portals/<slug>/progress.md` exists
  with at least one ticked `[x]` item, you are in **RESUME mode**. Default action:
  print a status block (foundation status, completed capabilities, the next
  capability from the build order, any prior-run deviations or fast-follows),
  append a one-line entry `- <YYYY-MM-DD>, resume detected by setup-portal` to
  `progress.md`'s Log, and ask the user how to proceed before any further action.
  Do NOT re-run the phase [1] interview. Do NOT resume the build without an
  explicit go-ahead.
- **Test framework detection.** Check for `vendor/bin/pest` versus
  `vendor/bin/phpunit`. Whichever is present is the host's framework; the build
  plan §11 and the tests in phase [6] follow that, not a hardcoded assumption.
- **Stack detection.** Confirm this is a MortelOS host app (Laravel + Livewire 4
  + `mortelos/starter`). If TALL helper skills are present, you will use them in
  phase [6]. If not, say so and use the headless fallback in
  `references/build-loop.md`.

State plainly what you found and which entry point you are taking (fresh
bootstrap or resume) before proceeding.

## Phase [1]: Interview to capability map

This is where the skill makes or breaks the portal. **Read `references/interview.md`
and follow it exactly.** Style: deep, one question at a time, conversational,
driven by a coverage map so it is both thorough and gap-free.

- Start capability-first ("what should each role be able to do?"), never
  page-first.
- First question always establishes the portal **name** (which becomes `<slug>`,
  kebab-case) and its **goal** (the north star that later drives the build order).
- Track which coverage dimensions are still open; ask one sharp question for the
  most relevant open one; listen for answers that open new sub-dimensions.
- If the request spans several independent subsystems, stop and decompose before
  refining details. Pick the first portal; give the rest their own kickoffs.
- Establish the **tenancy mode** early: does this portal serve one customer, or
  several customers from one deployment? The default is single-tenant; "many
  customers / organisations / clients in one install" routes to the `add-tenancy`
  skill in phase [3]. Capture it as a coverage answer, it drives the foundation.
- Record unknowns as explicit assumptions; confirm them before planning. The bar
  is higher here than in a phased build: there is no per-slice loop downstream to
  catch a gap, so the map must be complete enough to plan and build the whole
  portal from in one pass.
- When every dimension is answered or a confirmed assumption, write
  `docs/portals/<slug>/capability-map.md` from `references/capability-map.md` and
  have the user confirm it.

## Phase [2]: Package decisions

For every surface the portal introduces, decide the package boundary
(`package-now` / `package-ready` / `workspace-only`) per §2. Prefer the artisan
command when the host has the MortelOS dev tools:

```bash
php artisan mortelos:package-decision "<Surface>" --decision=<...> \
  --surface=<...> --reason="<...>" --no-interaction
php artisan mortelos:package-decisions:check --require-reason --no-interaction
```

If the command is absent, append the same decision (surface, decision, reason) to
`.mortelos/package-decisions.md`. Package-first governance is mandatory; record a
decision for each reusable addition.

## Phase [3]: Set up the foundation

Wire the Starter contracts correctly so the app boots and every later capability
plugs into governed primitives, not one-off page code. **Follow
`references/foundation-wiring.md`**; it is the checklist derived from the README
contract tables plus §7 (deny-by-default policies) and §9 (tenant identity).

Assume the base is sound: in a project from `mortelos/starter` most of this is
pre-wired. Your job is to verify and fill gaps, not to rebuild. The required-to-
boot part is the five `auth` contracts; everything else is optional and degrades
silently. The starter is **single-tenant by default**; role/policy models are
host-owned, you seed safe deny-by-default defaults rather than invent them.

**Tenancy fork.** Act on the single-vs-multi-customer answer from phase [1]:

- **One customer (default):** do nothing. The starter is already single-tenant;
  the dashboard/governance gates run through the host policy seam.
- **Many customers in one deployment:** run the **`add-tenancy`** skill here,
  before the build plan, so every later capability binds onto its seam rather
  than being retrofitted. add-tenancy picks one isolation driver
  (`database` default, `row` alternative), wires identification + the tenant
  switcher, and generates isolation tests. It is opt-in on purpose: do not run
  it for a single-customer portal. Note add-tenancy's own one-way door (the
  `row` driver forecloses framework operate-mode) when you record the decision.

End the phase by verifying the app boots `login → dashboard` (single-tenant) or,
after add-tenancy, that its generated isolation test is green. A non-booting
base is the **one hard stop** before the plan gate: report the blocker and fix
the foundation before continuing. If it boots, carry that status into phase [5]
rather than asking for a separate approval here.

## Phase [4]: Complete build plan

Write `docs/portals/<slug>/build-plan.md` from `references/build-plan-template.md`,
covering §3–§11: domain model, projections, connectors, widgets/surfaces,
policies, workflows/inbox, observability, tests/release. Order **every**
capability by value and dependency into a single build order, and put the portal
**goal** at the top as the north star. Also create
`docs/portals/<slug>/progress.md`; that file is the checklist the build pass
updates and the anchor that tells the build when the goal is reached.

The plan is standalone and **complete**: self-contained, no GSD dependency, no
`TBD`s. Because the build runs in one pass with no per-slice review, a gap in the
plan becomes a gap in the portal. Sections that genuinely do not apply (for
example, no connectors in v1) get an explicit `N/A, reason: <…>` marker; see
`references/build-plan-template.md`. Use `references/primitives.md` to map each
customer concept to the right MortelOS primitive.

## Phase [5]: Plan approval gate

This is the **single review gate** and the one place the user directs the build.
The capability-map confirmation in phase [1] is not a second gate: that one
checks "did I understand what you want?" mid-interview; this one authorizes the
whole build. If the map was just confirmed, keep this gate light, the user is
approving the plan and the build order, not re-litigating the requirements.
Present, in a compact form:

1. **Foundation status** from phase [3]: the app boots `login → dashboard`, deny-by-default scaffold seeded, tenant identity documented.
2. **The complete plan**: a short summary of the capability map and build plan
   (entities, surfaces, policies, connectors/inbox if any, test targets).
3. **The build order**: the full ordered list of capabilities that will be built
   in phase [6], so the user sees exactly what "build everything" means.

Then **stop and get an explicit go-ahead.** On feedback, adjust `build-plan.md`
(and `capability-map.md` if scope changes), re-present, and confirm before
building. Do not start phase [6] without a clear "go". This single gate replaces
the old per-slice checkpoints: the user approves the whole plan here, once.

## Phase [6]: Full build, one pass

Build the **entire** portal end to end in the planned dependency order. **Follow
`references/build-loop.md`.** Each capability is built fully (entity → events/
projection → deny-by-default policy → surface → inbox flow if needed → connector
if needed → observability → full tests) before moving to the next, but you do
**not** stop for review between capabilities, that is what the plan gate was for.

When the TALL skills are available, implementation goes through them: `tall-model`
for the model plus migration plus factory, `tall-feature` for the TDD red-green-
refactor of each capability, `tall-test` for coverage. When they are not callable
(headless runs, missing plugin), use the headless fallback recipe in
`references/build-loop.md`. This skill owns the MortelOS-specific shape (entity/
link/event, projection, deny-by-default policy, surface registration, inbox flow,
observability).

Tests are part of the build, not an afterthought: every capability gets full
coverage in the host framework (deny-by-default enforcement, happy-path action
flow, projection rebuild, tenant isolation, and connector failure/approval flows
where present). Keep `progress.md` ticking as capabilities complete so an
interrupted run can resume. If reality diverges from the plan, **log the
deviation and keep building**; surface every deviation at the handoff, do not
reintroduce a per-capability stop.

## Phase [7]: Handoff

When the build is complete, verify and hand off. The portal is "done" for this
kickoff when every capability the goal requires is built and green; from here the
partner continues development.

1. **Verify.** Run the full test suite (evidence before claims): all green. The
   app still boots `login → dashboard`. Deny-by-default holds.
2. **Write `docs/portals/<slug>/handoff.md`** from
   `references/handoff-template.md` so the partner can continue without re-reading
   the whole history: what was built (capabilities, entities, surfaces, policies,
   tests + result), what was deliberately deferred (fast-follows, with reasons),
   how to add the next capability (the build-loop recipe + where the patterns
   live), and where things live (namespaces, routes, config keys, test paths).
3. **Update `progress.md`**: tick every built capability, append a final log
   entry, and note any deviations from `build-plan.md`.
4. **Hand off.** Summarize in a short list (not a walkthrough) what was built and
   what the partner should pick up first. Stop.

## Key principles

- **Capability-first, not page-first.** Start from what a role can do; assemble
  from governed primitives (handbook intro).
- **Complete plan or it fails.** The build runs in one pass with no per-slice
  loop, so the plan must be gap-free before the gate. Record unknowns as confirmed
  assumptions during the interview; never leave a `TBD` in the plan.
- **One plan gate, then build all.** The user approves the complete plan once
  (phase [5]); after that you build the whole portal in a single pass and hand
  off. No per-capability review stops.
- **Deny by default.** Every mutating action and sensitive surface gets a policy
  ability; seed safe defaults during onboarding (§7).
- **Tenant identity is host-owned.** Document it, seed policies, never invent a
  membership model in the portal (§9).
- **Observability is part of the feature.** If an operator cannot answer "why did
  this appear, disappear, or fail?", the capability is not done (§10).
- **Hand off, do not abandon.** When the build is green, leave the partner a short
  handoff doc: what was built, what was deferred and why, how to add the next
  capability.
- **Idempotent and resumable.** Detect existing work in phase [0]; resume the
  build pass from `progress.md`, do not clobber.
- **MCP is runtime, not build-time.** The MortelOS MCP operates the running
  workspace; portal bootstrap is artisan plus code and config edits.
