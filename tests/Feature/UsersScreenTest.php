<?php

declare(strict_types=1);

use App\Models\Invitation;
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

/** A user whose role may open the users screen and invite. */
function userInviter(): User
{
    $user = User::factory()->create();
    $role = createRole('owner');
    grantPolicy($role, 'users.manage');
    grantPolicy($role, 'invitations.manage');
    attachGovernanceMembership($user, $role, 'admin');

    return $user;
}

it('offers the tenant roles and picks member by default', function (): void {
    actingAs(userInviter());
    $member = createRole('member');

    Livewire::test('pages::users.index')
        ->assertSet('inviteRole', modelKeyString($member))
        ->assertSee('owner');
});

it('invites through the Action and shows the message, the link and the open invitation', function (): void {
    actingAs(userInviter());

    $component = Livewire::test('pages::users.index')
        ->set('inviteEmail', 'nieuw@example.test')
        ->call('invite')
        ->assertSet('inviteEmail', '')
        ->assertSee('nieuw@example.test')
        ->assertSee('Ongedaan maken');
    $component->assertHasNoErrors();

    expect($component->get('resultMessage'))->toContain('Uitnodiging klaargezet')
        ->and($component->get('acceptUrl'))->toStartWith(config()->string('app.url').'/invite/')
        ->and($component->get('pendingUndo'))->not->toBeNull()
        ->and($component->get('pendingInvites'))->toHaveCount(1);
});

it('rejects an invalid email address before calling the Action', function (): void {
    actingAs(userInviter());

    Livewire::test('pages::users.index')
        ->set('inviteEmail', 'no-address')
        ->call('invite')
        ->assertHasErrors(['inviteEmail' => 'email']);

    expect(Invitation::query()->count())->toBe(0);
});

it('shows a failed invitation as an error', function (): void {
    actingAs(userInviter());

    Livewire::test('pages::users.index')
        ->set('inviteEmail', 'nieuw@example.test')
        ->call('invite')
        ->set('inviteEmail', 'nieuw@example.test')
        ->call('invite')
        ->assertSet('errorMessage', 'Er staat al een uitnodiging open voor nieuw@example.test.')
        ->assertSet('resultMessage', null);
});

it('undoes an invitation through the UndoRegistry', function (): void {
    actingAs(userInviter());

    $component = Livewire::test('pages::users.index')
        ->set('inviteEmail', 'nieuw@example.test')
        ->call('invite')
        ->call('undoInvite')
        ->assertSet('pendingUndo', null)
        ->assertSet('acceptUrl', null)
        ->assertSet('pendingInvites', [])
        ->assertDontSee('Ongedaan maken');

    expect($component->get('resultMessage'))->toContain('Uitnodiging voor nieuw@example.test ingetrokken.')
        ->and(Invitation::query()->sole()->revoked_at)->not->toBeNull();
});

it('revokes an open invitation from the list', function (): void {
    actingAs(userInviter());

    $component = Livewire::test('pages::users.index')
        ->set('inviteEmail', 'nieuw@example.test')
        ->call('invite');

    $component->call('revokeInvite', Invitation::query()->sole()->id)
        ->assertSet('pendingInvites', [])
        ->assertSet('pendingUndo', null);

    expect(Invitation::query()->sole()->revoked_at)->not->toBeNull();
});

it('forbids inviting without invitations.manage', function (): void {
    actingAs(usersScreenManager());

    Livewire::test('pages::users.index')
        ->set('inviteEmail', 'nieuw@example.test')
        ->call('invite')
        ->assertForbidden();

    expect(Invitation::query()->count())->toBe(0);
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
