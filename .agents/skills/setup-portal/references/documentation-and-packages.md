# Documentation and Package Intelligence

Phase [0]. Load this before the interview and reuse it in phases [2], [4], and
[6]. The goal is to stop the agent from inventing custom host code when the
answer already exists in MortelOS docs, installed packages, or sibling package
worktrees.

## Read order

1. `README.md`, for the public contract tables, boot promise, baseline routes,
   seeded account, and troubleshooting entry points.
2. `docs/building-portals.md`, the canonical build method. Its §1-§11 structure
   governs the capability map, package decisions, primitives, policies,
   workflows, observability, tests, and release.
3. `knowledge/README.md`, then the topic note that matches the current phase:

| File | Read when |
| --- | --- |
| `knowledge/01-quickstart.md` | First touch or host boot context |
| `knowledge/02-primitives.md` | Mapping concepts to entities, links, events, projections, policies, surfaces |
| `knowledge/03-tall-conventions.md` | Writing Livewire 4 SFC, Blade, Flux UI, Pest, or action classes |
| `knowledge/04-package-governance.md` | Deciding `package-now`, `package-ready`, or `workspace-only` |
| `knowledge/05-mortelos-ecosystem.md` | Choosing an existing MortelOS package or known package boundary |
| `knowledge/06-mcp-runtime.md` | Anything involving MCP, agent tools, OAuth, runtime access |
| `knowledge/07-test-and-verify.md` | Before claiming the build works |
| `knowledge/08-troubleshooting.md` | Boot, route, asset, package install, or test failures |

4. `docs/host-app-anatomy.md`, when deciding where host-owned code, resolvers,
   package configs, routes, tests, or portal docs should live.
5. `docs/init-host-app.md`, when validating a fresh create-project baseline.
6. `docs/specs/*.md`, only when the topic matches. These are design/history docs,
   not always current implementation contracts. Useful current topics include
   starter as operate node, tenancy stripping, and add-tenancy decisions.

## Detect available packages

Run these checks from the host app before deciding "custom":

```bash
composer show 'mortelos/*' 2>/dev/null || true
find vendor/mortelos -maxdepth 2 -type f \( -name composer.json -o -name README.md -o -name AGENTS.md \) 2>/dev/null | sort
find .. -maxdepth 3 -type f \( -path '*/mortelos-*/composer.json' -o -path '*/channel-*/composer.json' -o -path '*/widget-*/composer.json' \) | sort
```

Read only what matches the portal capability:

- `composer.json`, for package name, description, providers, dependencies.
- `README.md`, `docs/`, `AGENTS.md`, or `CLAUDE.md`, if present.
- `src/Contracts`, `src/*ServiceProvider.php`, `config/`, `routes/`,
  `resources/views`, `database/migrations`, and `tests/`, when you need concrete
  extension points or examples.

Treat `vendor/mortelos/*` and sibling `mortelos/*`, `channel-*`, and `widget-*`
repositories as read-only unless the explicit task is a package PR. For portal
work, inspect packages for contracts and extension points, then wire host-side
through config, resolvers, actions, projections, or package APIs.

## Known package map

Start with what is installed in the host, then use sibling package worktrees only
as available examples or candidate package dependencies.

| Package | Prefer for |
| --- | --- |
| `mortelos/framework` | Entity registry, entity links, events, event sourcing, projections, tenant primitives, MCP runtime, Laravel AI integration |
| `mortelos/starter` | App shell, auth route contracts, dashboard, inbox, governance, users, settings, starter pages, shell slots |
| `mortelos/ui` | Shared Flux-aligned UI primitives and design components |
| `mortelos/dev-tools` | Package decision commands, package governance checks, scaffolding helpers |
| `mortelos/entity-graph` | Entity graph traversal, API routes, visualisation, graph chat widget, agent tool |
| `mortelos/policy-studio` | Governance UI, proposal queue, trust config, policy proposal and approval flows |
| `mortelos/document-studio` | Document review pipelines |
| `mortelos/chat` | Chat shell and `WidgetRegistry` for chat widgets |
| `mortelos/overviews` | Flexible overview data sources, saved overviews, overview chat widget and context integration |
| `mortelos/channel-moneybird` | Moneybird contact, invoice, finance sync and channel health |
| `mortelos/channel-gmail` | Gmail channel driver |
| `mortelos/channel-google-drive` | Google Drive file, attachment, and briefing push/sync |
| `mortelos/channel-fireflies` | Fireflies transcript ingest and inbound webhook |
| `mortelos/channel-plaud` | Plaud channel driver |
| `mortelos/channel-telegram` | Telegram bot channel driver |
| `mortelos/widget-compliance` | Compliance chat widgets |
| `mortelos/widget-document-feedback` | Document feedback chat widget |

If a local package exists but is not installed in `composer.json`, record whether
to add it as a dependency, use it only as a reference, or keep the new capability
package-ready. Do not silently copy package code into the host.

## Existing-solution decision rule

For each capability and surface, decide in this order:

1. **Use installed package:** the package already owns the route, widget,
   connector, policy flow, projection, or primitive.
2. **Extend package through its seam:** config key, resolver, service provider,
   widget registry, contract, policy ability, event listener, or package API.
3. **Add a package dependency:** a sibling or known package exists and matches
   the capability better than custom host code.
4. **Build `package-ready`:** no package owns it yet, but the capability is
   reusable. Keep boundaries explicit and record the future package name.
5. **Build `workspace-only`:** only for concrete customer-specific behavior:
   branding, bespoke legal process, one-off data model, or tenant policy.

Write the chosen package fit into `capability-map.md` and `build-plan.md`. Every
custom implementation needs a short note saying which packages were checked and
why none owns the capability.

## Do not use

- Filament, Jetstream, Breeze, Inertia, Vue, or React for new portal surfaces.
- Class-based Livewire v3 patterns for new components.
- Direct API calls in Livewire or Blade. Use connectors, actions, projections, or
  package services.
- Component-only access checks for role or tenant access. Use policies and the
  central access resolver.
