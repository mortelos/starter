<?php

declare(strict_types=1);

use App\Models\Policy;
use App\Models\Role;
use App\Models\User;
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

function userKeyString(User $user): string
{
    $key = $user->getKey();

    if (! is_int($key) && ! is_string($key)) {
        throw new RuntimeException('Expected a scalar user key.');
    }

    return (string) $key;
}
