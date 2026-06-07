---
name: setup-portal
description: Use this FIRST whenever someone wants to set up, start, bootstrap, or build a customer portal on MortelOS / mortelos-starter. This skill uses the public MortelOS documentation as the source of truth, interviews for the capability map, records package decisions, writes the build plan, gets approval, builds the portal and verifies the result.
---

# Setup Portal

Use this skill for new MortelOS portal, workspace or customer-extension kickoff work.

Do not use local copies of the MortelOS method. Read the public docs:

| Topic | URL |
| --- | --- |
| Agentic development | https://mortelos.nl/docs/0/agentic-development |
| Building portals | https://mortelos.nl/docs/0/building-portals |
| Host app anatomy | https://mortelos.nl/docs/0/host-app-anatomy |
| TALL conventions | https://mortelos.nl/docs/0/tall-conventions |
| Package governance | https://mortelos.nl/docs/0/package-governance |
| MCP runtime | https://mortelos.nl/docs/0/mcp-runtime |
| Troubleshooting | https://mortelos.nl/docs/0/troubleshooting |

Workflow:

1. Verify this is a MortelOS Starter host app.
2. Read the relevant public docs pages before changing files.
3. Interview capability-first before writing code.
4. Record package decisions before adding surfaces.
5. Write a complete build plan and get explicit approval.
6. Build host-side using existing MortelOS primitives and packages first.
7. Verify with doctor, Pest and Pint where applicable.
8. Hand off with what changed, what was deferred and how to continue.
