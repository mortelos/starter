<?php

declare(strict_types=1);

use App\Actions\ChangeLog\UndoChange;
use App\Actions\Invitations\InviteUser;
use App\Contracts\ChangeLog\ChangeLogAccess;
use App\Contracts\ChangeLog\ChangeLogAgents;
use App\Events\ChangeLog\ChangeUndone;
use App\Models\Invitation;
use App\Models\User;
use App\Support\ChangeLog\ChangeDescription;
use App\Support\ChangeLog\ChangeLogEntry;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mortel\Actions\ActionResult;
use Mortel\Actions\Undo;
use Mortel\Actions\UndoRegistry;
use Mortel\Enums\PolicyScope;
use Mortel\Enums\TrustLevel;
use Mortel\OwnTables\AggregateUuid;
use Mortel\OwnTables\ChangeSet;
use Mortel\OwnTables\OwnTableChanges;
use Mortel\OwnTables\RevertOwnTableChanges;
use Mortel\Support\Actor;
use Mortel\Support\EventContext;
use Mortel\Support\Scalar;
use Tests\Support\ChangeLog\LinkAgents;
use Tests\Support\ChangeLog\MapAccess;
use Tests\Support\ChangeLog\RevertThing;
use Tests\Support\ChangeLog\RevertWithoutAbility;
use Tests\Support\ChangeLog\RevertWithRequirement;
use Tests\Support\ChangeLog\Thing;
use Tests\Support\ChangeLog\ThingAggregate;
use Tests\Support\ChangeLog\ThingWritten;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Counter-Action calls of this test, in order.
 *
 * @return ArrayObject<int, mixed>
 */
function revertCalls(): ArrayObject
{
    $calls = app()->bound('test.revert-calls') ? app('test.revert-calls') : null;

    if (! $calls instanceof ArrayObject) {
        $calls = new ArrayObject;
        app()->instance('test.revert-calls', $calls);
    }

    return $calls;
}

/**
 * Registers `things.thing.changed` (part `things`) with an undo through $action, and a counter-Action that records
 * its call (and a row in `events`, like a real counter-Action) and returns $result.
 *
 * @param  Closure(array<string, mixed>, Actor): ActionResult|null  $handler
 */
function registerThing(string $action = RevertThing::class, ?Closure $handler = null, ?string $noUndoReason = null, bool $withUndo = true, ?CarbonImmutable $validUntil = null): void
{
    $descriptions = changeLogDescriptions();
    $descriptions->part('things', 'Dingen');
    $descriptions->table('things', 'Ding', ['name' => 'de naam', 'colour' => 'de kleur']);
    $descriptions->register('things.thing.changed', 'things', fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
        sentence: 'Ding '.$entry->text('name').' gewijzigd',
        undo: $withUndo ? Undo::of($action, ['thing' => $entry->text('name')], $validUntil) : null,
        noUndoReason: $noUndoReason,
    ));

    app(UndoRegistry::class)->register($action, $handler ?? function (array $params, Actor $actor): ActionResult {
        revertCalls()->append([$params, $actor->id()]);
        storeChangeEvent('things.thing.reverted', ['thing' => $params['thing'] ?? null], $actor->id());

        return ActionResult::done('Ding teruggezet.');
    });
}

it('undoes a change through the UndoRegistry, records ChangeUndone with the actor and links the undone row', function (): void {
    registerThing();
    $viewer = changeLogViewer('things.manage');
    $id = storeChangeEvent('things.thing.changed', ['name' => 'Bank'], (string) $viewer->id);

    actingAs($viewer);
    $page = Livewire::test('pages::changelog.index')
        ->assertSee("wire:click=\"undo('{$id}')\"", false)
        ->call('undo', $id)
        ->assertSet('failed', false)
        ->assertSee('Ongedaan gemaakt: Ding Bank gewijzigd.');

    $undo = DB::table('events')->where('event_type', ChangeUndone::TYPE)->first() ?? throw new RuntimeException('No ChangeUndone recorded.');
    expect(revertCalls()->getArrayCopy())->toBe([[['thing' => 'Bank'], (string) $viewer->id]])
        ->and($undo->aggregate_uuid)->toBe($id)
        ->and($undo->actor)->toBe((string) $viewer->id)
        ->and($undo->reason)->toBe('Ongedaan gemaakt: Ding Bank gewijzigd')
        ->and(DB::table('events')->where('event_type', 'things.thing.reverted')->count())->toBe(1);

    $page->assertSee('focus='.Scalar::string($undo->id), false)->assertDontSee("wire:click=\"undo('{$id}')\"", false);
});

