<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mortel\Actions\Policy\CreatePolicy;
use Mortel\Enums\PolicyEffect;
use Mortel\Enums\PolicyScope;
use Mortel\Events\Role\RoleCreated;
use Mortel\Models\Policy;
use Mortel\Models\Role;
use Mortel\Models\UteqStoredEvent;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

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

it('creates a role through the framework action, as a stored event', function (): void {
    actingAs(ownerUser());

    Livewire::test('pages::governance.roles')
        ->set('newRoleName', 'Reviewer')
        ->set('newRoleDescription', 'Mag voorstellen reviewen')
        ->call('createRole')
        ->assertHasNoErrors();

    $role = Role::query()->where('name', 'Reviewer')->firstOrFail();

    expect($role->description)->toBe('Mag voorstellen reviewen');
    expect(UteqStoredEvent::where('aggregate_uuid', modelKeyString($role))->where('event_class', RoleCreated::class)->count())->toBe(1);
});

it('validates a required role name on create', function (): void {
    actingAs(ownerUser());

    Livewire::test('pages::governance.roles')
        ->set('newRoleName', '')
        ->call('createRole')
        ->assertHasErrors(['newRoleName' => 'required']);
});

it('updates a role', function (): void {
    actingAs(ownerUser());
    $role = createRole('Old name');
    $roleKey = modelKeyString($role);

    Livewire::test('pages::governance.roles')
        ->call('startEditRole', $roleKey)
        ->set('editRoleName', 'New name')
        ->call('updateRole')
        ->assertHasNoErrors();

    expect(Role::query()->whereKey($roleKey)->firstOrFail()->name)->toBe('New name');
});

it('deletes a role and its policies', function (): void {
    actingAs(ownerUser());
    $role = createRole('Tijdelijk');
    $policy = grantPolicy($role, 'inbox.manage');

    Livewire::test('pages::governance.roles')
        ->call('deleteRole', modelKeyString($role))
        ->assertHasNoErrors();

    expect(Role::query()->whereKey($role->getKey())->exists())->toBeFalse();
    expect(Policy::query()->whereKey($policy->getKey())->exists())->toBeFalse();
});

it('refuses to delete a role a member still holds and says so on the screen', function (): void {
    actingAs(ownerUser());
    $role = createRole('Bezet');
    attachGovernanceMembership(User::factory()->create(), $role);

    Livewire::test('pages::governance.roles')
        ->call('deleteRole', modelKeyString($role))
        ->assertHasErrors('roles')
        ->assertSee('Deze rol is nog toegewezen aan een gebruiker.');

    expect(Role::query()->whereKey($role->getKey())->exists())->toBeTrue();
});

it('adds a policy to a role in the chosen scope', function (): void {
    actingAs(ownerUser());
    $role = createRole('Reviewer');
    $roleKey = modelKeyString($role);

    Livewire::test('pages::governance.roles')
        ->set("policyAction.{$roleKey}", 'inbox.manage')
        ->set("policyEffect.{$roleKey}", 'allow')
        ->set("policyScope.{$roleKey}", 'role')
        ->call('addPolicy', $roleKey)
        ->assertHasNoErrors();

    expect(roleHasPolicy($role, 'inbox.manage'))->toBeTrue();
    expect(Policy::query()->where('role_id', $roleKey)->firstOrFail()->scope)->toBe('role');
});

it('shows every ability of a policy with more than one', function (): void {
    actingAs(ownerUser());
    $role = createRole('Reviewer');

    app(CreatePolicy::class)->handle(
        name: 'Inbox',
        description: null,
        scope: PolicyScope::Policy,
        resourceType: null,
        resourceId: null,
        roleId: modelKeyString($role),
        actions: ['inbox.read' => 'allow', 'inbox.manage' => 'deny'],
        effect: PolicyEffect::Allow,
        priority: 0,
        conditions: null,
        orgId: governanceTenantId(),
        branchId: governanceBranchId(),
        actor: 'system:test',
    );

    Livewire::test('pages::governance.roles')
        ->assertSee('inbox.read')
        ->assertSee('inbox.manage');
});

it('removes a policy from a role', function (): void {
    actingAs(ownerUser());
    $role = createRole('Reviewer');
    $policy = grantPolicy($role, 'inbox.manage');

    Livewire::test('pages::governance.roles')
        ->call('deletePolicy', modelKeyString($policy))
        ->assertHasNoErrors();

    expect(Policy::query()->whereKey($policy->getKey())->exists())->toBeFalse();
});
