<?php

declare(strict_types=1);

namespace App\Access;

/**
 * Explicit "see nothing" actor: the fail-closed default when a person actor
 * cannot be resolved (e.g. a user detached from the tenant between dispatch and
 * execution). MUST be rejected by all access checks rather than silently
 * elevated to SystemActor.
 *
 * Mirrors Mortel\Access\DeniedActor 1:1 (R4). The symmetric complement to
 * SystemActor: a deliberate deny for unresolvable person callers, not a
 * null-fallback.
 */
final class DeniedActor implements AccessActor
{
    public static function instance(): self
    {
        return new self;
    }
}
