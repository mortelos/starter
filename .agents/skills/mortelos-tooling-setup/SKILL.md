---
name: mortelos-tooling-setup
description: "Use this when someone needs to prepare a clean local machine for MortelOS portal work, especially before creating a new portal host app. Covers required local tooling such as Laravel Herd, PHP, Composer, Node, GitHub access, the MortelOS CLI, DBngin, TablePlus, and verification before handing off to setup-portal."
---

# MortelOS Tooling Setup

Use this skill before `setup-portal` when the user cannot yet run a MortelOS host app locally, asks what to install first, or mentions Herd, DBngin, TablePlus, PHP, Composer, Node, GitHub access, or `mortelos new`.

This is a machine-preparation skill. Stop when the machine can create, boot, and verify a fresh MortelOS Starter host app. Do not start customer-specific portal implementation here.

## Source of Truth

Before giving current install instructions, check the official docs when network access is available:

1. MortelOS installation: https://mortelos.nl/docs/0/installation
2. MortelOS building portals: https://mortelos.nl/docs/0/building-portals
3. MortelOS dev tools: https://mortelos.nl/docs/0/package-dev-tools
4. Laravel Herd installation: https://herd.laravel.com/docs/macos/getting-started/installation
5. Laravel Herd databases: https://herd.laravel.com/docs/macos/getting-started/databases
6. Laravel Herd PHP versions: https://herd.laravel.com/docs/macos/technology/php-versions
7. Laravel Herd Node versions: https://herd.laravel.com/docs/macos/technology/node-versions
8. DBngin: https://dbngin.com/
9. TablePlus: https://tableplus.com/

Do not maintain a local copy of the MortelOS method. Use this skill for the setup checklist and use the docs for current versions, commands, and constraints.

## Recommended Stack

Default to a Mac-first local setup unless the user says otherwise.

Required:

1. Laravel Herd for PHP, Nginx, Composer, Node, local `.test` domains, and Laravel-friendly defaults.
2. PHP version matching the MortelOS installation docs. Current starter baseline is PHP `^8.4`.
3. Composer version matching the MortelOS installation docs. Current baseline is Composer `^2.7`.
4. Node version matching the MortelOS installation docs. Current baseline is Node `^20`.
5. Git with GitHub SSH or token access for private MortelOS package repositories when Composer needs them.
6. MortelOS CLI installed from a trusted `mortelos/starter` checkout.
7. A Laravel-capable editor such as Cursor, VS Code, or PhpStorm.

Database tooling:

1. The starter runs on PostgreSQL by default, so a local PostgreSQL service must run before `mortelos new` or `composer setup`.
2. Use DBngin for PostgreSQL (and Redis when needed) unless the developer uses Herd Pro services.
3. Use Herd Pro services instead of DBngin if the team has Herd Pro and wants database services managed inside Herd.
4. Use TablePlus to inspect PostgreSQL and Redis connections during setup and debugging.

Optional but useful:

1. GitHub CLI for repo authentication and quick remote checks.
2. A password manager for GitHub tokens, database credentials, and service keys.
3. Local mail tooling only when a portal flow needs email during the first setup.

## Install Flow

Use this order when guiding a clean machine.

```text
Clean machine
    |
    v
Install Herd
    |
    v
Confirm PHP, Composer, Node
    |
    v
Configure GitHub access
    |
    v
Install MortelOS CLI
    |
    v
Start PostgreSQL
    |
    v
DBngin or Herd Pro
    |
    v
Add TablePlus
                  |
                  v
        Create and verify host app
                  |
                  v
            Continue with setup-portal
```

## Step 1: Install Herd

Tell the user to install Laravel Herd first. For macOS, Herd requires macOS 12.0 or higher.

After Herd onboarding, verify these commands from a new terminal:

```bash
herd --version
php --version
composer --version
node --version
npm --version
```

If PHP or Node versions do not match the MortelOS docs, use Herd's version managers:

```bash
herd php:list
herd php:install 8.4
herd use 8.4
nvm install 20
nvm use 20
```

If the user is working inside a Herd parked directory, use `~/Herd` as the default workspace.

## Step 2: Configure GitHub Access

MortelOS packages may require private repository access during Composer install.

Verify Git and GitHub access:

```bash
git --version
ssh -T git@github.com
```

If SSH is not configured, guide the user through GitHub SSH setup or token-based Composer authentication. Do not invent credentials. Ask the user to confirm access before continuing.

## Step 3: Install MortelOS CLI

Install the CLI from a trusted starter checkout:

```bash
git clone https://github.com/mortelos/starter.git mortelos-starter
cd mortelos-starter
mkdir -p ~/.local/bin
install -m 0755 bin/mortelos ~/.local/bin/mortelos
mortelos --version
```

Make sure `~/.local/bin` is in `PATH` before stale system-wide locations. If `mortelos --version` shows the wrong version, inspect:

```bash
type -a mortelos
```

## Step 4: Choose Database Tooling

Use this decision rule:

1. Run PostgreSQL locally through DBngin or Herd Pro services. The starter `.env.example` expects `127.0.0.1:5432` with user `postgres` and an empty password (the DBngin default); change `DB_USERNAME` and `DB_PASSWORD` in `.env` when the service uses other credentials.
2. Add Redis through the same tool only when queues, cache, broadcasts, or connector work need it.
3. Install TablePlus for visual inspection and quick connection checks.

Expected local connection checks:

1. PostgreSQL: `composer setup` names the database after the project folder (`mijn-portal` becomes `mijn_portal`) and creates it during the first migration; open it in TablePlus afterwards.
2. SQLite stays available for a quick throwaway check: set `DB_CONNECTION=sqlite` in `.env` and remove the other `DB_` lines. Do not ship a portal on SQLite: it allows one writer at a time, and a slow workflow step then blocks every other write.
3. Redis: start the Redis service only when queues, cache, broadcasts, or connector work need it.

## Step 5: Create a Smoke Host

Only create a smoke host after the tooling checks pass.

```bash
cd ~/Herd
mortelos new smoke-portal
cd smoke-portal
herd isolate 8.4
herd isolate-node 20
herd open
php artisan starter:doctor
vendor/bin/pest
```

If the project was created without the CLI, use the MortelOS installation docs for the current Composer-based fallback.

Expected result:

1. The app opens locally.
2. Login works with the development seed account from the MortelOS installation docs.
3. `php artisan starter:doctor` passes.
4. `vendor/bin/pest` passes or fails only for a clear environment reason that is reported.

## Handoff to Portal Work

When setup succeeds, report only:

1. Installed or verified tools.
2. PHP, Composer, Node, Herd, and MortelOS CLI versions.
3. PostgreSQL service used: DBngin or Herd Pro.
4. TablePlus connection status.
5. Smoke host verification results.
6. Next step: use `setup-portal` for the actual portal kickoff.

Keep Dutch user-facing setup copy free of em-dashes.
