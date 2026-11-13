<?php

declare(strict_types=1);

namespace App\Enums;

/** Who made a change, as the change log groups it: a person, an agent (or bridge) or the system. */
enum ChangeLogActorKind: string
{
    case Person = 'person';
    case Agent = 'agent';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Persoon',
            self::Agent => 'Agent',
            self::System => 'Systeem',
        };
    }
}
