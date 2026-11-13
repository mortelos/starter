<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Access\StarterGovernanceGate;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may open the change log and its CSV export: a user whose role has an allow policy for `changelog.view` (scope
 * Action, like the other host abilities in StarterGovernanceGate::SCOPES). Deny by default.
 */
final class ChangeLogAccessCheck
{
    public const string ABILITY = 'changelog.view';

    public static function allows(?Authenticatable $user): bool
    {
        return app(StarterGovernanceGate::class)->allows($user, self::ABILITY);
    }
}
