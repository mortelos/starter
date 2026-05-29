# Claude Code instructions — mortelos/starter

The single source of truth for all agents (including Claude) is
[`AGENTS.md`](AGENTS.md). Read it first. This file only adds Claude-specific
setup that other agents don't need.

## When working in a host app

Before doing anything portal-related, run the `portal-kickoff` skill. It owns
phases 0–6 of the MortelOS portal workflow (pre-flight, interview, package
decisions, foundation wiring, build plan, vertical-slice loop, checkpoint).

The skill lives in this package at `.claude/skills/portal-kickoff/`. Host apps
symlink it in once:

```bash
mkdir -p .claude/skills
ln -s vendor/mortelos/starter/.claude/skills/portal-kickoff .claude/skills/portal-kickoff
```

After that the skill triggers automatically on phrases like "build a customer
portal", "customers should be able to upload documents", "set up a workspace".
See its `description` frontmatter for the full trigger list.

## TALL stack helpers

If the user has the `uteq-tall-master` plugin installed (commonly does):

- `tall-model` — scaffold a model + migration + factory
- `tall-feature` — TDD red-green-refactor of a capability
- `tall-test` — write Pest tests for existing code
- `tall-page` — scaffold a Livewire 4 SFC page

Use them from inside phase [5] of the `portal-kickoff` workflow. Fall back to
the headless recipe in `.claude/skills/portal-kickoff/references/build-loop.md`
when these skills aren't callable.

## Edit workflow when consumed via symlink

This package is symlinked into host apps as `vendor/mortelos/starter`. When you
edit here:

1. Edits land in the host app via the symlink (no host commit needed)
2. Commit **separately** in this repository
3. After service-provider or config changes, run
   `composer update mortelos/starter` in the host app
4. Run `composer validate --strict` and `vendor/bin/pest` before pushing

## Communication conventions (project-wide)

These come from the user profile and apply across all repos:

- Reply in **Dutch**; code, commits, PR descriptions and tech specs stay in **English**
- Terse and direct; skip explanations unless asked "why"
- No em-dashes (`—`) in Dutch prose — use commas, semicolons or full sentences
- ASCII visualisations for UI layouts and complex workflows when useful
- Always cite code with `path/to/file:line` so the user can jump
