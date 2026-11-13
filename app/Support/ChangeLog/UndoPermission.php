<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Access\StarterGovernanceGate;
use App\Contracts\ChangeLog\ChangeLogAgents;
use Mortel\Actions\Undo;
use Mortel\Enums\PolicyScope;
use Mortel\Enums\TrustLevel;
use Mortel\Support\Actor;

/**
 * Whether an actor may undo one change. The screen asks it to show or hide the button; UndoChange asks it again before
 * anything happens, so the rule holds for every caller, agents included.
 *
 *  1. The part of the change must be visible to the actor's user (level not null).
 *  2. An agent or bridge may only undo its own changes: the same actor, or the same agent key (ChangeLogAgents).
 *  3. The actor must be allowed the original action: the `ABILITY` constant of the counter-Action, or its
 *     `requires()` (a PolicyRequirement-shaped object with `action` and `scope`), or else level Act on the part.
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

        $allowed = $requirement === null
            ? $level === TrustLevel::Act
            : $this->allows($actor, $requirement[0], $requirement[1]);

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

    /** @return array{0: string, 1: PolicyScope}|null */
    private function requirement(string $action): ?array
    {
        if (! class_exists($action)) {
            return null;
        }

        $constant = $action.'::ABILITY';

        if (defined($constant) && is_string($ability = constant($constant)) && $ability !== '') {
            return [$ability, StarterGovernanceGate::scopeFor($ability)];
        }

        $instance = method_exists($action, 'requires') ? app($action) : null;

        if (is_object($instance) && method_exists($instance, 'requires')) {
            $requirement = $instance->requires();
            $name = is_object($requirement) && property_exists($requirement, 'action') ? $requirement->action : null;
            $scope = is_object($requirement) && property_exists($requirement, 'scope') ? $requirement->scope : null;

            if (is_string($name) && $name !== '') {
                return [$name, $scope instanceof PolicyScope ? $scope : StarterGovernanceGate::scopeFor($name)];
            }
        }

        return null;
    }

    private function allows(Actor $actor, string $ability, PolicyScope $scope): bool
    {
        return $this->decisions[$actor->id().'|'.$ability.'|'.$scope->value] ??= $this->gate->allowsActor($actor, $ability, $scope);
    }
}
