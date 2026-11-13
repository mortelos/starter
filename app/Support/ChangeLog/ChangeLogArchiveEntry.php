<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Enums\ChangeLogActorKind;
use Carbon\CarbonImmutable;

/** One row of history from before the move to events. Read only: it has no undo. */
final readonly class ChangeLogArchiveEntry
{
    public function __construct(
        public string $id,
        public CarbonImmutable $at,
        public string $actorName,
        public ChangeLogActorKind $actorKind,
        public string $sentence,
        public string $part,
        public ?string $detail = null,
    ) {}
}
