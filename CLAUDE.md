# mortelos/starter — application starter layer

Upstream `mortelos/starter` repository. Symlinked into a host MortelOS application as `vendor/mortelos/starter`.

## Conventions

- Namespace: `Mortelos\Starter\*`
- Provides resolvers consumed by host applications: `StarterSidebarNavigationResolver`, `StarterUniversalSearchResolver`, `StarterGovernanceResolver`.

## Edit workflow when consumed via symlink

1. Edits land in the host app via symlink.
2. Commit separately in **this** repository.
3. After changing service-provider config, run `composer update mortelos/starter` in the host app.

Package-first governance applies — record any reusable additions in the host app's `.mortelos/package-decisions.md`.
