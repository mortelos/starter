<?php

declare(strict_types=1);

namespace App\Access;

use App\Contracts\GovernanceGate;
use App\Models\Policy;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Fail-closed governance gate (R7), single-tenant baseline, deny-by-default.
 *
 * Decision order — any miss is a deny:
 *  1. no user                              -> deny (DeniedActor semantics)
 *  2. user has no role with an explicit
 *     `allow` policy for the action        -> deny
 *
 * Roles and policies are DB DATA (D11), editable by the owner via the governance
 * surface — never hardcoded role names, never config-in-code. The absence of an
 * explicit `allow` row is a denial; a `deny` row never grants.
 *
 * Mirrors the host-check shape of the framework-aligned TenantGovernanceGate
 * (`hostAllows`), minus tenancy/tenant scoping. The add-tenancy skill detects
 * this gate and swaps it for the tenant-scoped variant when multi-tenancy is
 * added.
 */
final class StarterGovernanceGate implements GovernanceGate
{
    private const MANAGE_GOVERNANCE = 'governance.manage';

    public function canManage(?Authenticatable $user): bool
    {
        return $this->allows($user, self::MANAGE_GOVERNANCE);
    }

    /**
     * Generic deny-by-default ability check over the owner-editable policy data.
     *
     * The contract only exposes canManage(); this parameterized method lets the
     * users surface ask for `users.manage` against the same role/policy store
     * (resolved on the bound concrete instance via method_exists).
     */
    public function allows(?Authenticatable $user, string $action): bool
    {
        if ($user === null || ! method_exists($user, 'roles')) {
            return false;
        }

        $roleIds = $user->roles()->pluck('roles.id')->all();

        if ($roleIds === []) {
            return false;
        }

        return Policy::query()
            ->whereIn('role_id', $roleIds)
            ->where('action', $action)
            ->where('effect', 'allow')
            ->exists();
    }
}
