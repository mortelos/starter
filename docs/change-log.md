# Change log with undo

Every MortelOS app records each change as a stored event in `events`, with its actor and reason. The change log at
`/changelog` reads those events back for people: who did what, in which part of the app and when, with filters, a CSV
export and an **Ongedaan maken** button wherever a change can be reversed.

Nothing needs to be set up for it to work: the starter's own events (invitations, roles and policies, the undo itself)
have sentences, and any other event reads "Wijziging vastgelegd" under the part "Overig". An app makes the log useful
for its own domain through its outlets, listed in `resources/mortelos/outlets.json`
(`php artisan mortelos:outlets:list`).

## Who sees it

| Right | Where it is checked |
| --- | --- |
| Open the page and the CSV export | ability `changelog.view` (scope Action, `StarterGovernanceGate::SCOPES`). The seeder gives it to `owner`; the migration `2026_11_13_200000_grant_changelog_access` does the same for an existing `owner` role. Other roles get it on `/governance/roles`. The profile menu links to the page for those who have it. |
| See the rows of a part | the `ChangeLogAccess` outlet. Default: whoever may open the page sees every part. |
| Undo a change | `UndoChange` (`app/Actions/ChangeLog/UndoChange.php`), for every caller: the user must be allowed the original action, which is the `ABILITY` constant of the counter-Action, or the `Mortel\Api\PolicyRequirement` its `requires()` returns, checked whole (action, scope and resource type) through `CheckPolicy`, or else level `Act` on the part from `ChangeLogAccess`. A `requires()` that returns anything else is refused. |
| An agent or bridge undoing | only its own changes: the same actor, or the same agent key from `ChangeLogAgents`. This is checked in `UndoChange`, so it holds for a tool call as well as the screen. An agent acts with the rights of the user it acts for; without one it has none. |

## Undo

The button calls `UndoChange`, which in one transaction:

1. records `ChangeUndone` (`changelog.change.undone`) on a `ChangeLogAggregate` whose uuid is the id of the undone
   event, so a change is undone once at most, also when two people click at the same time;
2. runs the counter-Action through the `UndoRegistry` with the Undo recipe the describer returned.

When the counter-Action does not return `done`, or throws, the transaction rolls back and the page shows a Dutch message.
When the framework's own-table undo (`Mortel\OwnTables\RevertOwnTableChanges`) refuses, it names the rows that changed
in `data.conflicts`, and the message says what changed in the meantime: "Bij Fase 12 zijn de begindatum en de einddatum
intussen gewijzigd. Er is niets teruggedraaid." The revision column and the timestamps are left out. Give your tables
and columns words with `table()` (below). An `OwnTableConflict` thrown by a stale own-table write in a counter-Action
says that someone changed it in the meantime. Any other refusal shows its own message. A counter-Action that answers `approval_needed` is rolled back as well: an undo button does not
start an approval.

The undone row then shows "Ongedaan gemaakt" with a link to the row of the undo; the link drops the filters, which could
hide that row. The undo row ("Ongedaan gemaakt: …") names the change it undid, so it belongs to the part of that change:
only whoever may see that part sees the undo row, and the part filter shows it next to the change
(`ChangeLogReader::partOf()`). A row without an undo shows the reason its describer gives; an expired Undo shows that it
is too late.

The open list stays at the point where it was loaded, so an undo does not push the rows down. New rows show after a
filter change or **Naar de nieuwste**.

## Outlet 1: a sentence and a part per event type

`App\Support\ChangeLog\ChangeLogDescriptions`, filled by classes that implement `App\Contracts\ChangeLog\DescribesChanges`. Every
resolve builds a fresh registry from the classes tagged `changelog.descriptions` (no shared state between requests or
tenants). Tag your class in a service provider:

```php
$this->app->tag([AppChangeDescriptions::class], ChangeLogDescriptions::TAG);
```

```php
use App\Contracts\ChangeLog\DescribesChanges;
use App\Support\ChangeLog\ChangeDescription;
use App\Support\ChangeLog\ChangeLogDescriptions;
use App\Support\ChangeLog\ChangeLogEntry;
use Mortel\Actions\Undo;

final class AppChangeDescriptions implements DescribesChanges
{
    public function register(ChangeLogDescriptions $descriptions): void
    {
        $descriptions->part('planning', 'Planning');

        $descriptions->register('planning.phase.shifted', 'planning', fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
            sentence: 'Fase '.$entry->text('name').' '.$entry->text('days').' dagen verschoven',
            undo: Undo::of(RevertOwnTableChanges::class, [
                'aggregate' => WorkPackageAggregate::class,
                'aggregate_uuid' => $entry->aggregateUuid,
                'version' => $entry->aggregateVersion,
            ]),
        ));

        $descriptions->register('mail.message.sent', 'planning', fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
            sentence: 'Mail verstuurd aan '.$entry->text('to'),
            noUndoReason: 'Een verstuurde mail komt niet terug.',
        ));

        // Words for the conflict message of an own-table undo.
        $descriptions->table('phases', 'Fase', ['starts_on' => 'de begindatum', 'ends_on' => 'de einddatum']);
    }
}
```

