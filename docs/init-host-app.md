# Init a Host App

In this template, "init" is one command:

```bash
composer create-project mortelos/starter mijn-portal
cd mijn-portal
npm install --ignore-scripts
npm run build
php artisan starter:doctor    # should be green
php artisan serve             # http://127.0.0.1:8000
# log in as admin@example.test / password
```

That's it. The `composer create-project` already ran `composer install`, set an
`APP_KEY`, created `database/database.sqlite`, ran migrations, and seeded the
admin account. The login → tenant-select → dashboard flow works immediately on
the seeded admin, with the stubs under `app/Http/Controllers/Auth/` handling
the auth contracts.

## Prerequisites

| Tool | Version |
| --- | --- |
| PHP | `^8.4` |
| Composer | `^2.7` |
| Node | `^20` (for Vite + Tailwind) |
| SSH access to `github.com/mortelos/ui` | Required for `composer install` |

On macOS with Homebrew:

```bash
brew install php@8.4 composer node@20
ssh -T git@github.com   # confirm SSH access
```

## What to do next

Once the app boots and you see the dashboard:

1. **Run the `portal-kickoff` skill** (Claude) on the user's request, or
2. **Follow `docs/building-portals.md` §1** (other agents) to start the
   capability-first interview

After the capability map is captured the agent fills the contracts:

- Replace the auth stubs in `app/Http/Controllers/Auth/` with real
  implementations as the portal's auth flow specifies (passkey lib, tenant
  membership lookup, invitation persistence)
- Implement the host-owned resolvers in `app/Support/` and bind them in
  `config/starter.php` as the capability map needs sidebar, search, governance,
  users, dashboard, inbox, or chat
- Record every new surface as a `package-now` / `-ready` / `workspace-only`
  decision in `.mortelos/package-decisions.md`

See [`host-app-anatomy.md`](host-app-anatomy.md) for the directory layout a
fully fleshed-out host grows into.
