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

/**
 * Create a user that owns the governance.manage allow policy.
 */
function ownerUser(): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create(['name' => 'Owner']);
    Policy::factory()->for($role)->action('governance.manage')->allow()->create();
    $user->roles()->attach($role);

    return $user;
}

it('renders the roles screen for an owner', function (): void {
    actingAs(ownerUser());

    get(route('governance.roles'))->assertOk();
});

it('returns 403 for a user without governance.manage', function (): void {
    actingAs(User::factory()->create());

    get(route('governance.roles'))->assertForbidden();
});

it('renders the governance page for an owner without a configured resolver', function (): void {
    // Wiring the gate so owners pass must not surface the optional-resolver
    // LogicException; the owner reaches the empty state + the roles link.
    actingAs(ownerUser());

    get(route('governance'))->assertOk();
});

it('redirects guests to login', function (): void {
    get(route('governance.roles'))->assertRedirect(route('login'));
});

it('creates a role', function (): void {
    actingAs(ownerUser());

    Livewire::test('pages.governance.roles')
        ->set('newRoleName', 'Reviewer')
        ->set('newRoleDescription', 'Mag voorstellen reviewen')
        ->call('createRole')
        ->assertHasNoErrors();

    expect(Role::query()->where('name', 'Reviewer')->exists())->toBeTrue();
});

it('validates a required role name on create', function (): void {
    actingAs(ownerUser());

    Livewire::test('pages.governance.roles')
        ->set('newRoleName', '')
        ->call('createRole')
        ->assertHasErrors(['newRoleName' => 'required']);
});

it('updates a role', function (): void {
    actingAs(ownerUser());
    $role = Role::factory()->create(['name' => 'Old name']);
    $roleKey = modelKeyString($role);

    Livewire::test('pages.governance.roles')
        ->call('startEditRole', $roleKey)
        ->set('editRoleName', 'New name')
        ->call('updateRole')
        ->assertHasNoErrors();

    $freshRole = Role::query()->whereKey($roleKey)->firstOrFail();

    expect($freshRole->name)->toBe('New name');
});

it('deletes a role and its policies', function (): void {
    actingAs(ownerUser());
    $role = Role::factory()->create();
    $policy = Policy::factory()->for($role)->create();

    Livewire::test('pages.governance.roles')
        ->call('deleteRole', $role->getKey());

    expect(Role::query()->whereKey($role->getKey())->exists())->toBeFalse();
    expect(Policy::query()->whereKey($policy->getKey())->exists())->toBeFalse();
});

it('adds a policy to a role', function (): void {
    actingAs(ownerUser());
    $role = Role::factory()->create();
    $roleKey = modelKeyString($role);

    Livewire::test('pages.governance.roles')
        ->set("policyAction.{$roleKey}", 'inbox.manage')
        ->set("policyEffect.{$roleKey}", 'allow')
        ->call('addPolicy', $roleKey)
        ->assertHasNoErrors();

    expect(Policy::query()
        ->where('role_id', $role->getKey())
        ->where('action', 'inbox.manage')
        ->where('effect', 'allow')
        ->exists())->toBeTrue();
});

function modelKeyString(Role|Policy $model): string
{
    $key = $model->getKey();

    if (! is_int($key) && ! is_string($key)) {
        throw new RuntimeException('Expected a scalar model key.');
    }

    return (string) $key;
}

it('removes a policy from a role', function (): void {
    actingAs(ownerUser());
    $role = Role::factory()->create();
    $policy = Policy::factory()->for($role)->create();

    Livewire::test('pages.governance.roles')
        ->call('deletePolicy', $policy->getKey());

    expect(Policy::query()->whereKey($policy->getKey())->exists())->toBeFalse();
});
