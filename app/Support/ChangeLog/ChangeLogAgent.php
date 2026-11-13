<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

/** An agent the "Wie" filter offers: its key, the name users read and the actor prefix its events carry. */
final readonly class ChangeLogAgent
{
    public function __construct(
        public string $key,
        public string $name,
        public string $actorPrefix,
    ) {}
}
