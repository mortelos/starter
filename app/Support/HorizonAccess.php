<?php

declare(strict_types=1);

namespace App\Support;

use App\Access\StarterGovernanceGate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may open /horizon: a user whose role has an allow policy for `horizon.view` (scope Action, like the other host
 * abilities in StarterGovernanceGate::SCOPES). Deny by default: no user, no role or no policy is a no. There is no
 * e-mail list and no local bypass.
 */
final class HorizonAccess
{
    public const string ABILITY = 'horizon.view';

    public static function allows(?Authenticatable $user): bool
    {
        return app(StarterGovernanceGate::class)->allows($user, self::ABILITY);
    }
}
