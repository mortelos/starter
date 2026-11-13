<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Contracts\ChangeLog\ChangeLogAccess;
use App\Contracts\ChangeLog\ChangeLogAgents;
use App\Contracts\ChangeLog\ChangeLogArchive;
use App\Enums\ChangeLogActorKind;
use App\Enums\ChangeLogUndoState;
use App\Events\ChangeLog\ChangeUndone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Mortel\Actions\Undo;
use Mortel\Actions\UndoRegistry;
use Mortel\Contracts\TenantResolver;
use Mortel\Enums\TrustLevel;
use Mortel\Support\Actor;
use Mortel\Support\Scalar;
use UnexpectedValueException;

/**
 * Reads the change log from `events`: filtered and hidden parts in SQL, newest first with a cursor on
 * (created_at, id), and per page one query for the members (the actor names and the "Wie" filter) and one for the
 * undone rows. Reads only. Bound per request (scoped), so the page and its filter share the member list.
 */
final class ChangeLogReader
{
    public const int PAGE_SIZE = 50;

    private const int EXPORT_CHUNK = 500;

    // Not a request parameter: with a null cursor Laravel would otherwise read one from the request.
    private const string CURSOR_NAME = 'changelog_internal_cursor';

    private const array COLUMNS = ['id', 'event_type', 'aggregate_uuid', 'aggregate_version', 'actor', 'reason', 'created_at', 'event_properties'];

    /** @var array<string, string>|null the members of the tenant, read once per request (people() and actor names) */
    private ?array $members = null;

    public function __construct(
        private readonly ChangeLogDescriptions $descriptions,
        private readonly ChangeLogAgents $agents,
        private readonly ChangeLogAccess $access,
        private readonly ChangeLogArchive $archive,
        private readonly UndoRegistry $undoRegistry,
        private readonly UndoPermission $permission,
    ) {}

    /** @return array<string, TrustLevel> the parts this user may see, with their level */
    public function levels(?Authenticatable $user): array
    {
        if ($user === null) {
            return [];
        }

        $levels = [];

        foreach (array_keys($this->descriptions->parts()) as $part) {
            $level = $this->access->level($user, $part);

            if ($level !== null) {
                $levels[$part] = $level;
            }
        }

        return $levels;
    }

    /**
     * @param  array<string, TrustLevel>  $levels
     * @param  array{0: string, 1: string}|null  $bound  [created_at, id] of the newest event to show (inclusive):
     *                                                   the anchor of an open page, or a linked event
     */
    public function page(Actor $viewer, array $levels, ChangeLogFilter $filter, ?string $cursor = null, ?array $bound = null, ?string $archiveCursor = null): ChangeLogPage
    {
        $query = $this->query($filter, $levels);

        if ($bound !== null) {
            [$at, $id] = $bound;
            $query->where(fn (Builder $where) => $where->where('created_at', '<', $at)
                ->orWhere(fn (Builder $same) => $same->where('created_at', $at)->where('id', '<=', $id)));
        }

        try {
            $paginator = (clone $query)->cursorPaginate(self::PAGE_SIZE, ['*'], self::CURSOR_NAME, $cursor !== null ? Cursor::fromEncoded($cursor) : null);
        } catch (UnexpectedValueException) {
            // A cursor from an old or edited address: start at the newest instead of failing.
            $paginator = $query->cursorPaginate(self::PAGE_SIZE, ['*'], self::CURSOR_NAME, null);
        }
        $entries = array_map(ChangeLogEntry::fromRow(...), array_values($paginator->items()));
        $older = $paginator->nextCursor()?->encode();
        $archive = [];
        $archiveOlder = null;

        if ($older === null) {
            $archivePage = $this->archive->entries($filter, array_keys($levels), $archiveCursor, self::PAGE_SIZE);
            $archive = $this->archiveRows($archivePage->entries, $levels);
            $archiveOlder = $archivePage->next;
        }

        $first = $paginator->items()[0] ?? null;
        $newest = is_object($first) ? [Scalar::string($first->created_at ?? ''), Scalar::string($first->id ?? '')] : null;

        return new ChangeLogPage($this->rows($viewer, $levels, $entries), $older, $paginator->previousCursor()?->encode(), $archive, $archiveOlder, $newest);
    }

