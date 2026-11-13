<?php

declare(strict_types=1);

/*
 * Only what differs from mortelos/social-login's own config (merged key by key, shallowly). Providers, client ids
 * and secrets and the trusted Microsoft tenants come from .env: docs/social-login.md.
 */
return [

    // The package default is ['is_super_admin']. The starter's users have no such column and mortelos/app-standards
    // makes models strict (a missing attribute throws), so the default would break every sign-in for a user that
    // is found by e-mail address. An app that adds a flag like it lists the column here.
    'never_link_by_email' => [],

    // After signing in (with or without the two-factor code) the package goes to the visitor's intended page, or
    // home, which sends them to the post-login redirect. A user who links a provider starts at
    // /settings/social-login/{provider} (routes/starter.php), which makes the settings page the intended page.
    'redirect' => '/',

];
