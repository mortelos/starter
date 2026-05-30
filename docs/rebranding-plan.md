# MortelOS Rebranding Plan — `uteq/*` → `mortelos/*`

Status: **approved, Layer A in progress**. Pilot: `mortelos/dev-tools`.

## Decisions locked in

| Question | Decision |
| --- | --- |
| Mortel namespace stays `Mortel\`? | Yes. Composer name → `mortelos/framework`, PHP ns stays `Mortel\`. No source rewrites. |
| Plaud naming normalization? | Yes. `uteq/plaud-channel` → `mortelos/channel-plaud`. |
| Layer A or A + B? | **Layer A only.** No PHP namespace renames in this round. |
| Version bump per package? | **`0.3.0`** (minor) — semver-coherent under 0.x conventions. |
| Pilot first? | Yes — `mortelos/dev-tools`, then batch the rest. |

## Why

`mortelos/ui` and `mortelos/starter` already live under `github.com/mortelos`.
The remaining MortelOS-gemeengoed packages still live under `github.com/uteq`
with composer names `uteq/<…>`. Bringing them under one org makes the platform
coherent and matches the long-term naming target `mortelos/framework` recorded
in `knowledge/05-mortelos-ecosystem.md`.

## What's NOT in scope

- `uteq/os` (UteqOS host) — UTEQ's own MortelOS workspace, stays under `uteq/`
- `clawd-os`, `qlose`, `degoede-portal*`, `safesent*`, `wallable-print`,
  `laravel-feedback-hub` — either don't consume MortelOS packages or are out of
  the MortelOS scope
- PHP source-level domain refactors beyond the namespace swap

## Inventory

| # | Today (github) | Today (composer) | Today (php ns) | Target (github) | Target (composer) | Target (php ns) |
| - | --- | --- | --- | --- | --- | --- |
| # | Today (github) | Today (composer) | Target (github) | Target (composer) | PHP namespace |
| - | --- | --- | --- | --- | --- |
| 1 | `uteq/mortelos-dev-tools` | TBD | `mortelos/dev-tools` | `mortelos/dev-tools` | unchanged |
| 2 | `uteq/plaud-channel` | `uteq/plaud-channel` | `mortelos/channel-plaud` | `mortelos/channel-plaud` | unchanged |
| 3 | `uteq/channel-fireflies` | `uteq/channel-fireflies` | `mortelos/channel-fireflies` | `mortelos/channel-fireflies` | unchanged |
| 4 | `uteq/channel-google-drive` | `uteq/channel-google-drive` | `mortelos/channel-google-drive` | `mortelos/channel-google-drive` | unchanged |
| 5 | `uteq/channel-moneybird` | `uteq/channel-moneybird` | `mortelos/channel-moneybird` | `mortelos/channel-moneybird` | unchanged |
| 6 | `uteq/channel-telegram` | `uteq/channel-telegram` | `mortelos/channel-telegram` | `mortelos/channel-telegram` | unchanged |
| 7 | `uteq/channel-gmail` | `uteq/channel-gmail` | `mortelos/channel-gmail` | `mortelos/channel-gmail` | unchanged |
| 8 | `uteq/widget-compliance` | `uteq/widget-compliance` | `mortelos/widget-compliance` | `mortelos/widget-compliance` | unchanged |
| 9 | `uteq/widget-document-feedback` | `uteq/widget-document-feedback` | `mortelos/widget-document-feedback` | `mortelos/widget-document-feedback` | unchanged |
| 10 | `uteq/chat` | `uteq/chat` | `mortelos/chat` | `mortelos/chat` | `Uteq\Chat\` (unchanged for Layer A) |
| 11 | `uteq/mortel` | `uteq/mortel` | `mortelos/framework` | `mortelos/framework` | `Mortel\` (unchanged for Layer A) |

PHP namespaces stay as-is in Layer A. A separate Layer B pass can rename
`Uteq\Chat\` → `Mortelos\Chat\` and `Mortel\` → `Mortelos\Framework\` later.

## Two-layer approach

**Layer A — Coordination layer (low risk, can ship per package):**

- GitHub transfer / rename
- `composer.json` `name` change in the package itself
- Consumer `composer.json` dep name swap + `composer update`

This works without touching any PHP source. Old consumers keep working as long
as they pin the old name (Composer follows transfers by redirect for a while).

**Layer B — Namespace layer (breaking, single big migration):**

- Rename PHP namespace inside the package
- Update every `use Foo\Bar\…` import in every consumer
- Update FQCN strings in config files (service providers, contracts)
- Coordinate one combined release per package

Layer A and B can be done in **two passes**: ship Layer A first (everyone
unblocked, coherent naming externally), then do Layer B per package when
convenient.

## Recommended sequence

Risk-ordered, lowest blast-radius first:

| Pass | Package | Why |
| --- | --- | --- |
| 1 | `uteq/mortelos-dev-tools` → `mortelos/dev-tools` | Tooling; transient dep, breaking it is easy to spot |
| 2 | `uteq/plaud-channel` → `mortelos/channel-plaud` | Single consumer (uteqos-agent-console-agui), incl. naming normalization |
| 3 | `uteq/channel-fireflies` → `mortelos/channel-fireflies` | Channel, narrow surface |
| 4 | `uteq/channel-google-drive` | idem |
| 5 | `uteq/channel-moneybird` | idem |
| 6 | `uteq/channel-telegram` | idem |
| 7 | `uteq/channel-gmail` | idem |
| 8 | `uteq/widget-compliance` → `mortelos/widget-compliance` | Used by 3 hosts |
| 9 | `uteq/widget-document-feedback` | idem |
| 10 | `uteq/chat` → `mortelos/chat` | Larger surface, deeper integration |
| 11 | `uteq/mortel` → `mortelos/framework` | Most invasive; do last so all dependents are already on the new naming |

## Per-package execution steps (template)

For each package, in order:

1. **GitHub side (user)**
   - Settings → Danger Zone → Transfer ownership → new owner `mortelos`,
     new repo name (use the target column above)
2. **Package side (this agent)**
   - `git remote set-url origin https://github.com/mortelos/<new-name>.git`
   - Edit `composer.json` `name` field to the target
   - Edit `composer.json` `extra.laravel.providers` if the FQCN changed
   - (Layer B only) rename PHP namespace via composer + sed
   - Run package's own tests; commit; push; tag a new minor (`0.3.0`)
