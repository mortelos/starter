# Building blocks · starter

Read this before you propose code (rule `reuse-inventory` in AGENTS.md); update it in the same pull request when a block is added, removed or moved (rule `reuse-catalog`). The starter's own outlets are listed in `resources/mortelos/outlets.json` (`php artisan mortelos:outlets:list`). Blocks that come from MortelOS packages are listed in `vendor/mortelos/agent-standards/resources/mortelos/building-blocks.md`; this file only lists what the starter wires itself and how it is set up here.

## Sign-in and account

| Block | What it does | Set it up via | Code | Note |
| --- | --- | --- | --- | --- |
| Password login | Login page and POST handler, throttled | Config in `config/starter.php`: `starter.auth.password_form_component` (a Blade component name) and `starter.auth.controllers.password_login` (a controller class); changing either is code | `resources/views/pages/auth/⚡login.blade.php`, `app/Http/Controllers/Auth/PasswordLoginController.php`, `resources/views/components/auth/password-form.blade.php` | Route `login` and `auth.password-login` in `routes/starter.php` |
| Passkey login | Slot for a passkey form and handler | `starter.auth.passkey_form_component` (a Blade component name, null = hidden) and `starter.auth.controllers.passkey_authenticated` (a controller class); a real passkey login is code | `app/Http/Controllers/Auth/PasskeyAuthenticatedController.php`, `resources/views/components/auth/passkey-form.blade.php` | Handler answers 501 until a passkey library is chosen |
| Login throttle | Limits login, passkey and invite POSTs to 5 per minute per IP | Rate limiter `auth` | `app/Providers/AppServiceProvider.php` | Used as `throttle:auth` in `routes/starter.php` |
| Post-login redirect | Decides where a user lands after login and on `/` | Code: edit the class bound to `starter.auth.post_login_redirect_resolver` | `app/Actions/Auth/ResolvePostLoginRedirect.php` | Returns the dashboard route; make it role-aware here |
| Seeded admin | Admin user with the owner role in the default tenant | `php artisan db:seed` | `database/seeders/DatabaseSeeder.php` | `admin@example.test` with a dev password; change it before any deploy. Re-seeding is safe |
| Profile and password | Users change name, email and password | Settings screen `/settings` | `resources/views/pages/settings/⚡index.blade.php`, `app/Actions/Account/UpdateProfile.php`, `app/Actions/Account/ChangePassword.php` | Route `settings` |
| Accept invitation | Public page where an invited person sets a password and joins | Link from the invite (`/invite/{token}`) | `app/Http/Controllers/Auth/AcceptInvitationController.php`, `resources/views/auth/accept-invitation.blade.php`, `app/Actions/Invitations/AcceptInvitation.php` | Token is stored as a sha256 hash only |
| Two-factor sign-in | Code from an authenticator app (or a recovery code) after the password and after social login, set up on the settings page with the password as proof | Card **Tweestaps** on `/settings`; `config/fortify.php` (feature `twoFactorAuthentication`); limiter `two-factor` | `resources/views/components/settings/⚡two-factor.blade.php`, `app/Actions/Account/EnableTwoFactor.php`, `ConfirmTwoFactor.php`, `DisableTwoFactor.php`, `RegenerateRecoveryCodes.php`, `resources/views/auth/two-factor-challenge.blade.php`, `app/Http/Controllers/Auth/PasswordLoginController.php` | Laravel Fortify's columns on `users`, so Fortify apps fit one to one; Fortify's own routes are off, the challenge routes are in `routes/starter.php`. See [two-factor.md](two-factor.md) |
| Sign in with Google and Microsoft | Buttons on the login page, a link card on the settings page; only people with an account, link after a password login | `SOCIAL_LOGIN_*` in `.env` (a button shows when client id and secret are set); `config/social-login.php` | `resources/views/components/auth/password-form.blade.php`, `resources/views/components/settings/social-login-card.blade.php`, `app/Providers/StarterServiceProvider.php`, `routes/starter.php`, `lang/vendor/social-login/nl/messages.php` | Package `mortelos/social-login`; the policy is the package default (`RefuseUnknownUsers`, strict `SignInMethods`), bound and tested. See [social-login.md](social-login.md) |

