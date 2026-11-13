<?php

declare(strict_types=1);

namespace App\Events\ChangeLog;

use Mortel\Contracts\ProvidesStoredEventMetadata;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * A change from the change log was undone. The aggregate is the undone event itself (its id is the aggregate uuid),
 * so a second undo of the same change is refused by the aggregate and, when two arrive at once, by the unique
 * (aggregate_uuid, aggregate_version) of `events`. The counter-Action records its own event next to this one.
 */
final class ChangeUndone extends ShouldBeStored implements ProvidesStoredEventMetadata
{
    public const string TYPE = 'changelog.change.undone';

    public function __construct(
        public readonly string $event_id,
        public readonly string $event_type,
        public readonly string $undo_action,
        public readonly string $summary,
    ) {}

    public function storedEventType(): string
    {
        return self::TYPE;
    }

    public function storedAggregateType(): string
    {
        return 'change';
    }
}
