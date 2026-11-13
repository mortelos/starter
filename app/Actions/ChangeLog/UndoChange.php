<?php

declare(strict_types=1);

namespace App\Actions\ChangeLog;

use App\Access\StarterGovernanceGate;
use App\Aggregates\ChangeLogAggregate;
use App\Events\ChangeLog\ChangeUndone;
use App\Exceptions\ChangeAlreadyUndoneException;
use App\Exceptions\UndoRefusedException;
use App\Models\User;
use App\Support\ChangeLog\ChangeLogDescriptions;
use App\Support\ChangeLog\ChangeLogReader;
use App\Support\ChangeLog\UndoFailureText;
use App\Support\ChangeLog\UndoPermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Mortel\Actions\ActionResult;
use Mortel\Actions\UndoRegistry;
use Mortel\Contracts\TenantResolver;
use Mortel\Support\Actor;
use Mortel\Support\EventContext;
use Mortel\Support\Scalar;
use Throwable;

/**
 * Undoes one change from the change log through the UndoRegistry, for every caller: the screen, a job or an agent.
 * It records ChangeUndone (the audit, and the link from the undone row) and runs the counter-Action in one
 * transaction; when the counter-Action refuses or a row changed in the meantime, nothing stays behind and the
 * result says in Dutch what changed. No Undo of its own: the counter-Action's event is the way forward.
 *
 * Rights (UndoPermission): `changelog.view`, the part visible to the actor's user, the original action's ability (or
 * level Act on the part), and for an agent or bridge only its own changes.
 */
final class UndoChange
{
    public const ABILITY = 'changelog.view';

    public function __construct(
        private readonly StarterGovernanceGate $gate,
        private readonly ChangeLogReader $reader,
        private readonly ChangeLogDescriptions $descriptions,
        private readonly UndoPermission $permission,
        private readonly UndoRegistry $undoRegistry,
        private readonly UndoFailureText $failureText,
    ) {}

    /** @throws AuthorizationException */
    public function handle(string $eventId, Actor $actor): ActionResult
    {
        if (! $this->gate->allowsActor($actor, self::ABILITY)) {
            throw new AuthorizationException('Je mag het wijzigingslog niet gebruiken.');
        }

        $entry = $this->reader->entry($eventId);

        if ($entry === null) {
            return ActionResult::failed('Deze wijziging bestaat niet.');
        }

        $userId = $actor->userId();
        $levels = $this->reader->levels($userId === null ? null : User::query()->find($userId));
        $level = $levels[$this->reader->partOf($entry)->key] ?? null;
        $description = $this->descriptions->describe($entry);
        $undo = $description->undo;

        if ($level === null) {
            throw new AuthorizationException('Je mag dit onderdeel niet zien.');
        }

        if ($this->reader->undoneBy($entry->id) !== null) {
            return ActionResult::failed('Deze wijziging is al ongedaan gemaakt.');
        }

        if ($undo === null) {
            return ActionResult::failed($description->noUndoReason ?? 'Deze wijziging kan niet ongedaan worden gemaakt.');
        }

        if (! $this->undoRegistry->knows($undo->action)) {
            return ActionResult::failed('Deze wijziging kan niet ongedaan worden gemaakt: er is geen terugdraaiactie ingesteld.');
        }

        if ($undo->isExpired()) {
            return ActionResult::failed('Het is te laat om dit ongedaan te maken.');
        }

        $refusal = $this->permission->refusal($actor, $entry, $undo, $level);

        if ($refusal !== null) {
            throw new AuthorizationException($refusal);
        }

        try {
            DB::transaction(function () use ($entry, $undo, $actor, $description): void {
                app(EventContext::class)->within(
                    $actor,
                    mb_strimwidth('Ongedaan gemaakt: '.$description->sentence, 0, 255),
                    fn () => ChangeLogAggregate::retrieve($entry->id)->undo($entry->type, $undo->action, $description->sentence)->persist(),
                    app(TenantResolver::class)->id(),
                    Scalar::string(config('starter.tenancy.default_branch_id', 'main'), 'main'),
                );

                $result = $this->undoRegistry->undo($undo, $actor);

                if (! $result->isDone()) {
                    throw new UndoRefusedException($result);
                }
            });
        } catch (UndoRefusedException $refused) {
            return ActionResult::failed($this->failureText->forResult($undo, $refused->result), $refused->result->data);
        } catch (ChangeAlreadyUndoneException $undone) {
            return ActionResult::failed($undone->getMessage());
        } catch (AuthorizationException $denied) {
            throw $denied;
        } catch (Throwable $exception) {
            return ActionResult::failed($this->failureText->forException($exception) ?? throw $exception);
        }

        $undoEventId = DB::table('events')->where('event_type', ChangeUndone::TYPE)->where('aggregate_uuid', $entry->id)->value('id');

        return ActionResult::done('Ongedaan gemaakt: '.rtrim($description->sentence, '.').'.', [
            'event_id' => $entry->id,
            'undo_event_id' => Scalar::string($undoEventId),
        ]);
    }
}
