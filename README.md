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
vendor/bin/pest
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
