# Claude Code instructions — mortelos/starter

The single source of truth for all agents (Claude, Codex, Cursor, Windsurf,
generic LLM) is [`AGENTS.md`](AGENTS.md). Read it first. This file only adds
Claude-specific setup that other agents don't need.

## What this repo is

`mortelos/starter` is a **Laravel application template** for AI-driven portal
builds on the TALL stack. New portals start with:

```bash
composer create-project mortelos/starter mijn-portal
cd mijn-portal
```

…which yields a working Laravel app with the MortelOS shell already wired:
login, tenant select, dashboard, inbox, governance, users, settings, plus
seeded admin account. From there an AI agent assembles portal capabilities on
top.

## When building a portal

Run the `portal-kickoff` skill on a new portal request. It owns phases 0–6 of
the MortelOS portal workflow (pre-flight, interview, package decisions,
foundation review, build plan, vertical-slice loop, checkpoint).

The skill lives at `.claude/skills/portal-kickoff/`. Because this repo is the
host app (no `vendor/mortelos/starter` indirection anymore), the skill is
already in the right place — no symlink needed.

After that the skill triggers automatically on phrases like "build a customer
portal", "customers should be able to upload documents", "set up a workspace".
See its `description` frontmatter for the full trigger list.

## TALL stack helpers

If the `uteq-tall-master` plugin is installed (commonly is):

- `tall-model` — scaffold a model + migration + factory
- `tall-feature` — TDD red-green-refactor of a capability
- `tall-test` — write Pest tests for existing code
- `tall-page` — scaffold a Livewire 4 SFC page

Use them from inside phase [5] of the `portal-kickoff` workflow. Fall back to
the headless recipe in `.claude/skills/portal-kickoff/references/build-loop.md`
when these skills aren't callable.

## Communication conventions (project-wide)

These come from the user profile and apply across all repos:

- Reply in **Dutch**; code, commits, PR descriptions and tech specs stay in **English**
- Terse and direct; skip explanations unless asked "why"
- No em-dashes (`—`) in Dutch prose — use commas, semicolons or full sentences
- ASCII visualisations for UI layouts and complex workflows when useful
- Always cite code with `path/to/file:line` so the user can jump