## Tenancy and membership

| Block | What it does | Set it up via | Code | Note |
| --- | --- | --- | --- | --- |
| Single-tenant resolver | Always resolves one default tenant; reads tenant data from the `tenants` table | `STARTER_DEFAULT_TENANT_ID`, `STARTER_DEFAULT_TENANT_NAME`, `STARTER_DEFAULT_BRANCH_ID` (`starter.tenancy.*`) | `app/Support/SingleTenantResolver.php`, `app/Providers/StarterServiceProvider.php` | Bound as the framework `TenantResolver`. More than one customer: use the `add-tenancy` skill |
| Tenant record | The default tenant, with its name in the `data` json | `tenants` table, `DatabaseSeeder` | `app/Models/Tenant.php` | `mortel.models.tenant` is null on purpose |
| Membership and role | Which user belongs to the tenant and with which role | `tenant_user` table (`role`, `role_id`) | `database/migrations/2026_06_01_000003_create_single_tenant_framework_baseline.php` | Written by the seeder and by `AcceptInvitation` |

## Access and governance

| Block | What it does | Set it up via | Code | Note |
| --- | --- | --- | --- | --- |
| Governance gate | Deny-by-default check: a user may do an ability only if their role has an allow policy | Screen `/governance/roles` (allow policies per role); a new ability is an entry in `SCOPES` (code) | `app/Access/StarterGovernanceGate.php`, `app/Contracts/GovernanceGate.php` | A new host ability goes in the `SCOPES` map of the gate, then in the seeder |
| Host abilities | `governance.manage`, `users.manage`, `invitations.manage`, `horizon.view`, `changelog.view` | Screen `/governance/roles` or the seeder: one allow policy per ability; a new ability is code in `SCOPES` | `app/Access/StarterGovernanceGate.php`, `database/seeders/DatabaseSeeder.php` | `invitations.manage`, `horizon.view` and `changelog.view` live in scope `Action`; see AGENTS.md for the rule |
| Owner role | Role `owner` with an allow policy for every host ability | `roles` and `policies` tables; `php artisan db:seed` | `database/seeders/DatabaseSeeder.php`, `tests/Support/Governance.php` | Created through the framework Actions, so it is stored events |
| Roles and policies screen | Create, edit and delete roles and policies | Screen `/governance/roles` | `resources/views/pages/governance/⚡roles.blade.php` | Route `governance.roles` |
| Governance overview | Landing page for roles, proposals, stats and trust settings | Code: bind a class to `starter.governance.resolver` and optionally `starter.governance.access_resolver`; the five `starter.governance.*_component` keys take a Livewire component name | `resources/views/pages/governance/⚡index.blade.php`, `app/Enums/TrustLevel.php` | Empty state until a resolver is set; without `access_resolver` the gate decides. `TrustLevel` only mirrors the three framework trust levels; it is not a setting |

## Users and invitations

