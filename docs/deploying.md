# Deploying

The sidebar shows an update notice ("Update beschikbaar") once a newer build is live, and the browser keeps what users typed across a deploy or an expired session. This needs no wiring in the starter (`<x-mortel::sidebar>` and `@mortelScripts` are already in the layout), but the deploy has to follow three rules.

## Build id

`mortelos/ui` compares the build id of an open page with the id of the live build. The id comes from the first of these that exists:

1. the environment variable `MORTEL_BUILD` (set it on the server, or `MORTEL_BUILD_LABEL` for the label shown to users);
2. the name of the release directory the app runs from, when its real path ends in `/releases/<name>` (Forge zero-downtime and a `current` symlink both qualify);
3. the git commit of the checkout;
4. the hash of the Vite manifest (`public/build/manifest.json`).

A deploy must change the id. With a release directory per deploy that happens by itself; with an in-place deploy, set `MORTEL_BUILD` to something that changes with every release (a tag or commit).

## Keep the previous build's assets

Pages that are still open request the asset files of the build they were loaded with. Vite gives every build new file names and removes the old ones, so a deploy that replaces `public/build` breaks those pages until they reload.

Copy the previous release's `public/build` files into the new one without overwriting anything:

```bash
cp -rn "$PREVIOUS_RELEASE/public/build/." "$NEW_RELEASE/public/build/"
```

Use `cp -n` (never overwrite), so the new build's `manifest.json` and files always win.

## After switching releases, and after every `composer install`

Blade caches the output of `@mortelScripts` in the compiled views (`storage/framework/views`). Until those are cleared, pages keep rendering the old script tags, or none at all after a `mortelos/ui` update, and the safe-deploy script silently stays off. This step is a must on every deploy, after `composer install` and after the release switch:

```bash
php artisan view:clear
php artisan view:cache
```

## Release notes

Write a note per release in `resources/release-notes/`; see the README there for the naming rule.
