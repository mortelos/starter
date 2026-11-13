<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Contracts\ChangeLog\ChangeLogAgents;

/**
 * Default of the ChangeLogAgents outlet: an agent is "Agent" and each run is its own agent, a bridge is named after
 * its channel. No agent filter. Bind your own class to name your integrations and say for whom they acted.
 */
final class GenericChangeLogAgents implements ChangeLogAgents
{
    public function agents(): array
    {
        return [];
    }

    public function identify(array $actorIds): array
    {
        $identities = [];

        foreach ($actorIds as $actorId) {
            $identities[$actorId] = str_starts_with($actorId, 'bridge:')
                ? new ChangeLogAgentIdentity($actorId, ucfirst(substr($actorId, 7)))
                : new ChangeLogAgentIdentity($actorId, 'Agent');
        }

        return $identities;
    }
}
