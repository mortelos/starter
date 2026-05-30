# portal-kickoff Skill, Evaluation Report

Date: 2026-05-28
Skill under test: `/Users/uteq/Sites/mortelos-starter/.claude/skills/portal-kickoff` at commit `ce21a34`.

## TL;DR

| Eval | Result | Method |
|---|---|---|
| Trigger-evals (sonnet-4-6, 20 queries, 3 runs each) | **18/20 (90%)** | Custom runner in cwd `mortelos-starter`, real skill discovery |
| Scenario 01, klantdossier (full build) | **14/14 PHPUnit tests groen, 50 assertions, 13/13 dimensions closed, 0 TBDs** | Subagent in worktree `uteqos-eval-pilot` |
| Scenario 02, klantfinancien + Moneybird (full build) | **15/15 PHPUnit tests groen, 56 assertions, 13/13 dimensions closed, 0 TBDs** | Subagent in worktree `uteqos-eval-02` |
| Scenario 03, resume bestaand portal | **RESUME geboden, geen herinterview, geen ongevraagd bouwwerk** | Subagent in worktree `uteqos-eval-pilot` |

The skill works end to end on three distinct scenarios. Triggering is solid. The interview is gap-free in practice. The build layer produces working, tested code with deny-by-default policies. Phase [0] idempotency works.

## 1. Trigger evaluation

### Harness note
Initial run with `skill-creator`'s `run_loop.py` produced a misleading 4/8 result. Diagnosis: that harness searches for `.claude/` walking up from cwd and creates a command-shim there. Run from the skill-creator directory it found `/Users/uteq/.claude/`, not the actual project, so the skill was never discoverable to the test `claude -p`. Uniform 0.0 across every should-trigger query plus zero false positives confirms a methodology artefact, not a description weakness. Replaced with a custom runner.

### Custom runner
- Each query: `claude -p "<query>"\n"Reply with ONLY the exact skill name (lowercase) you would invoke first, or 'none'."` with cwd `/Users/uteq/Sites/mortelos-starter`, `--model claude-sonnet-4-6`, 3 runs per query.
- Pass threshold: should-trigger requires majority (rate > 0.5), should-not-trigger requires minority (rate <= 0.33).
- Results at `.claude/skills/portal-kickoff-workspace/trigger-real/results.json`.

### Results, 18/20 (90%)
- **Should-trigger** (10 queries): 9 pass (7 at rate 1.00, 2 at rate 0.67). 1 fail.
- **Should-not-trigger** (10 queries): 9 pass at rate 0.00. 1 fail (false positive).

### Actionable findings
1. **False positive**: *"maak een nieuw Laravel project met Jetstream voor een klantenportaal"* triggers at rate 1.00. Sonnet matches on the word "klantenportaal" alone. Fix: add a negative-context line to the description that excludes non-MortelOS stacks (Jetstream, Filament, vanilla Laravel).
2. **Sampling noise** at 1 query: *"ik wil een klantportal opzetten op mortelos/starter, waar klanten hun dossiers zien en documenten uploaden, en account managers die reviewen en goedkeuren. waar begin ik?"* triggered at rate 0.33. Most likely sampling variance at 3 runs. Recommend re-running this single query at 10 runs to confirm.

## 2. Scenario 01, klantdossier (full build)

**Worktree:** `/Users/uteq/Sites/uteqos-eval-pilot`. Disposable git worktree of uteqos on branch `portal-eval-pilot`. sqlite-backed, vendor symlinked, skill symlinked.

**Subagent budget:** 26.5 min wall clock, 187k tokens, 156 tool uses.

**Interview quality.** One question at a time, capability-first, 13/13 dimensions closed, 3 explicit assumptions captured and confirmed, 0 TBDs.

**Foundation.** Auth contracts pre-filled by reference host UteqOS, confirmed (not re-edited). Six deny-by-default policy abilities seeded via Gate plus per-model Policy classes with the real decision logic. Tenant identity documented as host requirement in `foundation.md`.

**Build plan.** All sections §2 to §11 covered, 5-slice build order, 0 TBDs.

