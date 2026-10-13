<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\StarterUsersAccessResolver;
use App\Support\StarterUsersResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

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
    attachGovernanceMembership($target, null);
    $targetKey = modelKeyString($target);

    actingAs($manager);

    $component = Livewire::test('pages::users.index');
    $component->call('openUserAccessSlide', $targetKey);
    $component->assertSet('showUserAccessSlide', true);
    $component->assertSet('selectedUserAccessId', $targetKey);
});

it('renders access details for an inspectable user from the membership role', function (): void {
    $manager = usersScreenManager();
    $target = User::factory()->create();
    $role = createRole('Reviewer');
    grantPolicy($role, 'dashboard.view');
    attachGovernanceMembership($target, $role);
    $targetKey = modelKeyString($target);

    actingAs($manager);

    $component = Livewire::test('users.user-access-slide-over', ['userId' => $targetKey]);
    $component->assertSee($target->email);
    $component->assertSee('Reviewer');
    $component->assertSee('dashboard.view');
});

it('keeps invite creation closed until a membership model is configured', function (): void {
    actingAs(usersScreenManager());

    Livewire::test('pages::users.index')
        ->set('inviteEmail', 'new@example.test')
        ->set('inviteRole', 'member')
        ->call('invite')
        ->assertSet('errorMessage', 'Uitnodigingen zijn nog niet gekoppeld aan een membershipmodel.');
});

it('lists only users with a membership in the configured tenant', function (): void {
    $manager = usersScreenManager();
    $member = User::factory()->create();
    $outsideUser = User::factory()->create();
    attachGovernanceMembership($member, null);

    actingAs($manager);

    Livewire::test('pages::users.index')
        ->assertSee($member->email)
        ->assertDontSee($outsideUser->email);
});

it('does not allow inspecting a user outside the configured tenant', function (): void {
    actingAs(usersScreenManager());
    $outsideUser = User::factory()->create();

    expect(app(StarterUsersAccessResolver::class)->canInspect(modelKeyString($outsideUser)))->toBeFalse();
});

it('does not grant user management without a tenant membership', function (): void {
    $outsideManager = User::factory()->create();
    $role = createRole('Owner');
    grantPolicy($role, 'users.manage');

    actingAs($outsideManager);

    get(route('users'))->assertRedirect(route('dashboard'));
});

it('reports the tenant membership role', function (): void {
    actingAs(usersScreenManager());
    $member = User::factory()->create();
    attachGovernanceMembership($member, null, 'member');

    $resolvedMember = collect(app(StarterUsersResolver::class)->members())
        ->firstWhere('id', modelKeyString($member));

    expect($resolvedMember)->not->toBeNull();
    expect($resolvedMember['role'] ?? null)->toBe('member');
});
