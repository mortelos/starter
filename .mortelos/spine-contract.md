# MortelOS Spine contract

The Spine is the governance default every MortelOS portal inherits. It is owned
once here, in the platform, so portals adopt it instead of reinventing it.

| Clause | Rule |
| ------ | ---- |
| **C1** | Reading in-scope data is direct. No proposal, no approval. |
| **C2** | Every mutation of governed state goes through a human-approved proposal. |
| **C3** | Access is deny-by-default across entities, links, tools and surfaces. |
| **C4** | The audit trail is immutable and event-sourced. |

## The rails

The four clauses rest on concrete `Mortel\` classes in `mortelos/framework`.
A portal is spine-conformant when all of them exist and are wired:

| Clause | Anchor | Wiring |
| ------ | ------ | ------- |
| C3 | `Mortel\Access\ContextAccessResolver` | Retains its `deny_by_default` terminal branch |
| C3 | `Mortel\Access\AccessActor`, `Mortel\Access\DeniedActor` | Exist |
| C2 | `Mortel\Models\InboxItem`, `Mortel\Actions\ApproveInboxItem` | Exist |
| C2 | `Mortel\Aggregates\*` (Entity, Link, Policy, Role, Channel, Workflow, Document) | Exist |
| C2/C3 | `Mortel\Actions\Policies\CheckPolicy` | Resolvable from the container |
| C2/C3 | `Mortel\Http\Middleware\EnforcePolicy` | Present on the host route stack |
| C4 | Spatie `StoredEventRepository` | Bound to an append-only repository |
| C4 | `create_events_table` migration | Published into the host |
| all | `Mortel\Contracts\TenantResolver` | Bound to a real resolver, not `NullTenantResolver` |

Verify with:

```bash
php artisan mortelos:spine:check
```

## Governed models and sanctioned writers

`config/spine.php` names the models whose rows are governed state, and the
classes allowed to write them directly:

- **aggregates** — they are the write path,
- **projectors** — they rebuild read models from events, which is not a
  governed mutation,
- **governed actions** tagged `#[Mortelos\AppStandards\Spine\GovernedWriter]` —
  the escape hatch for writers that do not fit the namespace patterns.

Everything else writing `->save()`, `->update()`, `->create()` or `->delete()`
on a governed model is a C2 bypass. The PHPStan rule in `mortelos/dev-tools`
flags it; `@spine-ignore` on the offending line silences a deliberate exception.

```neon
# phpstan.neon
includes:
    - vendor/mortelos/dev-tools/extension.neon
```

Publish both this contract and the config with:

```bash
php artisan vendor:publish --tag=mortelos-spine-contract
```

## What the checks do not prove

C2 is concrete-class, not an interface, so it cannot be forced structurally.
`mortelos:spine:check` proves the rails exist and are connected; the PHPStan
rule catches the obvious bypass smells. Neither proves that a given mutation
actually travelled through a proposal a human approved.

**That part stays a per-portal test.** Every portal keeps its own tests for its
proposal path: propose → pending → human approves → state changes → event
recorded. Treat a green `mortelos:spine:check` as "the rails are laid", never as
"the traffic obeys them".