| Block | What it does | Set it up via | Code | Note |
| --- | --- | --- | --- | --- |
| Users screen | Lists members and open invitations, invites and revokes | Classes bound to `starter.users.resolver` and `starter.users.access_resolver` (code, the starter ships both) | `resources/views/pages/users/⚡index.blade.php`, `app/Support/StarterUsersResolver.php`, `app/Support/StarterUsersAccessResolver.php` | Needs ability `users.manage`; route `users` |
| Member access panel | Slide-over with the access of one member | Class bound to `starter.users.access_resolver` (code) | `resources/views/components/users/⚡user-access-slide-over.blade.php` | Only members of the current tenant can be opened |
| Invite a user | Creates an invitation and returns the accept link | Ability `invitations.manage`, `invitations` table | `app/Actions/Invitations/InviteUser.php`, `app/Models/Invitation.php` | Link is valid 7 days; the plain token only exists in the Action result |
| Invite with undo | Inviting returns an Undo that revokes the invitation within an hour; revoking itself has no Undo, a new invitation is the way back | Counter-Action registered in `UndoRegistry` (code) | `app/Actions/Invitations/InviteUser.php`, `app/Actions/Invitations/RevokeInvitation.php`, `app/Providers/StarterServiceProvider.php` | Worked example of the Undo pattern |
| Invitation aggregate | Records created, revoked and accepted as stored events | `invitations.invitation.*` in `event_class_map` | `app/Aggregates/InvitationAggregate.php`, `app/Events/Invitations/InvitationCreated.php`, `config/event-sourcing.php` | Worked example of aggregate, event and projector |
| Invitation projector | Writes the `invitations` table from the events | Code: a class in `app/Projectors` is found automatically (`auto_discover_projectors_and_reactors`) | `app/Projectors/InvitationProjector.php`, `config/event-sourcing.php` | Replay rebuilds the table |
| Invitation service | The framework invitation contract on top of `InviteUser` | Container binding `TenantInvitationService` | `app/Support/StarterTenantInvitationService.php` | Lets package screens invite without a second path |

## Inbox

| Block | What it does | Set it up via | Code | Note |
| --- | --- | --- | --- | --- |
| Inbox screen | Filterable list with a detail pane per item | `inbox_items` table; filters in the URL (`filterType`, `filterRiskLevel`, `filterSource`, `status`, `archive`, `item`) | `resources/views/pages/inbox/⚡index.blade.php`, `resources/views/components/inbox/⚡inbox-list.blade.php`, `resources/views/components/inbox/⚡inbox-filter.blade.php` | Route `inbox` |
| Inbox access | An owner sees and decides on their own items only | Code: container binding `AuthorizeInboxItemAccess` | `app/Providers/StarterServiceProvider.php`, `resources/views/components/inbox/⚡inbox-list.blade.php`, `resources/views/components/inbox/⚡inbox-detail.blade.php`, `resources/views/components/inbox/⚡intake-detail.blade.php` | Team inboxes need more than the binding: the list, detail and intake components each filter on `user_id` themselves and must change with it |
| Approve or reject | Detail pane for items that wait for a decision | Item state in `inbox_items` | `resources/views/components/inbox/⚡inbox-detail.blade.php` | Calls the framework Actions `ApproveInboxItem` and `RejectInboxItem` |
| Intake pane | Chat plus widgets for item types listed as intake | `starter.inbox.intake_detail_types` (a list of item types, a plain setting) and a class bound to `starter.inbox.item_type_resolver` (code) | `resources/views/components/inbox/⚡intake-detail.blade.php` | Conversations live in `agent_conversations` and `agent_conversation_messages` |
| Inbox tables | Items, learning patterns and agent conversations | Migration | `database/migrations/2026_10_14_000001_create_inbox_tables.php` | `learning_patterns` feeds `starter.governance.learning_patterns_component` |

## Shell and navigation

