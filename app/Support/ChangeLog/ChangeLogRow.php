<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Enums\ChangeLogUndoState;
use Carbon\CarbonImmutable;

/** One line of the change log as the page and the CSV export show it. */
final readonly class ChangeLogRow
{
    public function __construct(
        public string $id,
        public CarbonImmutable $at,
        public string $sentence,
        public ?string $detail,
        public ChangePart $part,
        public ChangeLogActorLabel $actor,
        public ChangeLogUndoState $undo,
        public ?string $note = null,
        public ?string $undoneBy = null,
        public bool $archived = false,
    ) {}
}