it('undoes an invitation from the log: the invitation is revoked', function (): void {
    $viewer = changeLogViewer('invitations.manage');
    app(InviteUser::class)->handle('weg@example.test', modelKeyString(createRole('member')), Actor::user($viewer));
    $id = Scalar::string(DB::table('events')->where('event_type', 'invitations.invitation.created')->value('id'));

    $result = app(UndoChange::class)->handle($id, Actor::user($viewer));

    expect($result->isDone())->toBeTrue()
        ->and(Invitation::query()->sole()->revoked_at)->not->toBeNull();
});

/**
 * The real own-table setup: a table with a revision column, the framework's projector, and a sentence whose Undo is the
 * framework's RevertOwnTableChanges for the event's aggregate and version. That counter-Action names no ability, so the
 * viewer gets level Act on the part.
 */
function setUpThings(): void
{
    Thing::createTable();
    app()->instance(ChangeLogAccess::class, new MapAccess(['things' => TrustLevel::Act, 'other' => TrustLevel::Observe]));

    $descriptions = changeLogDescriptions();
    $descriptions->part('things', 'Dingen');
    $descriptions->table(Thing::TABLE, 'Ding', ['name' => 'de naam', 'colour' => 'de kleur']);
    $descriptions->register('things.thing.written', 'things', fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
        sentence: 'Ding: '.$entry->text('summary'),
        undo: Undo::of(RevertOwnTableChanges::class, [
            'aggregate' => ThingAggregate::class,
            'aggregate_uuid' => $entry->aggregateUuid,
            'version' => $entry->aggregateVersion,
        ]),
    ));
}

function thingEventId(int $version): string
{
    return Scalar::string(DB::table('events')->where('event_type', 'things.thing.written')->where('aggregate_version', $version)->value('id'));
}

it('undoes an own-table change through RevertOwnTableChanges and puts the row back', function (): void {
    setUpThings();
    $viewer = changeLogViewer();
    $thing = new Thing(['name' => 'Bank', 'colour' => 'red']);
    Thing::write($thing, 'Bank aangemaakt', Actor::user($viewer));
    $thing->name = 'Stoel';
    Thing::write($thing, 'Bank heet nu Stoel', Actor::user($viewer));

    $result = app(UndoChange::class)->handle(thingEventId(2), Actor::user($viewer));

    expect($result->isDone())->toBeTrue($result->message)
        ->and(Thing::query()->sole()->name)->toBe('Bank')
        ->and(DB::table('events')->where('event_type', 'own_table.changes.reverted')->count())->toBe(1)
        ->and(DB::table('events')->where('event_type', ChangeUndone::TYPE)->value('aggregate_uuid'))->toBe(thingEventId(2));
});

it('changes nothing and names what changed when the row was changed in the meantime', function (): void {
    setUpThings();
    $viewer = changeLogViewer();
    $other = changeLogViewer();
    $thing = new Thing(['name' => 'Bank', 'colour' => 'red']);
    Thing::write($thing, 'Bank aangemaakt', Actor::user($viewer));
    $thing->name = 'Stoel';
    Thing::write($thing, 'Bank heet nu Stoel', Actor::user($viewer));
    $thing->name = 'Tafel';
    $thing->colour = 'blue';
    Thing::write($thing, 'Stoel heet nu Tafel en is anders van kleur', Actor::user($other));

    actingAs($viewer);
    Livewire::test('pages::changelog.index')
        ->call('undo', thingEventId(2))
        ->assertSet('failed', true)
        ->assertSee('Dit kan niet meer ongedaan worden gemaakt, want er is intussen iets veranderd. Bij Ding '.$thing->id.' zijn de naam en de kleur intussen gewijzigd. Er is niets teruggedraaid.');

    $row = Thing::query()->sole();
    expect([$row->name, $row->colour, $row->revision])->toBe(['Tafel', 'blue', 3])
        ->and(DB::table('events')->where('event_type', 'own_table.changes.reverted')->count())->toBe(0)
        ->and(DB::table('events')->where('event_type', ChangeUndone::TYPE)->count())->toBe(0);
});

it('says a row is gone when it was removed in the meantime', function (): void {
    setUpThings();
    $viewer = changeLogViewer();
    $thing = new Thing(['name' => 'Bank', 'colour' => 'red']);
    Thing::write($thing, 'Bank aangemaakt', Actor::user($viewer));
    $thing->name = 'Stoel';
    Thing::write($thing, 'Bank heet nu Stoel', Actor::user($viewer));
    app(EventContext::class)->within(Actor::user($viewer), 'Stoel weg', fn () => app(OwnTableChanges::class)->record(
        ThingAggregate::retrieve(AggregateUuid::for('changelog_thing', $thing->id)),
        new ThingWritten('Stoel weg'),
        ChangeSet::make()->delete(Thing::query()->sole()),
    ));

    $result = app(UndoChange::class)->handle(thingEventId(2), Actor::user($viewer));

    expect($result->isFailed())->toBeTrue()
        ->and($result->message)->toBe('Dit kan niet meer ongedaan worden gemaakt, want er is intussen iets veranderd. Ding '.$thing->id.' bestaat niet meer. Er is niets teruggedraaid.')
        ->and(Thing::query()->count())->toBe(0);
});

