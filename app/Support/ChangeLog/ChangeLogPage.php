<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

/** One page of the change log: stored events, and archive rows once the events of the filter run out. */
final readonly class ChangeLogPage
{
    /**
     * @param  list<ChangeLogRow>  $rows
     * @param  list<ChangeLogRow>  $archive
     * @param  array{0: string, 1: string}|null  $newest  [created_at, id] of the first event on the page
     */
    public function __construct(
        public array $rows,
        public ?string $older,
        public ?string $newer,
        public array $archive = [],
        public ?string $archiveOlder = null,
        public ?array $newest = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rows === [] && $this->archive === [];
    }
}
