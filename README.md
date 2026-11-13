# MortelOS Starter

MortelOS Starter is a Laravel host application template for governed, AI-assisted customer portals.

Use the public documentation as the source of truth:

| Topic | URL |
| --- | --- |
| Overview | https://mortelos.nl/docs |
| Installation | https://mortelos.nl/docs/0/installation |
| First portal | https://mortelos.nl/docs/0/first-portal |
| Building portals | https://mortelos.nl/docs/0/building-portals |
| Starter package | https://mortelos.nl/docs/0/starter-package |
| Troubleshooting | https://mortelos.nl/docs/0/troubleshooting |

Local quick check:

```bash
php artisan starter:doctor
composer test
npx playwright install chromium   # once per machine
npm run build && composer test:browser
```

Local development:

```bash
composer dev
```

Agent workflows:

| Situation | Skill |
| --- | --- |
| Clean machine, missing tooling, Herd, PHP, Composer, Node, GitHub access, MortelOS CLI, DBngin, TablePlus, or `mortelos new` setup | `mortelos-tooling-setup` |
| New portal kickoff after a host app can boot and verify | `setup-portal` |

Sign-in and queue options (each has a short guide in `docs/`):

| What | Turn it on | Guide |
| --- | --- | --- |
| Two-factor sign-in (authenticator app and recovery codes) | Each user: **Instellingen**, card **Tweestaps** | [docs/two-factor.md](docs/two-factor.md) |
| Sign in with Google and Microsoft | Set `SOCIAL_LOGIN_*` client id and secret in `.env`; a button shows only when both are set | [docs/social-login.md](docs/social-login.md) |
| Horizon (Redis queue and dashboard) | `QUEUE_CONNECTION=redis`, then `php artisan horizon`; `/horizon` needs the ability `horizon.view` | [docs/horizon.md](docs/horizon.md) |
