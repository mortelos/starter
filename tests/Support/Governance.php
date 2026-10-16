<?php

declare(strict_types=1);

use App\Access\StarterGovernanceGate;
use App\Models\Tenant;
use App\Models\User;
use Mortel\Actions\Policy\CreatePolicy;
use Mortel\Actions\Role\CreateRole;
use Mortel\Enums\PolicyEffect;
use Mortel\Enums\PolicyScope;
use Mortel\Models\Policy;
use Mortel\Models\Role;

/*
 * Test helpers for roles and policies. Everything goes through the framework Actions (so
 * through the aggregates and projectors), exactly like the screens and the seeder do.
 */

function governanceTenantId(): string
{
    $id = config('starter.tenancy.default_tenant_id', 'default');

    return is_string($id) || is_int($id) ? (string) $id : 'default';
}

function governanceBranchId(): string
{
    $id = config('starter.tenancy.default_branch_id', 'main');

    return is_string($id) ? $id : 'main';
}

function createRole(string $name, ?string $description = null): Role
{
    $id = app(CreateRole::class)->handle(
        name: $name,
        description: $description,
        trustConfig: [],
        scope: ['all_branches' => true],
        orgId: governanceTenantId(),
        branchId: governanceBranchId(),
        actor: 'system:test',
    );

    return Role::query()->findOrFail($id);
}

function grantPolicy(Role $role, string $ability, PolicyEffect $effect = PolicyEffect::Allow, ?PolicyScope $scope = null): Policy
{
    $id = app(CreatePolicy::class)->handle(
        name: $ability,
        description: null,
        scope: $scope ?? StarterGovernanceGate::scopeFor($ability),
        resourceType: null,
        resourceId: null,
        roleId: modelKeyString($role),
        actions: [$ability => $effect->value],
        effect: $effect,
        priority: 0,
        conditions: null,
        orgId: governanceTenantId(),
        branchId: governanceBranchId(),
        actor: 'system:test',
    );

    return Policy::query()->findOrFail($id);
}

function attachGovernanceMembership(User $user, ?Role $role, string $pivotRole = 'member'): void
{
    $tenantId = governanceTenantId();

    Tenant::query()->firstOrCreate(
        ['id' => $tenantId],
        ['data' => ['name' => 'Default workspace']],
    );

    $user->tenants()->attach($tenantId, [
        'role' => $pivotRole,
        'role_id' => $role === null ? null : modelKeyString($role),
    ]);
}

/** A user with a role that is allowed governance.manage. */
function ownerUser(): User
{
    $user = User::factory()->create();
    $role = createRole('owner');
    grantPolicy($role, 'governance.manage');
    attachGovernanceMembership($user, $role, 'admin');

    return $user;
}

/** A user with a role that is allowed users.manage. */
function usersScreenManager(): User
{
    $user = User::factory()->create();
    $role = createRole('owner');
    grantPolicy($role, 'users.manage');
    attachGovernanceMembership($user, $role, 'admin');

    return $user;
}

function modelKeyString(Role|Policy|User $model): string
{
    $key = $model->getKey();

    if (! is_int($key) && ! is_string($key)) {
        throw new RuntimeException('Expected a scalar model key.');
    }

    return (string) $key;
}

/** Whether a policy of this role holds the ability with this effect (the json column `actions`). */
function roleHasPolicy(Role $role, string $ability, string $effect = 'allow'): bool
{
    return Policy::query()
        ->where('role_id', modelKeyString($role))
        ->get()
        ->contains(fn (Policy $policy): bool => is_array($policy->actions) && ($policy->actions[$ability] ?? null) === $effect);
}

/** A user with the role 'owner' that is allowed invitations.manage. */
function invitationsManager(): User
{
    $user = User::factory()->create();
    $role = createRole('owner');
    grantPolicy($role, 'invitations.manage');
    attachGovernanceMembership($user, $role, 'admin');

    return $user;
}