**First vertical slice.**
- 5 entities (Customer, Dossier, KlantdossierDocument, KlantdossierInboxItem, DossierSummary)
- 3 events (DocumentUploaded, DocumentApproved, DocumentRejected)
- 1 projection (DossierSummary, sync, rebuildable)
- 2 policies, 6 abilities
- 2 Livewire 4 SFC surfaces (`/dossier`, `/dossier-review/{itemId}`)
- Inbox flow wired via `inbox.item_type_resolver`
- 14 PHPUnit tests, 50 assertions, **14/14 groen** in 1.14s

**Honest deviations.**
- PHPUnit instead of Pest (host runs PHPUnit; skill had assumed Pest).
- Portal-owned inbox table (`klantdossier_inbox_items`) instead of the framework's `inbox_items`, because the latter is per-tenant in production multi-DB tenancy and not in the eval's central sqlite.
- `APP_BASE_PATH` env in `phpunit.xml` to make tests resolve the eval worktree (the vendor symlink resolves to real uteqos otherwise).
- Notification on approve/reject is stubbed (event emitted, channel not wired). Marked fast-follow.

## 3. Scenario 02, klantfinancien with Moneybird connector (full build)

**Worktree:** `/Users/uteq/Sites/uteqos-eval-02`. Same setup as pilot.

**Subagent budget:** 23.8 min wall clock, 175k tokens, 142 tool uses.

**Interview quality.** One question at a time, capability-first, 13/13 dimensions closed. Connector dimension §5 fully covered: auth (OAuth 2 auth-code, scopes, encrypted tokens), direction (read-only), frequency (on-demand + hourly + incremental updated_after + initial backfill), failure (5xx backoff plus 401/403 needs_reauth), classification (medium-high, AVG-relevant). Inbox dimension §8 correctly skipped except the single reauth-needed flow. Surfaces §6 landed right: starter page for customer plus finance, reusable widget for connector-health embedded in both `/finance` and `/governance`.

**Foundation.** Confirmed pre-wired; deny-by-default Gate abilities seeded: `klantfinancien.connector.moneybird.setup`, `.trigger_sync`, `.view_health`, `klantfinancien.revenue.view_analytics`, `klantfinancien.nav.finance.view`, plus `InvoicePolicy` view + download.

**Package decisions.** Two decisions recorded: `mortelos/connector-moneybird` as **package-now** (reusable connector), `KlantFinancien portal shell` as **workspace-only**.

**First vertical slice.**
- 7 entities (Customer, Invoice, SyncRun, RevenueProjection, ConnectorState, InboxItem, PdfDownloadAudit)
- 4 events (MoneybirdSyncStarted, MoneybirdSyncCompleted, MoneybirdSyncFailed, InvoiceUpserted)
- 1 projection (RevenueProjection per-customer + aggregate, async via InvoiceUpserted listener, rebuildable)
- 2 policies, ~7 abilities (deny-by-default, tenant-scoped)
- Connector boundary: `MoneybirdConnector` + `MoneybirdClient` contract + `FakeMoneybirdClient` (tests) + `HttpMoneybirdClient` stub (no real OAuth calls)
- 4 routes: `/financien`, `/financien/invoices/{invoice}/pdf`, `/finance/klantfinancien`, `POST /finance/klantfinancien/sync`
- Inbox reauth-flow wired via `CreateReauthInboxItem` listener (idempotent per SyncRun, only on 401/403)
- 15 PHPUnit tests, 56 assertions, **15/15 groen** in 0.89s

**Honest deviations.**
- Portal code lives under `app/Portals/Klantfinancien/` not a real composer package; package boundary declared, extraction deferred.
- Bootstrap autoloader workaround in `bootstrap/app.php` + `tests/bootstrap.php` for the vendor symlink (eval-env only).
- HTTP-level tests bypass tenancy middleware because stancl/tenancy has no sqlite driver; service/policy-layer tests cover the equivalents.
- RevenueProjection uses an 'AGG' string sentinel in `customer_id` instead of a nullable column (sqlite + mysql portability for unique-constraint semantics).

## 4. Scenario 03, resume existing portal

**Worktree:** `/Users/uteq/Sites/uteqos-eval-pilot` (same as scenario 01, post-build state).

**Subagent budget:** 1.5 min wall clock, 48k tokens, 12 tool uses.

**Pre-flight detection.** Correctly identified existing `docs/portals/klantdossier/`, read `progress.md`, summarized prior state (foundation done, slice 1 done with 14 tests green, slices 2 to 5 open), recognised the package-decisions entry at line 376.

