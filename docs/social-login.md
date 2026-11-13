# Sign in with Google and Microsoft

The starter ships `mortelos/social-login`. The login page shows a button per provider, and the settings page has a
card **Inloggen met Google of Microsoft** to link an account. A button shows only when the client id and the secret of
that provider are set; without keys nothing shows and nothing breaks.

## Turn it on

```dotenv
SOCIAL_LOGIN_PROVIDERS=google,microsoft            # in .env.example already
SOCIAL_LOGIN_GOOGLE_CLIENT_ID=
SOCIAL_LOGIN_GOOGLE_CLIENT_SECRET=
SOCIAL_LOGIN_MICROSOFT_CLIENT_ID=
SOCIAL_LOGIN_MICROSOFT_CLIENT_SECRET=
SOCIAL_LOGIN_MICROSOFT_TENANT=organizations
SOCIAL_LOGIN_MICROSOFT_TRUSTED_TENANTS="<tenant-id>=klant.nl|klant.com,<other-id>=ander.nl"
```

Real values go in the `.env` of the environment, never in `.env.example`. Register the redirect address with each
provider, one per environment (wildcards do not work): `https://<app>/login/google/callback` and
`https://<app>/login/microsoft/callback`.

- **Google**: an OAuth client of type *Web application*; set the consent screen to *External* and publish it, or only
  test users can sign in.
- **Microsoft**: a multi-tenant app registration. A Microsoft address only counts as verified when its Entra tenant and
  domain are in `SOCIAL_LOGIN_MICROSOFT_TRUSTED_TENANTS`, because any tenant admin can set any `mail` value. Without a
  line the user gets "geen bevestigd e-mailadres" and cannot link.

## Who gets in (the strict way)

- Only people who already have an account: `RefuseUnknownUsers` creates nobody. An unknown address gets "Er is nog
  geen account voor ...".
- Someone who can already sign in (a password counts) is **not** linked by e-mail address on their first Google or
  Microsoft sign-in: `SignInMethods` refuses it, with "Log eerst in met je wachtwoord en koppel daarna ... op je
  profiel (Instellingen)". An address on a user row proves nothing, because anyone can type any address on it.
- So: log in with the password first, open **Instellingen**, choose **Koppel Google** (or Microsoft). The address of the
  provider account must be the same as the address of the profile, and verified. From then on the button signs in.
- Both are bound in `StarterServiceProvider` (the package defaults, made visible), and `SocialLoginTest` guards them.
  Do not loosen them with a custom class.

## Two-factor after the provider

A user with two-factor on is not signed in by the provider callback. They go to the same code page as after a password
and are signed in after the code ([two-factor.md](two-factor.md)). The package needs `fortify.guard` to be the default
guard (`web`); `starter:doctor` and a test check it.

## Where it lands

A sign-in goes to the page the visitor wanted (`url.intended`) or home, which sends them to the post-login redirect.
Linking starts at `/settings/social-login/{provider}` (route `settings.social-login.link`), which makes the settings page
the page to come back to; a refused link shows its reason there (home hands it on).

## Settings in code

- `config/social-login.php`: only what differs from the package. `never_link_by_email` is `[]` here: the package default
  `['is_super_admin']` reads a column the starter does not have, and strict models make that throw.
- `lang/vendor/social-login/nl/messages.php`: the one Dutch message that differs (`link_refused`); the rest is the
  package's own Dutch.
- Table `social_identities`: `database/migrations/2026_10_07_000000_create_social_identities_table.php` (published from
  the package). Users and identities share the central connection; a multi-tenant app sets `social-login.connection`.

## Check it

`php artisan starter:doctor` checks the table and the provider list and warns when a listed provider has no keys.
`tests/Feature/SocialLoginTest.php` pretends the provider with `Socialite::fake`; the browser test
`tests/Browser/TwoFactorLoginTest.php` runs the whole round trip with a loopback provider.
