<?php

declare(strict_types=1);

namespace App\Contracts\ChangeLog;

use App\Support\ChangeLog\ChangeLogAgent;
use App\Support\ChangeLog\ChangeLogAgentIdentity;

/**
 * Contract outlet: who an agent or bridge actor in the change log is. The framework stores `agent:<run-id>` and
 * `bridge:<name>`; the app knows which integration a run belongs to and for whom it acted. The default
 * (App\Support\ChangeLog\GenericChangeLogAgents) calls every agent "Agent" and offers no agent filter.
 */
interface ChangeLogAgents
{
    /**
     * The agents the "Wie" filter offers, each with the actor prefix its stored events start with.
     *
     * @return list<ChangeLogAgent>
     */
    public function agents(): array;

    /**
     * Name, agent key and on-behalf-of user of each actor id, in one go for a whole page.
     *
     * @param  list<string>  $actorIds  ids starting with `agent:` or `bridge:`
     * @return array<string, ChangeLogAgentIdentity> by actor id; a missing id falls back to the default name
     */
    public function identify(array $actorIds): array;
}