**Resume behaviour.** Explicit "Entry point: RESUME, not a fresh bootstrap"; skipped phase [1] interview; named the next slice from the build plan (Slice 2: Customer view dossier + dossier-summary widget polish); surfaced both fast-follows from slice 1 (PHPUnit/Pest drift and notification stub); on the "niet nu bouwen" instruction respected the boundary and produced only a status summary without writing source code or migrations.

## 5. Convergent skill-level improvements (signal from all three runs)

Same gaps appeared in independent runs, so they are real.

1. **TALL fallback recipe.** Phases [5]+[6] tell the agent to invoke `tall-model`, `tall-feature`, `tall-test`. These are not callable headless. Both build runs fell back to manual scaffolding, which worked but cost time. **Fix:** add a short "headless fallback" section to `references/build-loop.md` with the manual recipe (model + migration + factory layout, Livewire 4 SFC path, action-class pattern, Pest stub layout).

2. **Test-framework detection in phase [0].** The skill silently assumes Pest. The reference host runs PHPUnit. Both build runs flagged this as a fast-follow. **Fix:** add a phase [0] check, "detect host test framework (look for `vendor/bin/pest` vs `vendor/bin/phpunit`), use it in §11 of the build plan".

3. **Vendor-symlink autoload caveat.** Eval-env specific, but useful documentation: when the package is symlinked as vendor, `Application::inferBasePath()` resolves to the real host dir, so tests need `APP_BASE_PATH` set. **Fix:** one-line note in `references/build-loop.md`'s "tests" section, only if running in a symlinked package layout.

4. **Phase [0] resume decision rule (from scenario 03).** SKILL.md mentions resume "in passing"; the workflow diagram shows phase [0] flowing into [1]. Make the rule explicit: "if `progress.md` exists with at least one [x], default to RESUME at the first [ ] item; print a status block; ask before any further action". Also: append a "resume detected" log line to `progress.md` as part of phase [0].

5. **Build-plan-template N/A markers.** Pilot 01 felt the template's connectors + inbox sections were half-empty for portals without those. **Fix:** in `references/build-plan-template.md`, prompt the user to mark a section as "N/A, reason: ..." instead of leaving it empty, so the doc reads honestly.

## 6. What was NOT done (out of scope)

- **No baseline-without-skill runs.** Advisor guidance was to validate the path first; baselines would triple the cost and were not part of the user's explicit "alle 3 gebouwd" scope. Recommend adding them as an optional follow-up if you want a hard "skill vs no-skill" delta.
- **Trigger-eval description optimization not applied.** The harness's automated rewrites failed to beat the original on the held-out test set (and were measured on the broken harness anyway). The custom runner shows the original is already at 90%; targeted manual edit for the Jetstream false positive is enough.

## 7. Recommended next steps (in order)

1. Apply the five convergent skill fixes from section 5.
2. Apply the targeted description tweak from section 1 (exclude non-MortelOS stacks).
3. Optional: re-run the custom trigger runner with 10 runs per query to retire the sampling-noise fail.
4. Optional: baseline-without-skill runs for a quantitative delta.
5. Cleanup: when finished reviewing, the two eval worktrees can be removed with `git -C /Users/uteq/Sites/uteqos worktree remove --force /Users/uteq/Sites/uteqos-eval-pilot` and the same for `uteqos-eval-02`, then `git -C /Users/uteq/Sites/uteqos branch -D portal-eval-pilot portal-eval-02`.

## 8. Artefact locations

- Eval workspace: `/Users/uteq/Sites/mortelos-starter/.claude/skills/portal-kickoff-workspace/`
  - `evals/trigger-eval.json`, `evals/evals.json`
  - `scenarios/01-klantdossier.md`, `02-klantfinancien.md`, `03-resume-klantdossier.md`
  - `trigger-real/results.json`, `trigger-real/run.log`
  - `trigger-opt/` (the broken harness output, kept for forensic reference)
  - this report: `EVAL-REPORT.md`
- Built portals: `/Users/uteq/Sites/uteqos-eval-pilot/docs/portals/klantdossier/` and `/Users/uteq/Sites/uteqos-eval-02/docs/portals/klantfinancien/`
