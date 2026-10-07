<?php

declare(strict_types=1);

namespace App\Access;

use App\Contracts\GovernanceGate;
use Illuminate\Contracts\Auth\Authenticatable;
use Mortel\Access\ActorContextResolver;
use Mortel\Actions\Policies\CheckPolicy;
use Mortel\Contracts\TenantResolver;
use Mortel\Enums\PolicyScope;

/**
 * Fail-closed governance gate (R7), single-tenant baseline, deny-by-default.
 *
 * Decision order; any miss is a deny:
 *  1. no user                                       -> deny
 *  2. no role on the tenant membership (tenant_user) -> deny
 *  3. no explicit `allow` policy for the ability     -> deny
 *
 * Roles and policies are data (D11), edited by the owner on the governance
 * screen through Mortel\Actions\Role and Mortel\Actions\Policy. Role resolution
 * and the policy check are the framework's own (ActorContextResolver and
 * CheckPolicy), so this host adds no second model of authorization.
 */
final class StarterGovernanceGate implements GovernanceGate
{
    private const MANAGE_GOVERNANCE = 'governance.manage';

    /**
     * The policy scope each host ability lives in. The framework stores abilities
     * per scope in the json column `actions`; CheckPolicy only sees a policy in
     * the scope it asks for, so screens, seeder and tests use this one map.
     *
     * @var array<string, PolicyScope>
     */
    public const SCOPES = [
        'governance.manage' => PolicyScope::Policy,
        'users.manage' => PolicyScope::Role,
    ];

    public function __construct(
        private readonly ActorContextResolver $actorContextResolver,
        private readonly TenantResolver $tenantResolver,
        private readonly CheckPolicy $checkPolicy,
    ) {}

    public function canManage(?Authenticatable $user): bool
    {
        return $this->allows($user, self::MANAGE_GOVERNANCE);
    }

    /**
     * Deny-by-default ability check over the owner-editable policy data. The
     * contract only exposes canManage(); the users surface asks for
     * `users.manage` through this method on the bound concrete instance.
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

        return $this->checkPolicy->check((string) $roleId, $action, self::scopeFor($action))->allowed;
    }

    public static function scopeFor(string $action): PolicyScope
    {
        return self::SCOPES[$action] ?? PolicyScope::Policy;
    }
}
