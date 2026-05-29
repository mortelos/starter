# 04 — Package-first governance

Every new feature, integration, frontend surface, backend service, or reusable
workflow **starts with a package decision** before implementation. This is the
core MortelOS rule that keeps the platform composable.

## The three decisions

| Decision | Use when |
| --- | --- |
| `package-now` | The capability can serve another MortelOS installation today. Build directly in a package. |
| `package-ready` | The host needs local wiring first, but the package boundary is explicit. Extract when stable. |
| `workspace-only` | The behavior is customer-specific by design (branding, policy, tenant config). |

**Default assumption**: if it can serve another MortelOS installation, prefer
`package-now` or `package-ready`. Pick `workspace-only` only with a concrete
reason.

## How to record

### When the host has MortelOS dev tools

```bash
php artisan mortelos:package-decision "Customer Portal" \
  --decision=package-ready \
  --surface=mortelos/customer-portal \
  --reason="Reusable shell with customer-specific tenant policy and branding." \
  --no-interaction

php artisan mortelos:package-decisions:check --require-reason --no-interaction
```

CI fails the PR if `composer package-governance` fails.

### When the host does not yet have dev tools

Append to `.mortelos/package-decisions.md` directly:

```markdown
## Customer Portal

- Surface: `mortelos/customer-portal`
- Decision: `package-ready`
- Reason: Reusable shell with customer-specific tenant policy and branding.
- Date: 2026-05-28
```

## What goes in a package

When you do build in a package, expect:

- `composer.json` with Laravel provider auto-discovery
- A service provider that registers routes/views/config/livewire-namespace
- A `config/<package>.php` with a publishable tag
- Routes only when the package owns URLs
- Views or a Livewire namespace only when it owns UI
- Migrations only when it owns persistence
- Tests for package behavior and host wiring
- A README contract section that the host implements

## Examples

| Package | Owns |
| --- | --- |
| `mortelos/starter` | App shell, layout, auth + dashboard + inbox + governance + users + settings routes, `starter::` Livewire namespace |
| `mortelos/ui` | Shared design primitives consumed by starter and other packages |
| `mortelos/entity-graph` | API routes, views, a Livewire namespace, migrations, extension contracts, a chat widget, an agent tool |
| `mortelos/policy-studio` | Governance widgets, routes, proposal-first policy flows |
| `mortelos/document-studio` | Document review pipelines |
| Custom connector (e.g. `mortelos/moneybird-connector`) | Channel id, setup provider, sync jobs, health check, data-request provider |

## What stays in the host

- Tenant model, membership, roles, invitations
- Branding, color, logo, copy
- Auth controllers (the package gives you the contract; the host implements)
- Policy seeding (the package gives you abilities; the host seeds defaults)
- Local orchestration: scheduled jobs, host-specific commands, telemetry sinks
- Resolvers: `StarterSidebarNavigationResolver`,
  `StarterUniversalSearchResolver`, `StarterGovernanceResolver`,
  `StarterUsersResolver`, etc. (the package gives you the interface; the host
  implements)

## Common smells

- **A package that hardcodes `App\…`** → reach back through a config key or
  resolver instead
- **A host that copies starter views into `resources/views/vendor/`** without a
  customization reason → unpublish them; extend the package instead
- **No decision recorded for a new surface** → CI will catch it; record first

## Tradeoff

`package-ready` is the most common choice for new portal surfaces. It says
"this should be a package eventually but I want fast host wiring now."
`package-now` is correct when you already see 2+ MortelOS installations needing
the same thing. `workspace-only` is rare and needs a concrete reason ("this
customer's regulator requires a bespoke approval flow that no other customer
will have").