| Block | What it does | Set it up via | Code | Note |
| --- | --- | --- | --- | --- |
| App layout | Sidebar, topbar, search and chat panel around every signed-in page | `starter.layout.*` keys | `resources/views/layouts/app.blade.php` | Pages use `#[Layout('layouts::app')]` |
| Guest layout | Layout for login and invite pages | n/a | `resources/views/layouts/guest.blade.php` | |
| Sidebar menu | Sections, inbox badge and saved overviews | Code: a class bound to `starter.navigation.sidebar_resolver` (methods `sections`, `inboxCount`, `overviews`); `starter.layout.sidebar_nav_component` is a Livewire component name | `resources/views/components/shared/⚡sidebar-nav.blade.php` | Empty until the resolver is set; `starter:doctor` warns |
| Topbar and profile menu | Profile dropdown, a link to the change log for those with `changelog.view`, and logout | `starter.layout.topbar_component` (a Livewire component name; swapping it is code) | `resources/views/components/shared/⚡topbar.blade.php` | Logout is route `logout` |
| Universal search | Search over entities, inbox items, chats and menu items | `starter.layout.universal_search_component` (a Livewire component name) plus a class bound to `starter.navigation.universal_search_resolver` (code) | `resources/views/components/shared/⚡universal-search.blade.php` | Off while the component key is null |
| Chat panel | Side panel with the conversation, shown when chat is enabled | Code: a class bound to `starter.chat.settings_service` (needs `enabled()`) and a component name in `starter.chat.conversation_panel_component` | `resources/views/layouts/app.blade.php` | Needs the chat package; hidden while the service key is null |
| Dashboard | Widget grid and an optional message | `starter.dashboard.primary_widgets` and `starter.dashboard.secondary_widgets` (lists of Livewire component names; the components are code), a class bound to `starter.dashboard.proud_message_resolver` (code) | `resources/views/pages/dashboard/⚡index.blade.php` | Route `dashboard`; add a widget by listing it, not by editing the page |
| Onboarding | Welcome wizard with trust levels | Code: a class bound to `starter.onboarding.resolver` (methods `resolve`, `complete`) | `resources/views/pages/onboarding/⚡index.blade.php` | Shows an empty state while the resolver is null |

## Events and data

| Block | What it does | Set it up via | Code | Note |
| --- | --- | --- | --- | --- |
| Event store | Stored events and snapshots, with actor and class indexes | `events` and `snapshots` tables | `database/migrations/2026_03_26_000003_create_events_table.php`, `database/migrations/2026_03_26_000005_create_snapshots_table.php` | Never write to `events` by hand |
| Event registration | Maps host event types to classes; lists the framework projectors | Add each new host event to `event_class_map` (code); host projectors and reactors in `app/` are discovered, the `projectors` list holds the framework ones | `config/event-sourcing.php` | A framework projector missing from `projectors` stores events but never fills its table |
| Event schemas | Json schema per host event, validated on record | `mortel.events.schema_namespaces` | `config/mortel.php`, `app/Events/Invitations/invitation.created.v1.schema.json` | One `*.v1.schema.json` next to each event class |
| Governed models | Models that only an aggregate or projector may write | `governed_models`, `writers` in `config/spine.php` | `config/spine.php` | Add a new read table here; PHPStan then guards it |
| Entities and roles tables | Framework baseline tables for entities, links, roles and policies | Migration | `database/migrations/2026_06_01_000003_create_single_tenant_framework_baseline.php`, `database/migrations/2026_06_01_000001_create_roles_policies_tables.php` | Single-tenant |
| Queue | Database queue with `retry_after` above the agent job timeout | `QUEUE_CONNECTION`, `DB_QUEUE_RETRY_AFTER` | `config/queue.php`, `tests/Feature/QueueRetryAfterTest.php` | The `composer dev` script works queue `agents` first; the test fails if `retry_after` drops too low |
| Horizon | Redis queue with a dashboard at `/horizon`, one supervisor `default` (queues `agents`, `webhooks`, `default`), failed jobs in the database, a long-wait warning in the log, `horizon:snapshot` in the scheduler | `QUEUE_CONNECTION=redis`, `HORIZON_PREFIX` (empty: one per worktree locally), `HORIZON_NOTIFICATION_MAIL`; an app adds its own supervisors in `config/horizon.php` | `config/horizon.php`, `app/Providers/HorizonServiceProvider.php`, `app/Support/HorizonAccess.php`, `database/migrations/2026_11_13_100000_grant_horizon_access.php`, `tests/Feature/HorizonConfigTest.php` | Off until Redis is chosen. Dashboard only for a role with the ability `horizon.view`. A test keeps every job's timeout under its supervisor's and that under `retry_after`. See [horizon.md](horizon.md) |
| Change log | `/changelog`: every stored event as a readable line (who, what, part, when), newest first under day headings, filters on who, part and period, CSV export of exactly what the filter shows, and Ongedaan maken through the `UndoRegistry` with a Dutch message on a conflict | Ability `changelog.view`; sentences and parts per event type in the registry `ChangeLogDescriptions` (code: a `DescribesChanges` class tagged `changelog.descriptions`); contract outlets `ChangeLogAgents` (agent names), `ChangeLogAccess` (rights per part) and `ChangeLogArchive` (history from before the move) | `resources/views/pages/changelog/⚡index.blade.php`, `app/Actions/ChangeLog/UndoChange.php`, `app/Support/ChangeLog/`, `app/Contracts/ChangeLog/`, `app/Http/Controllers/ChangeLogExportController.php`, `resources/mortelos/outlets.json` | Undo needs the counter-Action's ability (or level Act on the part); an agent undoes only its own changes. Budget with 10,000 events: 9 queries, about 95 KB. See [change-log.md](change-log.md) |

