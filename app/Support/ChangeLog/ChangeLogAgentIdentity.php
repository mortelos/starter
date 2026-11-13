<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

/**
 * Who one agent or bridge actor is: the key that makes two runs the same agent (an agent may only undo its own
 * changes), the name users read, and the user it acted for.
 */
final readonly class ChangeLogAgentIdentity
{
    public function __construct(
        public string $key,
        public string $name,
        public ?string $onBehalfOfUserId = null,
    ) {}
}
