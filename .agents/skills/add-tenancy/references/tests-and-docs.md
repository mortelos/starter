# Generated tests & docs (UTEQ-529, spec §9)

Every run leaves the portal with proof of isolation and a note of which driver
it is on. Tests are the contract; the docs note is so the partner is not
surprised later.

## Tests

Detect the host framework first (`vendor/bin/pest` vs `vendor/bin/phpunit`) and
match it. Copy the templates that fit the chosen driver:

| Template | Destination | Driver |
|----------|-------------|--------|
| `templates/tests/TenancyBootTest.php.stub` | `tests/Feature/TenancyBootTest.php` | both |
| `templates/tests/DatabaseTenantIsolationTest.php.stub` | `tests/Feature/TenantIsolationTest.php` | `database` |
| `templates/tests/RowTenantIsolationTest.php.stub` | `tests/Feature/TenantIsolationTest.php` | `row` |

What they prove:

- **Boot, both framework states (the autoload trap).** Framework-absent: the
  app boots and the guarded binding never autoloads `StanclTenantResolver`, and
  the contract is unbound. Framework-present (skipped on a build-only portal,
  runs in operate-mode CI): the resolver is bound to `StanclTenantResolver`.
  Plus `tenancy()->initialized` flips correctly.
- **Isolation.** `database`: two tenants → two databases → A's row invisible in
  B. `row`: `tenant_id` auto-filled on create, reads scoped to the active
  tenant, central context unscoped.

Test-DB notes:
- `database` driver: sqlite-per-tenant keeps the isolation test self-contained
  (tenant create fires stancl's CreateDatabase + MigrateDatabase). Ensure the
  example tenant migration is in `database/migrations/tenant/`. If tenant DB
  creation is slow in CI, scope these to a dedicated test group.
- `row` driver: a normal `RefreshDatabase` works — it is one database. The test
  assumes `App\Models\Note` (the example scoped model) exists.

## Docs

Append a short tenancy note to the **portal's own** docs (`README.md` and/or
`AGENTS.md`) from `templates/docs/tenancy-note.md.stub`, filled with the chosen
driver and `--shared` list. The one thing that must be written down is the
`row` **foreclosure** (no framework operate-mode without migration) — a future
maintainer choosing to add the framework needs to find that decision recorded,
not rediscover it.

Do not touch the mortelos org's central docs from here; this note lives in the
generated portal only.

## Final gate (phase [7])

- `vendor/bin/pest` (or `phpunit`) green, including the new isolation test.
- `vendor/bin/pint --dirty` clean.
- Report driver, foreclosure status, what was wired, and the partner's next step.
