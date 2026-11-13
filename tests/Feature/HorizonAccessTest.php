<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\HorizonAccess;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Mortel\Enums\PolicyEffect;
use Mortel\Models\Policy;
use Mortel\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

/** A user whose role has the given abilities allowed. */
function userAllowed(string ...$abilities): User
{
    $user = User::factory()->create();
    $role = createRole('role-'.implode('-', $abilities).'-'.fake()->unique()->numberBetween(1, 9999));

    foreach ($abilities as $ability) {
        grantPolicy($role, $ability);
    }

    attachGovernanceMembership($user, $role);

    return $user;
}

it('refuses a guest at /horizon', function (): void {
    get('/horizon')->assertForbidden();
});

it('refuses a signed-in user without a role', function (): void {
    $user = User::factory()->create();
    attachGovernanceMembership($user, null);

    actingAs($user)->get('/horizon')->assertForbidden();
});

it('refuses a user whose role has other abilities but not horizon.view', function (): void {
    actingAs(userAllowed('governance.manage', 'users.manage', 'invitations.manage'))->get('/horizon')->assertForbidden();
});

it('refuses a role with a deny policy for horizon.view', function (): void {
    $user = User::factory()->create();
    $role = createRole('denied');
    grantPolicy($role, 'horizon.view', PolicyEffect::Deny);
    attachGovernanceMembership($user, $role);

    actingAs($user)->get('/horizon')->assertForbidden();
});

it('lets a user in whose role is allowed horizon.view', function (): void {
    actingAs(userAllowed('horizon.view'))->get('/horizon')->assertOk();
});

it('keeps the door shut on a local machine too, where Horizon would let everyone in', function (): void {
    app()->instance('env', 'local');

    get('/horizon')->assertForbidden();
    actingAs(User::factory()->create())->get('/horizon')->assertForbidden();
    actingAs(userAllowed('horizon.view'))->get('/horizon')->assertOk();
});

it('decides through the gate and the governance policy, with no list of e-mail addresses', function (): void {
    $allowed = userAllowed('horizon.view');
    $other = userAllowed('users.manage');

    expect(Gate::forUser($allowed)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($other)->allows('viewHorizon'))->toBeFalse()
        ->and(Gate::allows('viewHorizon'))->toBeFalse()
        ->and(HorizonAccess::allows(null))->toBeFalse();
});

it('gives the seeded owner access to Horizon', function (): void {
    seed(DatabaseSeeder::class);

    actingAs(User::query()->where('email', 'admin@example.test')->firstOrFail())->get('/horizon')->assertOk();
});

/** Runs the migration that gives an existing owner role Horizon access. */
function grantHorizonMigration(): void
{
    $migration = require database_path('migrations/2026_11_13_100000_grant_horizon_access.php');
    $migration->up();
}

it('gives an owner role that exists from before Horizon the access, once', function (): void {
    $owner = createRole('owner');
    grantPolicy($owner, 'users.manage');
    $member = createRole('member');

    grantHorizonMigration();
    grantHorizonMigration();

    expect(roleHasPolicy($owner, 'horizon.view'))->toBeTrue()
        ->and(roleHasPolicy($member, 'horizon.view'))->toBeFalse()
        ->and(Policy::query()->where('role_id', modelKeyString($owner))->count())->toBe(2);
    expect(Role::query()->count())->toBe(2);
});

it('does nothing when there is no owner role yet, as on a new install', function (): void {
    grantHorizonMigration();

    expect(Policy::query()->count())->toBe(0);
});
