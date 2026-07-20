<?php

declare(strict_types=1);

use App\Models\Policy;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\StarterUsersAccessResolver;
use App\Support\StarterUsersResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function usersScreenManager(): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Owner']);

    Policy::factory()->for($role)->action('users.manage')->allow()->create();
    $user->roles()->attach($role);
    attachUserToDefaultTenant($user, 'admin', roleKeyString($role));

    return $user;
}

it('renders the users page for a user manager', function (): void {
    $manager = usersScreenManager();

    actingAs($manager);

    get(route('users'))
        ->assertOk()
        ->assertSee('Gebruikersbeheer')
        ->assertSee($manager->email);
});

it('redirects users without users.manage to the dashboard', function (): void {
    actingAs(User::factory()->create());

    get(route('users'))->assertRedirect(route('dashboard'));
});

it('opens the user access slide for an inspectable user', function (): void {
    $manager = usersScreenManager();
    $target = User::factory()->create();
    attachUserToDefaultTenant($target);
    $targetKey = userKeyString($target);

    actingAs($manager);

    $component = Livewire::test('pages.users.users');
    $component->call('openUserAccessSlide', $targetKey);
    $component->assertSet('showUserAccessSlide', true);
    $component->assertSet('selectedUserAccessId', $targetKey);
});

it('renders access details for an inspectable user', function (): void {
    $manager = usersScreenManager();
    $target = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Reviewer']);

    Policy::factory()->for($role)->action('dashboard.view')->allow()->create();
    $target->roles()->attach($role);
    attachUserToDefaultTenant($target, 'member', roleKeyString($role));
    $targetKey = userKeyString($target);

    actingAs($manager);

    $component = Livewire::test('users.user-access-slide-over', ['userId' => $targetKey]);
    $component->assertSee($target->email);
    $component->assertSee('Reviewer');
    $component->assertSee('dashboard.view');
});

it('keeps invite creation closed until a membership model is configured', function (): void {
    actingAs(usersScreenManager());

    Livewire::test('pages.users.users')
        ->set('inviteEmail', 'new@example.test')
        ->set('inviteRole', 'member')
        ->call('invite')
        ->assertSet('errorMessage', 'Uitnodigingen zijn nog niet gekoppeld aan een membershipmodel.');
});

it('lists only users with a membership in the configured tenant', function (): void {
    $manager = usersScreenManager();
    $member = User::factory()->create();
    $outsideUser = User::factory()->create();
    attachUserToDefaultTenant($member);

    actingAs($manager);

    Livewire::test('pages.users.users')
        ->assertSee($member->email)
        ->assertDontSee($outsideUser->email);
});

it('does not allow inspecting a user outside the configured tenant', function (): void {
    actingAs(usersScreenManager());
    $outsideUser = User::factory()->create();

    expect(app(StarterUsersAccessResolver::class)->canInspect(userKeyString($outsideUser)))->toBeFalse();
});

it('does not grant user management from a global role without tenant membership', function (): void {
    $outsideManager = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Owner']);

    Policy::factory()->for($role)->action('users.manage')->allow()->create();
    $outsideManager->roles()->attach($role);

    actingAs($outsideManager);

    get(route('users'))->assertRedirect(route('dashboard'));
});

it('reports the tenant membership role instead of a global user role', function (): void {
    actingAs(usersScreenManager());
    $member = User::factory()->create();
    $globalRole = Role::factory()->create(['name' => 'Reviewer']);
    $member->roles()->attach($globalRole);
    attachUserToDefaultTenant($member, 'member');

    $resolvedMember = collect(app(StarterUsersResolver::class)->members())
        ->firstWhere('id', userKeyString($member));

    expect($resolvedMember)->not->toBeNull();
    expect($resolvedMember['role'] ?? null)->toBe('member');
});

function attachUserToDefaultTenant(User $user, string $role = 'member', ?string $roleId = null): void
{
    $configuredTenantId = config('starter.tenancy.default_tenant_id', 'default');

    if (! is_string($configuredTenantId) && ! is_int($configuredTenantId)) {
        throw new RuntimeException('Expected a scalar tenant id.');
    }

    $tenantId = (string) $configuredTenantId;

    Tenant::query()->firstOrCreate(
        ['id' => $tenantId],
        ['data' => ['name' => 'Default workspace']],
    );

    $user->tenants()->attach($tenantId, [
        'role' => $role,
        'role_id' => $roleId,
    ]);
}

function userKeyString(User $user): string
{
    $key = $user->getKey();

    if (! is_int($key) && ! is_string($key)) {
        throw new RuntimeException('Expected a scalar user key.');
    }

    return (string) $key;
}

function roleKeyString(Role $role): string
{
    $key = $role->getKey();

    if (! is_int($key) && ! is_string($key)) {
        throw new RuntimeException('Expected a scalar role key.');
    }

    return (string) $key;
}
