# Upgrading

## To 0.14.0

The starter runs on `mortelos/framework` 0.7 and its code is English. Read the framework's
`UPGRADE.md` first (publish and run the new migration); this lists what changed in the starter itself.

| 0.13 | 0.14 |
| --- | --- |
| `mortelos/framework` `^0.6.41` | `^0.7` |
| `mortelos/ui` `^0.5.1`, `mortelos/app-standards` `^0.1.3` | `^0.5.2`, `^0.1.4` |
| dev: `mortelos/dev-tools` `^0.1.7`, `mortelos/agent-standards` `^0.1.14` | `^0.1.8`, `^0.1.15` |
| Livewire event `overzicht-saved` (sidebar listens, universal search dispatches) | `overview-saved` (handler `onOverviewSaved`) |
| route name `overzichten.show` (the sidebar links to it, the host defines it) | `overviews.show` |
| inbox filter types `voorstel`, `notificatie`, `actie` | `proposal`, `notification`, `action` |

What an app built on the starter must do:

1. Rename the `overzichten.show` route to `overviews.show`, and rename listeners of `overzicht-saved` to `overview-saved`.
2. Update stored inbox types and every other renamed value through the framework migration.
3. Run `composer update -W`, then `php artisan mortelos:agent-rules:publish --target=AGENTS.md` (the `post-autoload-dump` script does this) so `AGENTS.md` carries the new "Code Language" rule.
4. `tests/Feature/CodeLanguageGuardTest.php` runs `CodeLanguageGuard`; `composer test:arch` includes it. Customer-facing Dutch copy stays Dutch.
