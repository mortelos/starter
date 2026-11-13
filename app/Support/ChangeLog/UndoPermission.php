<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Access\StarterGovernanceGate;
use App\Contracts\ChangeLog\ChangeLogAgents;
use Mortel\Actions\Undo;
use Mortel\Api\PolicyRequirement;
use Mortel\Enums\TrustLevel;
use Mortel\Support\Actor;

/**
 * Whether an actor may undo one change. The screen asks it to show or hide the button; UndoChange asks it again before
 * anything happens, so the rule holds for every caller, agents included.
 *
 *  1. The part of the change must be visible to the actor's user (level not null).
 *  2. An agent or bridge may only undo its own changes: the same actor, or the same agent key (ChangeLogAgents).
 *  3. The actor must be allowed the original action: the `ABILITY` constant of the counter-Action, or the
 *     PolicyRequirement its `requires()` returns (all of it, resource type included), or else level Act on the part.
 *     A `requires()` that returns anything else is refused.
 */
final class UndoPermission
{
    /** @var array<string, bool> */
    private array $decisions = [];

    public function __construct(
        private readonly StarterGovernanceGate $gate,
        private readonly ChangeLogAgents $agents,
    ) {}

    /** Null when the actor may undo it; otherwise the reason in Dutch. */
    public function refusal(Actor $actor, ChangeLogEntry $entry, Undo $undo, ?TrustLevel $level): ?string
    {
        if ($level === null) {
            return 'Je mag dit onderdeel niet zien.';
        }

        if (($actor->isAgent() || $actor->isBridge()) && ! $this->isOwnChange($actor, $entry)) {
            return 'Een agent mag alleen zijn eigen wijzigingen ongedaan maken.';
        }

        $requirement = $this->requirement($undo->action);

        $allowed = match (true) {
            $requirement === null => $level === TrustLevel::Act,
            $requirement === false => false,
            default => $this->allows($actor, $requirement),
        };

        return $allowed ? null : 'Je mag deze wijziging niet ongedaan maken.';
    }

    private function isOwnChange(Actor $actor, ChangeLogEntry $entry): bool
    {
        if ($entry->actor === null) {
            return false;
        }

        if ($entry->actor === $actor->id()) {
            return true;
        }

        if (! str_starts_with($entry->actor, 'agent:') && ! str_starts_with($entry->actor, 'bridge:')) {
            return false;
        }

        $identities = $this->agents->identify(array_values(array_unique([$actor->id(), $entry->actor])));
        $mine = $identities[$actor->id()] ?? null;
        $theirs = $identities[$entry->actor] ?? null;

        return $mine !== null && $theirs !== null && $mine->key === $theirs->key;
    }

    /**
     * What the original action asks: the `ABILITY` of the counter-Action, or the PolicyRequirement its `requires()`
     * returns, whole (action, scope and resource type). Null when it names nothing (then level Act decides); false when
     * `requires()` returns something else, which is refused rather than guessed at.
     */
    private function requirement(string $action): PolicyRequirement|false|null
    {
        if (! class_exists($action)) {
            return null;
        }

        $constant = $action.'::ABILITY';

        if (defined($constant) && is_string($ability = constant($constant)) && $ability !== '') {
            return new PolicyRequirement($ability, StarterGovernanceGate::scopeFor($ability));
        }

        $instance = method_exists($action, 'requires') ? app($action) : null;

        if (! is_object($instance) || ! method_exists($instance, 'requires')) {
            return null;
        }

        $requirement = $instance->requires();

        return $requirement instanceof PolicyRequirement ? $requirement : false;
    }

    /**
     * The framework's own check (CheckPolicy through the gate) with every field of the requirement. There is no record
     * id for an undo, so this is the exact check on the resource type: a policy on another record does not open it.
     */
    private function allows(Actor $actor, PolicyRequirement $requirement): bool
    {
        $key = implode('|', [$actor->id(), $requirement->action, $requirement->scope->value, $requirement->resourceType ?? '']);

        return $this->decisions[$key] ??= $this->gate->allowsActor($actor, $requirement->action, $requirement->scope, $requirement->resourceType);
    }
}
