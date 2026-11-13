<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Contracts\ChangeLog\ChangeLogAccess;
use Illuminate\Contracts\Auth\Authenticatable;
use Mortel\Enums\TrustLevel;

/**
 * Default of the ChangeLogAccess outlet: whoever may open the change log (`changelog.view`) sees every part. Undo
 * still needs the ability of the counter-Action; a counter-Action without one needs a level Act from your own class.
 */
final class ViewersSeeEverything implements ChangeLogAccess
{
    public function level(Authenticatable $user, string $part): TrustLevel
    {
        return TrustLevel::Observe;
    }
}
