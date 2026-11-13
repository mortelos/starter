<?php

declare(strict_types=1);

namespace Tests\Support\ChangeLog;

use App\Contracts\ChangeLog\ChangeLogAccess;
use App\Contracts\ChangeLog\ChangeLogAgents;
use App\Contracts\ChangeLog\ChangeLogArchive;
use App\Support\ChangeLog\ChangeLogAgent;
use App\Support\ChangeLog\ChangeLogAgentIdentity;
use App\Support\ChangeLog\ChangeLogArchiveEntry;
use App\Support\ChangeLog\ChangeLogArchivePage;
use App\Support\ChangeLog\ChangeLogFilter;
use Illuminate\Contracts\Auth\Authenticatable;
use Mortel\Enums\PolicyScope;
use Mortel\Enums\TrustLevel;

/** A counter-Action that names its ability, like every host Action. */
final class RevertThing
{
    public const ABILITY = 'things.manage';
}

/** A counter-Action without an ability of its own (like the framework's generic own-table undo). */
final class RevertWithoutAbility {}

/** A counter-Action with a requires() in the shape of a PolicyRequirement. */
final class RevertWithRequirement
{
    public function requires(): object
    {
        return new class
        {
            public string $action = 'things.manage';

            public PolicyScope $scope = PolicyScope::Policy;
        };
    }
}

/** Access per part from a plain map; a part missing from the map is hidden. */
final class MapAccess implements ChangeLogAccess
{
    /** @param  array<string, TrustLevel>  $levels */
    public function __construct(public array $levels) {}

    public function level(Authenticatable $user, string $part): ?TrustLevel
    {
        return $this->levels[$part] ?? null;
    }
}

/** One named agent, "Koppeling", whose runs start with agent:link- and act for the user in the run id. */
final class LinkAgents implements ChangeLogAgents
{
    public function agents(): array
    {
        return [new ChangeLogAgent('link', 'de koppeling', 'agent:link-')];
    }

    public function identify(array $actorIds): array
    {
        $identities = [];

        foreach ($actorIds as $id) {
            $identities[$id] = str_starts_with($id, 'agent:link-')
                ? new ChangeLogAgentIdentity('link', 'de koppeling', explode('-', $id)[1] ?? null)
                : new ChangeLogAgentIdentity($id, 'Agent');
        }

        return $identities;
    }
}

/** An archive held in memory, filtered on part and period like a real source would. */
final class MemoryArchive implements ChangeLogArchive
{
    /** @param  list<ChangeLogArchiveEntry>  $entries */
    public function __construct(public array $entries) {}

    public function entries(ChangeLogFilter $filter, array $parts, ?string $after, int $limit): ChangeLogArchivePage
    {
        $rows = array_values(array_filter($this->entries, fn (ChangeLogArchiveEntry $entry): bool => in_array($entry->part, $parts, true)
            && ($filter->part === '' || $filter->part === $entry->part)
            && ($filter->from === null || $entry->at->gte($filter->from))
            && ($filter->to === null || $entry->at->lte($filter->to))));
        $offset = $after === null ? 0 : (int) $after;
        $page = array_slice($rows, $offset, $limit);

        return new ChangeLogArchivePage($page, $offset + $limit < count($rows) ? (string) ($offset + $limit) : null);
    }
}
