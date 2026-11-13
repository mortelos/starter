<?php

declare(strict_types=1);

use App\Actions\ChangeLog\UndoChange;
use App\Contracts\ChangeLog\ChangeLogAccess;
use App\Events\ChangeLog\ChangeUndone;
use App\Support\ChangeLog\ChangeDescription;
use App\Support\ChangeLog\ChangeLogEntry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mortel\Actions\ActionResult;
use Mortel\Actions\Policy\CreatePolicy;
use Mortel\Actions\Undo;
use Mortel\Actions\UndoRegistry;
use Mortel\Enums\PolicyEffect;
use Mortel\Enums\PolicyScope;
use Mortel\Enums\TrustLevel;
use Mortel\Support\Actor;
use Mortel\Support\Scalar;
use Tests\Support\ChangeLog\CheckRightTwiceJob;
use Tests\Support\ChangeLog\MapAccess;
use Tests\Support\ChangeLog\RevertThing;
use Tests\Support\ChangeLog\RevertWithOddRequirement;
use Tests\Support\ChangeLog\RevertWithRequirement;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
 * The four findings of the blind review of the change log, each with the test that fails without its fix.
 */

/** `secrets.thing.changed` in part `secrets`, undone through $action; returns the id of the event. */
function secretChange(string $action = RevertThing::class): string
{
    $descriptions = changeLogDescriptions();
    $descriptions->part('secrets', 'Geheim');
    $descriptions->register('secrets.thing.changed', 'secrets', fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
        sentence: 'Geheim plan '.$entry->text('name').' gewijzigd',
        undo: Undo::of($action, ['name' => $entry->text('name')]),
    ));
    app(UndoRegistry::class)->register($action, fn (): ActionResult => ActionResult::done('Terug.'));

    return storeChangeEvent('secrets.thing.changed', ['name' => 'Noord'], 'system:x');
}

/** Swap the access outlet, as a new request would see it. */
function partsVisible(string ...$parts): void
{
    app()->instance(ChangeLogAccess::class, new MapAccess(array_fill_keys([...$parts, 'other', 'users', 'access'], TrustLevel::Observe)));
    app()->forgetScopedInstances();
}

it('1. shows an undo row only to whoever may see the part of the undone change, on the screen and in the CSV', function (): void {
    $id = secretChange();
    $owner = changeLogViewer('things.manage');
    expect(app(UndoChange::class)->handle($id, Actor::user($owner))->isDone())->toBeTrue();

    // Without the part `secrets`: neither the change nor the text of its undo, under whatever part.
    partsVisible('changelog');
    $outsider = changeLogViewer();
    actingAs($outsider);
    Livewire::test('pages::changelog.index')->assertDontSee('Geheim plan Noord');
    expect(implode("\n", changeLogCsv($outsider)))->not->toContain('Geheim plan Noord');

    // With it: both, and the undo row follows the part filter of the change it undid.
    partsVisible('secrets');
    $insider = changeLogViewer();
    actingAs($insider);
    Livewire::test('pages::changelog.index', ['part' => 'secrets'])
        ->assertSee(['Ongedaan gemaakt: Geheim plan Noord gewijzigd', 'Geheim plan Noord gewijzigd']);
    expect(implode("\n", changeLogCsv($insider, ['part' => 'secrets'])))->toContain('Ongedaan gemaakt: Geheim plan Noord gewijzigd');
});

it('2. passes the whole requirement of the counter-Action, resource type included, to the policy check', function (): void {
    $id = secretChange(RevertWithRequirement::class);
    $user = changeLogViewer('things.manage');
    $roleId = Scalar::string(DB::table('tenant_user')->where('user_id', $user->id)->value('role_id'));

    // A general allow, and an explicit deny for the resource type the requirement names.
    app(CreatePolicy::class)->handle(
        name: 'Deny things.manage on secret_thing',
        description: null,
        scope: PolicyScope::Policy,
        resourceType: 'secret_thing',
        resourceId: null,
        roleId: $roleId,
        actions: ['things.manage' => PolicyEffect::Deny->value],
        effect: PolicyEffect::Deny,
        priority: 10,
        conditions: null,
        orgId: governanceTenantId(),
        branchId: governanceBranchId(),
        actor: 'system:test',
    );
    app()->forgetScopedInstances();

    expect(fn () => app(UndoChange::class)->handle($id, Actor::user($user)))->toThrow(AuthorizationException::class)
        ->and(DB::table('events')->where('event_type', ChangeUndone::TYPE)->count())->toBe(0);
});

it('2. refuses a requires() that is not a PolicyRequirement instead of guessing', function (): void {
    $id = secretChange(RevertWithOddRequirement::class);

    expect(fn () => app(UndoChange::class)->handle($id, Actor::user(changeLogViewer('things.manage'))))->toThrow(AuthorizationException::class);
});

it('3. reads a right that another process revoked again in the next step of a job', function (): void {
    $user = changeLogViewer('things.manage');

    dispatch_sync(new CheckRightTwiceJob((string) $user->id, 'things.manage'));

    expect(Cache::get(CheckRightTwiceJob::RESULTS))->toBe([true, false]);
});

it('3. still reads the policies once per request of the change log', function (): void {
    actingAs(changeLogViewer());
    $reads = 0;
    DB::listen(function (QueryExecuted $query) use (&$reads): void {
        $reads += str_contains($query->sql, 'from "policies"') ? 1 : 0;
    });

    get(route('changelog'))->assertOk();

    expect($reads)->toBe(1);
});

it('4. links an undone row to its undo without the filters that would hide it', function (): void {
    $id = secretChange();
    $user = changeLogViewer('things.manage');
    expect(app(UndoChange::class)->handle($id, Actor::user($user))->isDone())->toBeTrue();
    $undoId = Scalar::string(DB::table('events')->where('event_type', ChangeUndone::TYPE)->value('id'));

    actingAs($user);
    Livewire::test('pages::changelog.index', ['part' => 'secrets', 'who' => 'system'])
        ->assertSee('Geheim plan Noord gewijzigd')
        ->assertSee('href="'.route('changelog', ['focus' => $undoId]).'"', false);

    // The link lands on the undo row; with the filter `who=system` kept it would not be there (the undo was the user's).
    Livewire::test('pages::changelog.index', ['focus' => $undoId])->assertSee('Ongedaan gemaakt: Geheim plan Noord gewijzigd');
});