    /**
     * Every row the filter shows, stored events first and then the archive, for the CSV export.
     *
     * @param  array<string, TrustLevel>  $levels
     * @return Generator<int, ChangeLogRow>
     */
    public function all(Actor $viewer, array $levels, ChangeLogFilter $filter): Generator
    {
        $cursor = null;

        do {
            $paginator = $this->query($filter, $levels)->cursorPaginate(self::EXPORT_CHUNK, ['*'], self::CURSOR_NAME, $cursor);
            yield from $this->rows($viewer, $levels, array_map(ChangeLogEntry::fromRow(...), array_values($paginator->items())));
            $cursor = $paginator->nextCursor();
        } while ($cursor !== null);

        $after = null;

        do {
            $page = $this->archive->entries($filter, array_keys($levels), $after, self::EXPORT_CHUNK);
            yield from $this->archiveRows($page->entries, $levels);
            $after = $page->next;
        } while ($after !== null && $page->entries !== []);
    }

    /** @return array{0: string, 1: string}|null [created_at, id] of an event, to start a page at it */
    public function boundOf(string $id): ?array
    {
        $at = DB::table('events')->where('id', $id)->value('created_at');

        return $at === null ? null : [Scalar::string($at), $id];
    }

    public function entry(string $id): ?ChangeLogEntry
    {
        $row = DB::table('events')->select(self::COLUMNS)->where('id', $id)->first();

        return $row === null ? null : ChangeLogEntry::fromRow($row);
    }

    /** The id of the event that undid this one, or null. */
    public function undoneBy(string $id): ?string
    {
        return $this->undoneMap([$id])[$id] ?? null;
    }

    /**
     * What the change log offers for one change: its description, the undo state, a note and the Undo itself.
     *
     * @return array{description: ChangeDescription, part: ChangePart, state: ChangeLogUndoState, note: ?string, undo: ?Undo}
     */
    public function assess(Actor $viewer, ChangeLogEntry $entry, ?TrustLevel $level, ?string $undoneBy): array
    {
        $description = $this->descriptions->describe($entry);
        $undo = $description->undo;

        [$state, $note] = match (true) {
            $undoneBy !== null => [ChangeLogUndoState::Undone, 'Ongedaan gemaakt'],
            $undo === null => [ChangeLogUndoState::Unavailable, $description->noUndoReason],
            ! $this->undoRegistry->knows($undo->action) => [ChangeLogUndoState::Unavailable, 'Hiervoor is geen terugdraaiactie ingesteld.'],
            $undo->isExpired() => [ChangeLogUndoState::Unavailable, 'De termijn om dit ongedaan te maken is voorbij.'],
            default => ($refusal = $this->permission->refusal($viewer, $entry, $undo, $level)) === null
                ? [ChangeLogUndoState::Available, null]
                : [ChangeLogUndoState::Unavailable, $refusal],
        };

        return ['description' => $description, 'part' => $this->descriptions->partOf($entry->type), 'state' => $state, 'note' => $note, 'undo' => $undo];
    }

    /** @return array<string, string> user id => name, the members of the tenant for the "Wie" filter */
    public function people(): array
    {
        if ($this->members !== null) {
            return $this->members;
        }

        $names = DB::table('users')
            ->join('tenant_user', 'tenant_user.user_id', '=', 'users.id')
            ->where('tenant_user.tenant_id', app(TenantResolver::class)->id())
            ->orderBy('users.name')
            ->pluck('users.name', 'users.id')
            ->all();

        $this->members = [];

        foreach ($names as $id => $name) {
            $this->members[(string) $id] = Scalar::string($name);
        }

        return $this->members;
    }

    /** @return list<ChangeLogAgent> */
    public function agents(): array
    {
        return $this->agents->agents();
    }

