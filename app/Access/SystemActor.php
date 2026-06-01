<?php

declare(strict_types=1);

namespace App\Access;

/**
 * Explicit "system/maintenance call — bypass person-filtering" actor.
 *
 * Mirrors Mortel\Access\SystemActor 1:1 (R4). Never the default; callers
 * construct it deliberately for console commands or background jobs running
 * outside a user request, where tenant isolation is already enforced elsewhere.
 */
final class SystemActor implements AccessActor
{
    public static function instance(): self
    {
        return new self;
    }
}