it('changes nothing when an own-table write in the counter-Action throws OwnTableConflict', function (): void {
    setUpThings();
    $viewer = changeLogViewer('things.manage');
    $thing = new Thing(['name' => 'Bank', 'colour' => 'red']);
    Thing::write($thing, 'Bank aangemaakt', Actor::user($viewer));

    // A counter-Action that writes a row it read before someone else changed it: the framework refuses the stale write.
    registerThing(handler: function (array $params, Actor $actor) use ($thing): ActionResult {
        $stale = Thing::query()->findOrFail($thing->id);
        $fresh = Thing::query()->findOrFail($thing->id);
        $fresh->colour = 'green';
        Thing::write($fresh, 'Bank heeft een andere kleur', $actor);
        $stale->name = 'Terug';
        Thing::write($stale, 'Bank terug', $actor);

        return ActionResult::done('Terug.');
    });
    $id = storeChangeEvent('things.thing.changed', ['name' => 'Bank'], (string) $viewer->id);

    $result = app(UndoChange::class)->handle($id, Actor::user($viewer));

    expect($result->isFailed())->toBeTrue()
        ->and($result->message)->toBe('Dit kan niet meer ongedaan worden gemaakt, want er is intussen iets veranderd. Iemand heeft het intussen aangepast. Er is niets teruggedraaid.')
        ->and([Thing::query()->sole()->name, Thing::query()->sole()->colour])->toBe(['Bank', 'red'])
        ->and(DB::table('events')->where('event_type', ChangeUndone::TYPE)->count())->toBe(0);
});

it('shows why a change has no undo, and refuses to undo it', function (): void {
    registerThing(noUndoReason: 'Een verstuurde mail komt niet terug.', withUndo: false);
    $viewer = changeLogViewer('things.manage');
    $id = storeChangeEvent('things.thing.changed', ['name' => 'Mail'], (string) $viewer->id);

    actingAs($viewer);
    Livewire::test('pages::changelog.index')
        ->assertSee('Een verstuurde mail komt niet terug.')
        ->assertDontSee('wire:click="undo(', false);

    $result = app(UndoChange::class)->handle($id, Actor::user($viewer));

    expect($result->isFailed())->toBeTrue()
        ->and($result->message)->toBe('Een verstuurde mail komt niet terug.');
});

it('shows that an undo expired and refuses it', function (): void {
    registerThing(validUntil: CarbonImmutable::now()->subMinute());
    $viewer = changeLogViewer('things.manage');
    $id = storeChangeEvent('things.thing.changed', ['name' => 'Oud'], (string) $viewer->id);

    actingAs($viewer);
    Livewire::test('pages::changelog.index')->assertSee('De termijn om dit ongedaan te maken is voorbij.');

    expect(app(UndoChange::class)->handle($id, Actor::user($viewer))->message)->toBe('Het is te laat om dit ongedaan te maken.');
});

it('refuses a second undo of the same change', function (): void {
    registerThing();
    $viewer = changeLogViewer('things.manage');
    $id = storeChangeEvent('things.thing.changed', ['name' => 'Bank'], (string) $viewer->id);

    expect(app(UndoChange::class)->handle($id, Actor::user($viewer))->isDone())->toBeTrue();

    $again = app(UndoChange::class)->handle($id, Actor::user($viewer));

    expect($again->isFailed())->toBeTrue()
        ->and($again->message)->toBe('Deze wijziging is al ongedaan gemaakt.')
        ->and(revertCalls())->toHaveCount(1)
        ->and(DB::table('events')->where('event_type', ChangeUndone::TYPE)->count())->toBe(1);
});

it('needs the ability of the counter-Action to undo', function (): void {
    registerThing();
    $viewer = changeLogViewer();
    $id = storeChangeEvent('things.thing.changed', ['name' => 'Bank'], (string) $viewer->id);

    actingAs($viewer);
    Livewire::test('pages::changelog.index')
        ->assertSee('Je mag deze wijziging niet ongedaan maken.')
        ->assertDontSee('wire:click="undo(', false)
        ->call('undo', $id)
        ->assertSet('failed', true)
        ->assertSee('Je mag deze wijziging niet ongedaan maken.');

    expect(revertCalls())->toHaveCount(0)
        ->and(DB::table('events')->where('event_type', ChangeUndone::TYPE)->count())->toBe(0);
});

