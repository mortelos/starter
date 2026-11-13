<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\ChangeLog\ChangeDescription;
use App\Support\ChangeLog\ChangeLogEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Mortel\Actions\ActionResult;
use Mortel\Actions\Undo;
use Mortel\Actions\UndoRegistry;
use Mortel\Support\Scalar;
use Tests\Support\ChangeLog\RevertThing;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
 * Budget of the change log (plan M6): with 10,000 events at most 10 queries and 150 KB for opening and for filtering.
 * Time is not asserted here (it depends on the machine); it is measured by hand on PostgreSQL.
 */
const CHANGELOG_MAX_QUERIES = 10;

const CHANGELOG_MAX_BYTES = 150 * 1024;

beforeEach(function (): void {
    $descriptions = changeLogDescriptions();
    $descriptions->part('planning', 'Planning');
    $descriptions->register('planning.phase.shifted', 'planning', fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
        sentence: 'Fase '.$entry->text('name').' '.$entry->text('days').' dagen verschoven',
        undo: Undo::of(RevertThing::class, ['phase' => $entry->text('name')]),
    ));
    $descriptions->register('planning.mail.sent', 'planning', fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
        sentence: 'Planning '.$entry->text('name').' gemaild aan de opdrachtgever',
        detail: 'Met de bijlage planning-'.$entry->text('name').'.pdf',
        noUndoReason: 'Een verstuurde mail komt niet terug.',
    ));
    app(UndoRegistry::class)->register(RevertThing::class, fn (): ActionResult => ActionResult::done('Terug.'));

    $people = User::factory()->count(20)->create()->each(fn (User $user) => attachGovernanceMembership($user, null))->map(fn (User $user): string => (string) $user->id)->all();
    $actors = [...$people, 'agent:run-1', 'agent:run-2', 'system:scheduler', 'bridge:telegram'];
    $start = CarbonImmutable::now()->subDays(60);
    $rows = [];

    foreach (range(1, 10_000) as $i) {
        // A third of each: with undo, without undo but with a reason and a detail line, and unregistered.
        $type = ['planning.phase.shifted', 'planning.mail.sent', 'unknown.thing.happened'][$i % 3];
        $payload = ['name' => (string) $i, 'days' => $i % 7, 'changes' => array_fill(0, 5, ['table' => 'phases', 'after' => ['id' => $i, 'note' => Str::random(40)]])];
        $rows[] = changeEventRow(strtolower((string) Str::ulid()), $type, $payload, $actors[$i % count($actors)], $start->addMinutes($i * 8), 'Wijziging '.$i);

        if (count($rows) === 500) {
            DB::table('events')->insert($rows);
            $rows = [];
        }
    }
});

/**
 * @template T
 *
 * @param  Closure(): T  $work
 * @return array{0: T, 1: int} the result of $work and the number of queries it ran
 */
function countQueries(Closure $work): array
{
    $count = 0;
    DB::listen(function (QueryExecuted $query) use (&$count): void {
        $count++;

    });

    $result = $work();

    return [$result, $count];
}

it('opens the change log of 10,000 events within the query and byte budget', function (): void {
    $viewer = changeLogViewer('things.manage');
    actingAs($viewer);

    [$response, $queries] = countQueries(fn (): TestResponse => get(route('changelog')));
    $bytes = strlen(Scalar::string($response->getContent()));

    $response->assertOk()->assertSee(['Planning 10000 gemaild aan de opdrachtgever', 'Een verstuurde mail komt niet terug.', 'Ongedaan maken']);
    fwrite(STDERR, "ChangeLog open: {$queries} queries, {$bytes} bytes".PHP_EOL);

    expect($queries)->toBeLessThanOrEqual(CHANGELOG_MAX_QUERIES)
        ->and($bytes)->toBeLessThanOrEqual(CHANGELOG_MAX_BYTES);
});

it('filters the change log of 10,000 events within the query and byte budget', function (): void {
    $viewer = changeLogViewer('things.manage');
    actingAs($viewer);
    $component = Livewire::test('pages::changelog.index');

    foreach (['who' => 'agents', 'part' => 'planning', 'from' => CarbonImmutable::now()->subDays(30)->format('Y-m-d')] as $property => $value) {
        [, $queries] = countQueries(fn () => $component->set($property, $value));
        $bytes = strlen($component->html()) + strlen(json_encode($component->instance()->all(), JSON_THROW_ON_ERROR));

        fwrite(STDERR, "ChangeLog filter {$property}: {$queries} queries, {$bytes} bytes".PHP_EOL);

        expect($queries)->toBeLessThanOrEqual(CHANGELOG_MAX_QUERIES)
            ->and($bytes)->toBeLessThanOrEqual(CHANGELOG_MAX_BYTES);
    }

    $component->assertSee('dagen verschoven')->assertDontSee('Wijziging vastgelegd');
});