    /**
     * @param  list<ChangeLogRow>  $rows
     * @return array<string, non-empty-list<ChangeLogRow>> day label => rows, in the order given
     */
    public static function byDay(array $rows): array
    {
        $days = [];

        foreach ($rows as $row) {
            $days[self::dayLabel($row->at)][] = $row;
        }

        return $days;
    }

    public static function dayLabel(CarbonImmutable $at): string
    {
        // Day and month names follow the app locale (nl), which Laravel also sets on Carbon.
        $day = $at->setTimezone(config()->string('app.timezone'));
        $today = CarbonImmutable::now(config()->string('app.timezone'));

        return match (true) {
            $day->isSameDay($today) => 'Vandaag',
            $day->isSameDay($today->subDay()) => 'Gisteren',
            default => ucfirst($day->translatedFormat('l j F Y')),
        };
    }

    /** @param  array<string, TrustLevel>  $levels */
    private function query(ChangeLogFilter $filter, array $levels): Builder
    {
        $query = DB::table('events')->select(self::COLUMNS)->orderByDesc('created_at')->orderByDesc('id');
        $registered = $this->descriptions->registeredTypes();

        // Hidden parts, in SQL, so a page is always full.
        $visible = array_merge([], ...array_map($this->descriptions->typesOf(...), array_keys($levels)));

        if (isset($levels[ChangeLogDescriptions::OTHER])) {
            $this->whereTypeNotIn($query, array_values(array_diff($registered, $visible)));
        } elseif ($visible === []) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('event_type', $visible);
        }

        if ($filter->part !== '') {
            match (true) {
                ! isset($levels[$filter->part]) => $query->whereRaw('1 = 0'),
                $filter->part === ChangeLogDescriptions::OTHER => $this->whereTypeNotIn($query, $registered),
                default => $query->whereIn('event_type', $this->descriptions->typesOf($filter->part) ?: ['']),
            };
        }

        $this->whereWho($query, $filter->who);

        if ($filter->from !== null) {
            $query->where('created_at', '>=', $filter->from->setTimezone(config()->string('app.timezone'))->format('Y-m-d H:i:s'));
        }

        if ($filter->to !== null) {
            $query->where('created_at', '<=', $filter->to->setTimezone(config()->string('app.timezone'))->format('Y-m-d H:i:s.u'));
        }

