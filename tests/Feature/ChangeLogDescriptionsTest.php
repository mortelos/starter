<?php

declare(strict_types=1);

use App\Actions\Invitations\InviteUser;
use App\Actions\Invitations\RevokeInvitation;
use App\Support\ChangeLog\ChangeDescription;
use App\Support\ChangeLog\ChangeLogDescriptions;
use App\Support\ChangeLog\ChangeLogEntry;
use App\Support\ChangeLog\ChangeLogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mortel\Actions\Undo;
use Mortel\Support\Actor;
use Mortel\Support\Scalar;

uses(RefreshDatabase::class);

function changeLogEntry(string $id): ChangeLogEntry
{
    return app(ChangeLogReader::class)->entry($id) ?? throw new RuntimeException("No event {$id}.");
}

it('describes a registered event type with its sentence, part and undo', function (): void {
    $descriptions = changeLogDescriptions();
    $descriptions->part('planning', 'Planning');
    $descriptions->register('planning.phase.shifted', 'planning', fn (ChangeLogEntry $entry): ChangeDescription => new ChangeDescription(
        sentence: 'Fase '.$entry->text('name').' '.$entry->text('days').' dagen verschoven',
        undo: Undo::of('App\\Actions\\Planning\\ShiftPhase', ['days' => -3]),
    ));

    $entry = changeLogEntry(storeChangeEvent('planning.phase.shifted', ['name' => 'Voorbereiding', 'days' => 3], 'system:test'));
    $description = $descriptions->describe($entry);

    expect($description->sentence)->toBe('Fase Voorbereiding 3 dagen verschoven')
        ->and($description->undo?->params)->toBe(['days' => -3])
        ->and($descriptions->partOf('planning.phase.shifted')->label)->toBe('Planning')
        ->and($descriptions->typesOf('planning'))->toBe(['planning.phase.shifted']);
});

it('accepts a plain string from a describer', function (): void {
    changeLogDescriptions()->register('notes.note.created', 'notes', fn (): string => 'Notitie gemaakt');

    expect(changeLogDescriptions()->describe(changeLogEntry(storeChangeEvent('notes.note.created')))->sentence)->toBe('Notitie gemaakt');
});

it('falls back to a neutral sentence, the reason and the part Overig for an unknown type', function (): void {
    $entry = changeLogEntry(storeChangeEvent('unknown.thing.happened', [], 'system:test', reason: 'Nachtelijke import'));
    $descriptions = changeLogDescriptions();

    $description = $descriptions->describe($entry);

    expect($description->sentence)->toBe('Wijziging vastgelegd')
        ->and($description->detail)->toBe('Nachtelijke import')
        ->and($description->undo)->toBeNull()
        ->and($descriptions->partOf('unknown.thing.happened')->key)->toBe(ChangeLogDescriptions::OTHER)
        ->and($descriptions->partOf('unknown.thing.happened')->label)->toBe('Overig');
});

it('falls back instead of failing when a describer throws on an old payload', function (): void {
    changeLogDescriptions()->register('broken.thing.changed', 'other', fn (): never => throw new RuntimeException('old payload'));

    expect(changeLogDescriptions()->describe(changeLogEntry(storeChangeEvent('broken.thing.changed')))->sentence)->toBe('Wijziging vastgelegd');
});

it('describes the invitation events of the starter, with the undo of InviteUser', function (): void {
    $manager = invitationsManager();
    $role = createRole('member');
    $result = app(InviteUser::class)->handle('nieuw@example.test', modelKeyString($role), Actor::user($manager));
    $id = Scalar::string(DB::table('events')->where('event_type', 'invitations.invitation.created')->value('id'));
    $description = changeLogDescriptions()->describe(changeLogEntry($id));

    expect($description->sentence)->toBe('Uitnodiging klaargezet voor nieuw@example.test')
        ->and($description->undo?->action)->toBe(RevokeInvitation::class)
        ->and($description->undo?->params)->toBe(['invitation_id' => $result->data['invitation_id']])
        ->and(changeLogDescriptions()->partOf('invitations.invitation.created')->label)->toBe('Gebruikers');
});

it('says why a revoked invitation has no undo', function (): void {
    $description = changeLogDescriptions()->describe(changeLogEntry(storeChangeEvent('invitations.invitation.revoked', ['invitation_id' => 'x'])));

    expect($description->undo)->toBeNull()
        ->and($description->noUndoReason)->toBe('Een ingetrokken link blijft dood. Nodig opnieuw uit.');
});