3. **Consumer side (this agent) — per host**
   - In `composer.json`: swap the dep name from `uteq/<…>` to `mortelos/<…>`
   - Update the vcs `repositories[].url` to the new GitHub URL
   - `composer update mortelos/<…>`
   - (Layer B only) update FQCN imports throughout `app/`, `config/`, `tests/`
   - Run host tests; commit; push

Consumers known today:

| Host | Path | Consumes |
| --- | --- | --- |
| UteqOS | `/Users/uteq/Sites/uteqos` | all 10 packages above |
| UteqOS Agent Console | `/Users/uteq/Sites/uteqos-agent-console-agui` | all 10 packages above |
| Sijperda-OS | `/Users/uteq/Sites/sijperda-os` | mortel, widget-compliance, widget-document-feedback |
| Mortelos Starter (this repo) | — | none (yet) |
| Clawd-OS, Qlose | — | none (already checked) |

## Open questions (need user input before kicking off)

1. **Mortel → `mortelos/framework` or `mortelos/mortel`?**
   The user has chosen `mortelos/framework`. Confirm the namespace also becomes
   `Mortelos\Framework\` (Layer B) so package name and namespace match. If yes,
   `Mortel\…` imports throughout three host apps will need a bulk rewrite.

2. **Plaud naming normalization.**
   Current name `uteq/plaud-channel` breaks the `channel-<x>` convention.
   Target rename to `mortelos/channel-plaud` aligns it. Confirm acceptable.

3. **`uteq/mortelos-dev-tools` current composer name.**
   Need to read the package's `composer.json` to know if it's already
   `mortelos/dev-tools` (just github rename) or `uteq/mortelos-dev-tools` (full
   rebrand). Will check during execution.

4. **Layer A only, or A + B in one go?**
   - **A only**: 11 transfers + 11 `composer.json` swaps + 3 host updates,
     ~half a day. No source changes anywhere.
   - **A + B**: same plus PHP namespace rewrites in packages **and** all
     consumer source. Multiple days of focused work, breaking changes per
     package release.

5. **Version bumps.**
   Each renamed package gets a fresh tag (e.g. `0.3.0`). Confirm semver
   strategy — `0.3.0` (minor, breaking by 0.x convention) vs `1.0.0` (mark
   the move to mortelos as v1).

6. **Side effects to surface in the user's other tools.**
   - `.claude/`, `.cursor/`, knowledge files in **every** consuming host may
     still reference `uteq/mortel` etc. Out of scope for this plan; flagged
     for a separate sweep.

## Rollback considerations

- GitHub transfers retain redirect URLs for a while, so old `git clone`s keep
  working until composer hits a 404 on tags.
- Composer doesn't auto-redirect package names — once a consumer is on
  `mortelos/<…>`, going back means another swap.
- For Layer B, a rollback means restoring the old namespace + reverting all
  consumer imports. Keep the per-package commit small enough to revert cleanly.

## Execution log

| Pkg | GitHub transfer | Package release | UteqOS | Agent Console | Sijperda-OS |
| --- | --- | --- | --- | --- | --- |
| `mortelos/dev-tools` | done | n/a (composer name was already correct) | `0745e8d` (bundled w/ WIP, branch `codex/flexible-ai-overviews`) | `ded9b41` (branch `codex/agent-console-agui`) | n/a |
| `mortelos/channel-plaud` | — | — | — | — | n/a |
| `mortelos/channel-fireflies` | — | — | — | — | n/a |
| `mortelos/channel-google-drive` | — | — | — | — | n/a |
| `mortelos/channel-moneybird` | — | — | — | — | n/a |
| `mortelos/channel-telegram` | — | — | — | — | n/a |
| `mortelos/channel-gmail` | — | — | — | — | n/a |
| `mortelos/widget-compliance` | — | — | — | — | — |
| `mortelos/widget-document-feedback` | — | — | — | — | — |
| `mortelos/chat` | — | — | — | — | n/a |
| `mortelos/framework` | — | — | — | — | — |

## Out-of-scope (separate sweeps later)

- Renaming `Uteq\…` symbols inside UteqOS app code itself
- The MCP server class `Mortel\MCP\Servers\UteqOSServer` — that's UteqOS-specific
  and stays
- Reference paths in `.claude/skills/*` / `knowledge/*` of UteqOS / Agent Console
  / Sijperda-OS / Mortelos Starter — handled in a knowledge sweep after the
  package renames settle