        return $query;
    }

    /** @param  list<string>  $types */
    private function whereTypeNotIn(Builder $query, array $types): void
    {
        if ($types !== []) {
            $query->where(fn (Builder $where) => $where->whereNull('event_type')->orWhereNotIn('event_type', $types));
        }
    }

    private function whereWho(Builder $query, string $who): void
    {
        $agentKey = str_starts_with($who, 'agent:') ? substr($who, 6) : null;

        match (true) {
            $who === '' => null,
            $who === 'people' => $query->whereNotNull('actor')->where('actor', 'not like', '%:%'),
            $who === 'agents' => $query->where(fn (Builder $where) => $where->where('actor', 'like', 'agent:%')->orWhere('actor', 'like', 'bridge:%')),
            $who === 'system' => $query->where(fn (Builder $where) => $where->whereNull('actor')->orWhere('actor', 'like', 'system:%')),
            str_starts_with($who, 'user:') => $query->where('actor', substr($who, 5)),
            $agentKey !== null => $this->whereAgent($query, $agentKey),
            default => null,
        };
    }

    private function whereAgent(Builder $query, string $key): void
    {
        foreach ($this->agents->agents() as $agent) {
            if ($agent->key === $key) {
                $query->where('actor', 'like', addcslashes($agent->actorPrefix, '%_\\').'%');

                return;
            }
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * @param  array<string, TrustLevel>  $levels
     * @param  list<ChangeLogEntry>  $entries
     * @return list<ChangeLogRow>
     */
    private function rows(Actor $viewer, array $levels, array $entries): array
    {
        $actors = $this->actors(array_map(fn (ChangeLogEntry $entry): ?string => $entry->actor, $entries));
        $undone = $this->undoneMap(array_map(fn (ChangeLogEntry $entry): string => $entry->id, $entries));
        $rows = [];

        foreach ($entries as $entry) {
            $part = $this->descriptions->partOf($entry->type);
            $assessment = $this->assess($viewer, $entry, $levels[$part->key] ?? null, $undone[$entry->id] ?? null);

            $rows[] = new ChangeLogRow(
                id: $entry->id,
                at: $entry->at,
                sentence: $assessment['description']->sentence,
                detail: $assessment['description']->detail,
                part: $part,
                actor: $actors[$entry->actor ?? ''] ?? new ChangeLogActorLabel(ChangeLogActorKind::System, 'Systeem'),
                undo: $assessment['state'],
                note: $assessment['note'],
                undoneBy: $undone[$entry->id] ?? null,
            );
        }

        return $rows;
    }

    /**
     * @param  list<ChangeLogArchiveEntry>  $entries
     * @param  array<string, TrustLevel>  $levels
     * @return list<ChangeLogRow>
     */
    private function archiveRows(array $entries, array $levels): array
    {
        $parts = $this->descriptions->parts();
        $rows = [];

        foreach ($entries as $entry) {
            if (! isset($levels[$entry->part])) {
                continue;
            }

            $rows[] = new ChangeLogRow(
                id: 'archive-'.$entry->id,
                at: $entry->at,
                sentence: $entry->sentence,
                detail: $entry->detail,
                part: new ChangePart($entry->part, $parts[$entry->part] ?? $entry->part),
                actor: new ChangeLogActorLabel($entry->actorKind, $entry->actorName),
                undo: ChangeLogUndoState::Unavailable,
                archived: true,
            );
        }

        return $rows;
    }

    /**
     * @param  list<string|null>  $actorIds
     * @return array<string, ChangeLogActorLabel>
     */
    private function actors(array $actorIds): array
    {
        $actorIds = array_values(array_unique(array_filter($actorIds, fn (?string $id): bool => $id !== null && $id !== '')));
        $agentIds = array_values(array_filter($actorIds, fn (string $id): bool => str_starts_with($id, 'agent:') || str_starts_with($id, 'bridge:')));
        $identities = $agentIds === [] ? [] : $this->agents->identify($agentIds);
        $userIds = array_values(array_filter($actorIds, fn (string $id): bool => ! str_contains($id, ':')));

        foreach ($identities as $identity) {
            if ($identity->onBehalfOfUserId !== null) {
                $userIds[] = $identity->onBehalfOfUserId;
            }
        }

        // Most actors are members; only former members cost a second read.
        $names = $userIds === [] ? [] : $this->people();
        $missing = array_values(array_diff(array_unique($userIds), array_keys($names)));

        if ($missing !== []) {
            $names += User::query()->whereKey($missing)->pluck('name', 'id')->all();
        }
        $labels = [];

        foreach ($actorIds as $id) {
            $identity = $identities[$id] ?? null;

            if (in_array($id, $agentIds, true)) {
                $onBehalf = $identity?->onBehalfOfUserId;
                $labels[$id] = new ChangeLogActorLabel(
                    ChangeLogActorKind::Agent,
                    $identity === null ? 'Agent' : $identity->name,
                    $onBehalf === null ? null : Scalar::string($names[$onBehalf] ?? 'een onbekende gebruiker'),
                );

                continue;
            }

            $labels[$id] = str_contains($id, ':')
                ? new ChangeLogActorLabel(ChangeLogActorKind::System, 'Systeem')
                : new ChangeLogActorLabel(ChangeLogActorKind::Person, Scalar::string($names[$id] ?? 'Onbekende gebruiker'));
        }

        return $labels;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string> event id => id of the event that undid it
     */
    private function undoneMap(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $map = [];

        foreach (DB::table('events')->where('event_type', ChangeUndone::TYPE)->whereIn('aggregate_uuid', $ids)->pluck('id', 'aggregate_uuid') as $original => $undo) {
            $map[(string) $original] = Scalar::string($undo);
        }

        return $map;
    }
}