- The describer receives a `ChangeLogEntry`: `id`, `type`, `aggregateUuid`, `aggregateVersion`, `actor`, `reason`, `at`
  and the decoded `payload` (`text('field')` reads a scalar field). It returns a `ChangeDescription` (sentence, optional
  `detail` line, `undo` or `noUndoReason`) or just the sentence.
- The part of a type is fixed when it is registered. That is what keeps filtering and hiding in SQL: a hidden part
  never costs a half-empty page.
- A describer that throws (an old payload shape) falls back to "Wijziging vastgelegd" and is reported; the log keeps
  working.
- The Undo recipe comes from the event itself. Use the same counter-Action and window the Action put in its result.
- Write the sentences for the people who read them: no internal or technical names.

## Outlet 2: who an agent is

Bind `App\Contracts\ChangeLog\ChangeLogAgents`. The framework stores `agent:<run-id>` and `bridge:<name>`; your class
says which integration a run belongs to and for whom it acted:

```php
final class AppChangeLogAgents implements ChangeLogAgents
{
    public function agents(): array
    {
        return [new ChangeLogAgent('assistant', 'de assistent', 'agent:assistant-')];
    }

    public function identify(array $actorIds): array
    {
        // One query for the whole page, for example on your run table.
        // return [$actorId => new ChangeLogAgentIdentity('assistant', 'de assistent', $userId), …];
    }
}
```

`agents()` fills the "Wie" filter (the actor prefix is what the filter matches on). The `key` of an identity decides
what counts as the agent's own changes. The default, `GenericChangeLogAgents`, calls every agent "Agent", treats each
run as its own agent and names a bridge after its channel.

## Outlet 3: rights per part

Bind `App\Contracts\ChangeLog\ChangeLogAccess`: `level(Authenticatable $user, string $part): ?TrustLevel`. `null` hides
the part's rows from the page, the filter and the export; `Observe` shows them; `Act` also allows undo where the
counter-Action names no ability. It is asked once per part per request. The default, `ViewersSeeEverything`, returns
`Observe`. The part `other` holds every unregistered type.

## Outlet 4: history from before the move

Bind `App\Contracts\ChangeLog\ChangeLogArchive`:
`entries(ChangeLogFilter $filter, array $parts, ?string $after, int $limit): ChangeLogArchivePage`. Return rows newest
first, filtered on the filter and on the visible `$parts`, with your own cursor in `next`. The log shows them under
"Vóór de overstap" once the stored events of the filter run out, read only and without undo, and the CSV export
appends them. An app that moves from another system reads them from a table of its own, for example
`legacy_change_history`.
The default, `NoChangeLogArchive`, has none.

## Filters and export

| Filter | Values |
| --- | --- |
| Wie | everyone, all people, one member of the tenant, all agents (and bridges), one agent from `ChangeLogAgents`, the system |
| Onderdeel | every part the user may see, and "Overig" |
| Van, Tot en met | whole days in the app's time zone |

The filter lives in the address (`?who=…&part=…&from=…&to=…`). **CSV exporteren** downloads exactly what the filter
shows, every page and then the archive, with semicolons and a byte order mark so a Dutch spreadsheet opens it. A cell
that a spreadsheet would read as a formula starts with a quote.

## Speed

Measured by `tests/Feature/ChangeLogBudgetTest.php` with 10,000 events: opening uses 9 queries and about 95 KB,
filtering 2 queries and about 67 KB (budget 10 queries and 150 KB). On PostgreSQL on the dev server a full page with
10,000 events answered in about 160 to 190 ms. Pages are 50 rows, with a cursor on
`(created_at, id)`. The migration `2026_11_13_200001_add_change_log_indexes_to_events_table` adds the indexes
`events_created_at_id_index` and `events_actor_created_at_index`; `starter:doctor` warns when they are missing. One
policy cache per HTTP request (`app/Http/Middleware/SharePolicyCacheForRequest.php`, in the web group) keeps the rights
checks of a page at one read of `policies`. Outside a request, in a job or an agent run, every check reads `policies`
again, so a right that another process revokes counts in the next step.

## Code

| Part | File |
| --- | --- |
| Page | `resources/views/pages/changelog/⚡index.blade.php` (route `changelog`) |
| Export | `app/Http/Controllers/ChangeLogExportController.php` (route `changelog.export`) |
| Undo | `app/Actions/ChangeLog/UndoChange.php`, `app/Aggregates/ChangeLogAggregate.php`, `app/Events/ChangeLog/ChangeUndone.php` |
| Reading, rights, messages | `app/Support/ChangeLog/ChangeLogReader.php`, `UndoPermission.php`, `UndoFailureText.php`, `ChangeLogAccessCheck.php` |
| Outlets and defaults | `app/Support/ChangeLog/ChangeLogDescriptions.php`, `app/Contracts/ChangeLog/*`, `app/Support/ChangeLog/GenericChangeLogAgents.php`, `ViewersSeeEverything.php`, `NoChangeLogArchive.php`, `StarterChangeDescriptions.php` |
| Tests | `tests/Feature/ChangeLog*Test.php`, `tests/Feature/DoctorChangeLogTest.php`, `tests/Browser/ChangeLogVerifyTest.php` |

In a test, `changeLogDescriptions()` (`tests/Support/ChangeLog/helpers.php`) shares one registry for the rest of the test, so
sentences registered there reach the page and `UndoChange`.
