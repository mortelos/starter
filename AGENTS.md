# Agent Instructions

This repository is `mortelos/starter`, a runnable Laravel host application template.

The public MortelOS documentation is the source of truth. Do not maintain local copies of the method, package reference or troubleshooting docs in this repository.

Read these pages before portal work:

| Topic | URL |
| --- | --- |
| Installation | https://mortelos.nl/docs/0/installation |
| Agentic development | https://mortelos.nl/docs/0/agentic-development |
| Building portals | https://mortelos.nl/docs/0/building-portals |
| Host app anatomy | https://mortelos.nl/docs/0/host-app-anatomy |
| TALL conventions | https://mortelos.nl/docs/0/tall-conventions |
| Package governance | https://mortelos.nl/docs/0/package-governance |
| MCP runtime | https://mortelos.nl/docs/0/mcp-runtime |
| Troubleshooting | https://mortelos.nl/docs/0/troubleshooting |

## Local Rules

1. Use the `setup-portal` skill for new MortelOS portal requests when available.
2. Keep portal work host-side unless the task is explicitly a package PR.
3. Do not patch `vendor/mortelos/*` or sibling `mortelos/*` package worktrees from a starter task.
4. Keep domain rules out of Blade and Livewire components.
5. Record package decisions before adding new surfaces.
6. Verify host behavior with `php artisan starter:doctor`, `vendor/bin/pest` and `vendor/bin/pint --dirty` when applicable.
7. Do not use em-dashes in Dutch user-facing prose.

For current contracts, routes, install steps and troubleshooting, use the documentation site instead of this repository.
