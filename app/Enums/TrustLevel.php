<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tenant-scoped trust level for a role.
 *
 * Mirrors Mortel\Enums\TrustLevel 1:1 (R4): same backed values and ordering, so
 * find-replace adoption works and a role's `trust_config` is portable to the
 * framework unchanged.
 *
 *  - Observe : read-only.
 *  - Propose : may draft/queue actions for approval.
 *  - Act     : may execute actions directly.
 */
enum TrustLevel: string
{
    case Observe = 'observe';
    case Propose = 'propose';
    case Act = 'act';

    public function isHigherThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    public function rank(): int
    {
        return match ($this->value) {
            self::Observe->value => 0,
            self::Propose->value => 1,
            self::Act->value => 2,
        };
    }
}