## Checks and tooling

| Block | What it does | Set it up via | Code | Note |
| --- | --- | --- | --- | --- |
| Doctor | Checks config keys, routes, layout, event store, tenant baseline, two-factor wiring, social login, Horizon and the change log | `php artisan starter:doctor` | `app/Console/Commands/StarterDoctor.php` | Also runs `mortelos:spine:check` and `mortelos:outlets:check` |
| Arch guards | Fail on writes outside Actions, `event(new …)`, wrong page paths and raw Flux or HTML | `composer test:arch` | `tests/Feature/ArchGuardTest.php`, `tests/Feature/UiGuardTest.php`, `tests/Feature/OutletsGuardTest.php`, `tests/Feature/CodeLanguageGuardTest.php` | Allow lists may only go down |
| Test helpers | Create roles, policies and users the same way the app does, and a user with two-factor and its code | `tests/Pest.php` | `tests/Support/Governance.php`, `tests/Support/TwoFactor.php`, `tests/Support/LoopbackSocialite.php`, `tests/Support/QueueTimeouts.php`, `tests/TestCase.php` | Use these instead of inserting rows |
| Browser tests | Screen checks in headless Chromium | `composer test:browser` | `tests/Browser/NavigationVerifyTest.php`, `tests/Browser/UsersInviteTest.php`, `tests/Browser/TwoFactorLoginTest.php` | Run `npm run build` first |
| Error reporting | Flare with body fields, headers and traces censored | `FLARE_KEY`, `FLARE_REPORT` | `config/flare.php`, `tests/Unit/FlarePrivacyTest.php` | Keep the censor list when adding fields |
| Dutch UI | App locale `nl`, fallback `en`, kit strings in Dutch | `APP_LOCALE`, `APP_FALLBACK_LOCALE` in `.env.example` | `config/app.php`, `tests/Feature/UiLocaleTest.php` | |
| Dependency vetting | Clears new package versions with an agent review | `bin/vet-agent`, `vet.json` | `bin/vet-agent` | A new dependency needs a yes from the user first (AGENTS.md rule 10) |
| Portal workflow | Interview, package decisions, build plan and build for a new portal | Skill `setup-portal` | `.agents/skills/setup-portal/SKILL.md` | Run it first for a portal request |
| Machine setup | Gets a clean machine to the point where a host app boots | Skill `mortelos-tooling-setup` | `.agents/skills/mortelos-tooling-setup/SKILL.md` | |
| Scaffold command | Creates a new portal from the starter without its git history | `bin/mortelos new <name>` | `bin/mortelos` | |

The tenant isolation guard runs through `composer test:tenant-isolation` and CI. Users and invitations use the central connection; the baseline permits only Laravel's fixed test password hash cache.
