---
name: portal-kickoff
description: Use this FIRST whenever someone wants to set up, start, bootstrap, or build a customer portal on MortelOS / mortelos-starter — even when they only describe a capability ("customers should be able to upload documents", "account managers approve invoices", "show clients their dossiers") without saying the word "portal". This skill interviews for the capability map one question at a time, wires the Starter foundation correctly, records package decisions, produces a standalone build plan, and then builds the portal capability-by-capability (vertical-slice first, with a review checkpoint each time), leaning on the TALL skills for implementation. Trigger it for any new MortelOS portal, workspace, or customer-extension kickoff.
---

# Portal Kickoff

The first skill a user runs when building a customer portal on MortelOS. It turns
the design handbook in `docs/building-portals.md` into a guided, goal-directed
workflow: set the foundation primitives up the governed way, produce a complete
standalone build plan, then build the portal one vertical slice at a time.

**The operating model is director/reviewer.** You (the agent) do the wiring; the
user stays the director and the reviewer. Stop at every checkpoint. Never build
the whole portal in one autonomous pass — the user approves each slice.

`docs/building-portals.md` is the canonical method. This skill is the execution,
interview, and checkpoint layer on top of it. Read that document early and treat
its section numbers (§1–§11) as the source of truth; the references here only add
*how to ask*, *how to wire*, and *how to build*.

## Install in a host app (one-time)

This skill lives in `mortelos-starter/.claude/skills/portal-kickoff/`. Portal work
writes into the **host app** (`App\…`, the host override `config/starter.php`,
`routes/web.php`, host policies) — files outside the starter repo — so the skill
must run from the host-app working directory. Because the starter is installed as
`vendor/mortelos/starter`, symlink it into the host once:

```bash
mkdir -p .claude/skills
ln -s vendor/mortelos/starter/.claude/skills/portal-kickoff .claude/skills/portal-kickoff
```

## Workflow

```text
portal-kickoff  ("ik wil een klantportal voor ...")
        |
        v
[0] PRE-FLIGHT — host context & idempotency
     detect: is Starter wired? auth contracts filled? .mortelos/ present?
     existing portal plan under docs/portals/? -> fresh bootstrap OR resume
        |
        v
[1] INTERVIEW -> CAPABILITY MAP            (§1 · references/interview.md)
     DEEP, ONE QUESTION AT A TIME, driven by the coverage map.
     first: portal name + GOAL -> <slug> + north star.
     then capability-first until every coverage dimension is closed.
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
     -> CHECKPOINT: does the app boot login -> tenant-select -> dashboard?
        |
        v
[4] STANDALONE BUILD PLAN                   (§3–§11 · references/build-plan-template.md)
     domain model · projections · connectors · widgets/surfaces · policies ·
     workflows/inbox · observability · tests/release · prioritized capability
     order + the portal GOAL as north star
     -> docs/portals/<slug>/build-plan.md  +  progress.md
        |
        v
[5] BUILD THE FIRST VERTICAL SLICE          (references/build-loop.md)
     highest-value capability, end to end:
     entity -> projection -> policy(deny-default) -> widget/surface ->
     inbox flow (if needed) -> observability -> Pest tests
     IMPLEMENT via TALL skills: tall-model -> tall-feature (TDD) -> tall-test
     -> CHECKPOINT: review with the user, update progress.md
        |
        v
[6] CAPABILITY LOOP toward the GOAL
     repeat [5] per capability; checkpoint each time;
     stop when progress.md == the portal goal is reached
```

Create one todo per phase so progress is visible, then work the phases in order.
You may resume mid-way (see phase [0]); you do not always start at [1].

## Phase [0] — Pre-flight

Before asking anything, read the host context so you neither clobber existing
work nor re-ask answered questions.

- Is Starter wired? Look for the route bridge required from `routes/web.php`, a
  host `config/starter.php` that fills `auth.controllers.*`, and a layout that
  delegates to `mortelos-starter::layouts.app`. See `references/foundation-wiring.md`.
- Does `.mortelos/package-decisions.md` exist? Read recorded decisions.
- Is there an existing portal under `docs/portals/<slug>/`? If so, read its
  `progress.md` and offer to **resume** at the first open item instead of
  restarting.
- Detect the stack: confirm this is a MortelOS host app (Laravel + Livewire 4 +
  `mortelos/starter`). If `uteq-tall-master` skills are present, you will use them
  in phase [5]; if not, say so and fall back to building by `tall-conventions`
  principles manually.

