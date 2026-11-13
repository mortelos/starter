<?php

declare(strict_types=1);

namespace App\Aggregates;

use App\Events\ChangeLog\ChangeUndone;
use App\Exceptions\ChangeAlreadyUndoneException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

/**
 * The undo of one change from the change log. Its uuid is the id of the undone event (26 characters, like every
 * aggregate uuid), so the aggregate holds the one guard: a change is undone at most once.
 */
final class ChangeLogAggregate extends AggregateRoot
{
    private bool $undone = false;

    /** @throws ChangeAlreadyUndoneException */
    public function undo(string $eventType, string $undoAction, string $summary): static
    {
        if ($this->undone) {
            throw new ChangeAlreadyUndoneException;
        }

        $this->recordThat(new ChangeUndone(
            event_id: $this->uuid(),
            event_type: $eventType,
            undo_action: $undoAction,
            summary: $summary,
        ));

        return $this;
    }

    protected function applyChangeUndone(ChangeUndone $event): void
    {
        $this->undone = true;
    }
}
