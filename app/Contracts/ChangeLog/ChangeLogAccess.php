<?php

declare(strict_types=1);

namespace App\Contracts\ChangeLog;

use Illuminate\Contracts\Auth\Authenticatable;
use Mortel\Enums\TrustLevel;

/**
 * Contract outlet: what a user may do with the change log rows of one part ("onderdeel"). Null hides the part's rows;
 * Observe shows them; Act also lets the user undo a row whose counter-Action names no ability of its own. The default
 * (App\Support\ChangeLog\ViewersSeeEverything) shows every part to whoever may open the page.
 */
interface ChangeLogAccess
{
    public function level(Authenticatable $user, string $part): ?TrustLevel;
}
