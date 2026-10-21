# Building blocks · starter

Read this before you propose code (rule `reuse-inventory` in AGENTS.md); update it in the same pull request when a block is added, removed or moved (rule `reuse-catalog`). Blocks that come from MortelOS packages are listed in `vendor/mortelos/agent-standards/resources/mortelos/building-blocks.md`; this file only lists what the starter wires itself and how it is set up here.

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
| Host abilities | `governance.manage`, `users.manage`, `invitations.manage` | Screen `/governance/roles` or the seeder: one allow policy per ability; a new ability is code in `SCOPES` | `app/Access/StarterGovernanceGate.php`, `database/seeders/DatabaseSeeder.php` | `invitations.manage` lives in scope `Action`; see AGENTS.md for the rule |
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
| Topbar and profile menu | Profile dropdown and logout | `starter.layout.topbar_component` (a Livewire component name; swapping it is code) | `resources/views/components/shared/⚡topbar.blade.php` | Logout is route `logout` |
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

## Checks and tooling

| Block | What it does | Set it up via | Code | Note |
| --- | --- | --- | --- | --- |
| Doctor | Checks config keys, routes, layout, event store and tenant baseline | `php artisan starter:doctor` | `app/Console/Commands/StarterDoctor.php` | Also runs `mortelos:spine:check` and `mortelos:outlets:check` |
| Arch guards | Fail on writes outside Actions, `event(new …)`, wrong page paths and raw Flux or HTML | `composer test:arch` | `tests/Feature/ArchGuardTest.php`, `tests/Feature/UiGuardTest.php`, `tests/Feature/OutletsGuardTest.php`, `tests/Feature/CodeLanguageGuardTest.php` | Allow lists may only go down |
| Test helpers | Create roles, policies and users the same way the app does | `tests/Pest.php` | `tests/Support/Governance.php`, `tests/TestCase.php` | Use these instead of inserting rows |
| Browser tests | Screen checks in headless Chromium | `composer test:browser` | `tests/Browser/NavigationVerifyTest.php`, `tests/Browser/UsersInviteTest.php` | Run `npm run build` first |
| Error reporting | Flare with body fields, headers and traces censored | `FLARE_KEY`, `FLARE_REPORT` | `config/flare.php`, `tests/Unit/FlarePrivacyTest.php` | Keep the censor list when adding fields |
| Dutch UI | App locale `nl`, fallback `en`, kit strings in Dutch | `APP_LOCALE`, `APP_FALLBACK_LOCALE` in `.env.example` | `config/app.php`, `tests/Feature/UiLocaleTest.php` | |
| Dependency vetting | Clears new package versions with an agent review | `bin/vet-agent`, `vet.json` | `bin/vet-agent` | A new dependency needs a yes from the user first (AGENTS.md rule 10) |
| Portal workflow | Interview, package decisions, build plan and build for a new portal | Skill `setup-portal` | `.agents/skills/setup-portal/SKILL.md` | Run it first for a portal request |
| Machine setup | Gets a clean machine to the point where a host app boots | Skill `mortelos-tooling-setup` | `.agents/skills/mortelos-tooling-setup/SKILL.md` | |
| Scaffold command | Creates a new portal from the starter without its git history | `bin/mortelos new <name>` | `bin/mortelos` | |