it('reads the right from requires() when the counter-Action has one', function (): void {
    registerThing(RevertWithRequirement::class);
    $id = storeChangeEvent('things.thing.changed', ['name' => 'Bank'], 'system:x');

    expect(fn () => app(UndoChange::class)->handle($id, Actor::user(changeLogViewer())))->toThrow(AuthorizationException::class);

    $allowed = changeLogViewer();
    $role = createRole('policy-scope-'.fake()->unique()->numberBetween(1, 9999));
    grantPolicy($role, 'things.manage', scope: PolicyScope::Policy);
    DB::table('tenant_user')->where('user_id', $allowed->id)->update(['role_id' => modelKeyString($role)]);
    grantPolicy($role, 'changelog.view');

    expect(app(UndoChange::class)->handle($id, Actor::user($allowed))->isDone())->toBeTrue();
});

it('needs level Act on the part when the counter-Action names no right', function (): void {
    registerThing(RevertWithoutAbility::class);
    $viewer = changeLogViewer();
    $id = storeChangeEvent('things.thing.changed', ['name' => 'Bank'], (string) $viewer->id);

    app()->instance(ChangeLogAccess::class, new MapAccess(['things' => TrustLevel::Observe]));
    expect(fn () => app(UndoChange::class)->handle($id, Actor::user($viewer)))->toThrow(AuthorizationException::class, 'Je mag deze wijziging niet ongedaan maken.');

    app()->instance(ChangeLogAccess::class, new MapAccess(['things' => TrustLevel::Act]));
    app()->forgetScopedInstances(); // a new request: the reader is bound per request
    expect(app(UndoChange::class)->handle($id, Actor::user($viewer))->isDone())->toBeTrue();
});

it('refuses to undo a change in a part the user may not see', function (): void {
    registerThing();
    app()->instance(ChangeLogAccess::class, new MapAccess(['other' => TrustLevel::Observe]));
    $viewer = changeLogViewer('things.manage');
    $id = storeChangeEvent('things.thing.changed', ['name' => 'Bank'], (string) $viewer->id);

    expect(fn () => app(UndoChange::class)->handle($id, Actor::user($viewer)))->toThrow(AuthorizationException::class, 'Je mag dit onderdeel niet zien.');
});

it('refuses an undo without changelog.view', function (): void {
    registerThing();
    $user = User::factory()->create();
    $role = createRole('things-only');
    grantPolicy($role, 'things.manage');
    attachGovernanceMembership($user, $role);
    $id = storeChangeEvent('things.thing.changed', ['name' => 'Bank'], (string) $user->id);

    expect(fn () => app(UndoChange::class)->handle($id, Actor::user($user)))->toThrow(AuthorizationException::class);
});

it('lets an agent undo only its own changes, in the action and not just on the screen', function (): void {
    registerThing();
    $owner = changeLogViewer('things.manage');
    $byUser = storeChangeEvent('things.thing.changed', ['name' => 'Van mens'], (string) $owner->id);
    $byOtherRun = storeChangeEvent('things.thing.changed', ['name' => 'Van andere run'], 'agent:run-1');
    $byItself = storeChangeEvent('things.thing.changed', ['name' => 'Van zichzelf'], 'agent:run-2');

    $agent = Actor::agent('run-2', $owner);

    expect(fn () => app(UndoChange::class)->handle($byUser, $agent))->toThrow(AuthorizationException::class, 'Een agent mag alleen zijn eigen wijzigingen ongedaan maken.')
        ->and(fn () => app(UndoChange::class)->handle($byOtherRun, $agent))->toThrow(AuthorizationException::class)
        ->and(app(UndoChange::class)->handle($byItself, $agent)->isDone())->toBeTrue()
        ->and(DB::table('events')->where('event_type', ChangeUndone::TYPE)->value('actor'))->toBe('agent:run-2');
});

it('treats runs of one named agent as the same agent', function (): void {
    app()->bind(ChangeLogAgents::class, LinkAgents::class);
    registerThing();
    $owner = changeLogViewer('things.manage');
    $earlierRun = storeChangeEvent('things.thing.changed', ['name' => 'Eerder'], 'agent:link-'.$owner->id.'-a');

    expect(app(UndoChange::class)->handle($earlierRun, Actor::agent('link-'.$owner->id.'-b', $owner))->isDone())->toBeTrue();
});

it('gives an agent without a user no rights at all', function (): void {
    registerThing();
    $id = storeChangeEvent('things.thing.changed', ['name' => 'Bank'], 'agent:run-3');

    expect(fn () => app(UndoChange::class)->handle($id, Actor::agent('run-3')))->toThrow(AuthorizationException::class);
});
