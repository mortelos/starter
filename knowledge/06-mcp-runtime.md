# 06 — MCP runtime (operate, not build)

MortelOS has **two AI surfaces**:

| Surface | What it does | Tools |
| --- | --- | --- |
| **Build mode** (this repo) | Coding agent assembles the host app from primitives | File edits, artisan, composer, the `portal-kickoff` skill |
| **Operate mode** (MortelOS MCP) | Runtime agent operates the live workspace: search entities, run skills, trigger workflows, approve proposals, launch sub-agents | OAuth-authenticated MCP tools served from the host |

**The MCP server is not used for portal bootstrap.** Bootstrap is artisan +
code + config edits. The MCP server kicks in once the workspace is live.

## Server location

The server is provided by `mortelos/framework` (not by `mortelos/starter`). The
host mounts the framework-provided server class:

```php
// routes/ai.php (in the host app)
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();
Mcp::web('/mcp/mortelos', config('mortelos.mcp.server'))
    ->middleware([
        'auth:api',          // Passport OAuth
        // tenancy init from the MCP token
        // role resolution
        // trust-level enforcement
        // data classification
        // throttling
    ]);
```

## What the MCP exposes (high level)

Tool families on the MortelOS MCP server:

- `entity-*` — search, get, create, update, link entities
- `entity-history` — read entity audit trail
- `governance-*` — list pending, approve, reject proposals
- `agent-*` — run, status, cancel agent tasks
- `skill-run` — invoke a published skill on the workspace
- `workflow-trigger` — kick off a registered workflow
- `site-context` / `site-conventions` / `site-register` — workspace-level metadata
- `ask` — open-ended question into the workspace knowledge

Exact tool names and shapes live in `mortelos/framework` source. When
implementing a new agent capability, check there first; if you need to expose a
new tool, that's a `mortelos/framework` PR, not a starter PR.

## Access model

- **OAuth 2.1** with dynamic client registration (`Mcp::oauthRoutes()`)
- **Tenant scoping** from the MCP token — an agent only sees its tenant
- **Role resolution** — what roles the calling agent's principal has
- **Trust level enforcement** — high-trust tools require higher trust than the
  default
- **Data classification** — sensitive data gates require explicit grants
- **Throttling** — protects the workspace from runaway agents

Same policies govern the MCP surface and the web shell. If a user can't see
something in the inbox via the UI, the agent can't read it via `entity-get`
either.

## What "build mode" must do to prepare for "operate mode"

When building a portal, you're also setting up what the runtime agent will see:

1. **Every mutating action gets a policy ability** so the MCP can honor it
2. **Events are emitted on writes** so the agent can subscribe and react
3. **Projections expose stable read surfaces** so agent tools have something to
   query without hitting raw tables
4. **Inbox flows have item types registered** so the agent can list, route,
   approve, reject
5. **Workflows are registered** so the agent can trigger them by name
6. **Connector data-request providers exist** so the agent can fetch external
   data without re-implementing the integration

If any of these is missing, the portal works for human users but the agent
surface is partial.

## What you do NOT do here

- Don't expose new MCP tools from inside `mortelos/starter` — starter is the
  shell, not the agent surface. New tools go in `mortelos/framework` or a feature
  package.
- Don't bypass OAuth in dev to "make testing easier" — use the documented
  Passport flow.
- Don't write agent tools that don't have a policy ability.

## When the user asks "can the agent do X at runtime?"

Read the installed `mortelos/framework` MCP source for the actual tool list.
Don't guess based on this file; the source is authoritative.
