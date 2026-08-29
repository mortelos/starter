<?php

declare(strict_types=1);

namespace App\Access;

use App\Contracts\GovernanceGate;
use App\Models\Policy;
use Illuminate\Contracts\Auth\Authenticatable;
use Mortel\Access\ActorContextResolver;
use Mortel\Contracts\TenantResolver;

/**
 * Fail-closed governance gate (R7), single-tenant baseline, deny-by-default.
 *
 * Decision order — any miss is a deny:
 *  1. no user                              -> deny (DeniedActor semantics)
 *  2. user has no tenant role with an explicit
 *     `allow` policy for the action        -> deny
 *
 * Roles and policies are DB DATA (D11), editable by the owner via the governance
 * surface — never hardcoded role names, never config-in-code. The absence of an
 * explicit `allow` row is a denial; a `deny` row never grants.
 *
 * Role resolution delegates to the framework so tenant_user remains the single
 * source of truth in both single-tenant and multi-tenant hosts.
 */
final class StarterGovernanceGate implements GovernanceGate
{
    private const MANAGE_GOVERNANCE = 'governance.manage';

    public function __construct(
        private readonly ActorContextResolver $actorContextResolver,
        private readonly TenantResolver $tenantResolver,
    ) {}

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
        if ($user === null) {
            return false;
        }

        $identifier = $user->getAuthIdentifier();
        $userId = is_string($identifier) || is_int($identifier) ? (string) $identifier : null;
        $roleId = $this->actorContextResolver
            ->resolveRole($userId, $this->tenantResolver->id())
            ?->getKey();

        if (! is_string($roleId) && ! is_int($roleId)) {
            return false;
        }

        return Policy::query()
            ->where('role_id', (string) $roleId)
            ->where('action', $action)
            ->where('effect', 'allow')
            ->exists();
    }
}