State plainly what you found and which entry point you are taking (fresh vs
resume) before proceeding.

## Phase [1] — Interview → capability map

This is where the skill makes or breaks the portal. **Read `references/interview.md`
and follow it exactly.** Style: deep, one question at a time, conversational,
driven by a coverage map so it is both thorough and gap-free.

- Start capability-first ("what should each role be able to do?"), never
  page-first.
- First question always establishes the portal **name** (→ `<slug>`, kebab-case)
  and its **goal** (the north star that later drives the build loop).
- Track which coverage dimensions are still open; ask one sharp question for the
  most relevant open one; listen for answers that open new sub-dimensions.
- If the request spans several independent subsystems, stop and decompose before
  refining details — pick the first portal, give the rest their own kickoffs.
- Record unknowns as explicit assumptions; confirm them before planning.
- When every dimension is answered or a confirmed assumption, write
  `docs/portals/<slug>/capability-map.md` from `references/capability-map.md` and
  have the user confirm it.

## Phase [2] — Package decisions

For every surface the portal introduces, decide the package boundary
(`package-now` / `package-ready` / `workspace-only`) per §2. Prefer the artisan
command when the host has the MortelOS dev tools:

```bash
php artisan mortelos:package-decision "<Surface>" --decision=<...> \
  --surface=<...> --reason="<...>" --no-interaction
php artisan mortelos:package-decisions:check --require-reason --no-interaction
```

If the command is absent, append the same decision (surface, decision, reason) to
`.mortelos/package-decisions.md`. Package-first governance is mandatory — record a
decision for each reusable addition.

## Phase [3] — Set up the foundation

Wire the Starter contracts correctly so the app boots and every later capability
plugs into governed primitives, not one-off page code. **Follow
`references/foundation-wiring.md`** — it is the checklist derived from the README
contract tables plus §7 (deny-by-default policies) and §9 (tenant identity).

The required-to-boot part is the five `auth` contracts; everything else is
optional and degrades silently. Tenant/membership/roles are **host-owned**: you
document them as a requirement and seed safe policy defaults, you do not invent a
tenant model. End the phase at a checkpoint: confirm `login → tenant-select →
dashboard` works.

## Phase [4] — Standalone build plan

Write `docs/portals/<slug>/build-plan.md` from `references/build-plan-template.md`,
covering §3–§11: domain model, projections, connectors, widgets/surfaces,
policies, workflows/inbox, observability, tests/release. Order capabilities by
value and dependency, and put the portal **goal** at the top as the north star.
Also create `docs/portals/<slug>/progress.md` — the checklist the build loop
updates and the anchor that tells the loop when the goal is reached.

The plan is standalone: self-contained, no GSD dependency, no `TBD`s. Use
`references/primitives.md` to map each customer concept to the right MortelOS
primitive.

## Phase [5] — Build the first vertical slice

Build the highest-value (often most foundational) capability end to end so there
is proof the portal works, then checkpoint. **Follow `references/build-loop.md`.**
Implementation goes through the TALL skills — `tall-model` for the model +
migration + factory, `tall-feature` for the TDD red-green-refactor of the
capability, `tall-test` for coverage — while this skill owns the MortelOS-specific
shape (entity/link/event, projection, deny-by-default policy, surface
registration, inbox flow, observability). Update `progress.md` and stop for
review.

## Phase [6] — Capability loop toward the goal

Repeat phase [5] for each remaining capability in the planned order, checkpointing
after each. The portal goal in `progress.md` is the stop condition: when every
capability that the goal requires is built, tested, and reviewed, the portal is
done. Do not run ahead of the user's review.

## Key principles

- **Capability-first, not page-first.** Start from what a role can do; assemble
  from governed primitives. (§ handbook intro)
- **Deny by default.** Every mutating action and sensitive surface gets a policy
  ability; seed safe defaults during onboarding. (§7)
- **Tenant identity is host-owned.** Document it, seed policies, never invent a
  membership model in the portal. (§9)
- **Observability is part of the feature.** If an operator cannot answer "why did
  this appear, disappear, or fail?", the capability is not done. (§10)
- **Idempotent and resumable.** Detect existing work in phase [0]; resume, do not
  clobber.
- **Director/reviewer.** Checkpoint at the foundation and after every slice. The
  agent wires; the user approves.
- **MCP is runtime, not build-time.** The UteqOS MCP operates the running
  workspace; portal bootstrap is artisan + code/config edits.
