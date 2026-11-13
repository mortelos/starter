<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

/** One page of archive rows and the cursor of the next page (null: this was the last). */
final readonly class ChangeLogArchivePage
{
    /** @param  list<ChangeLogArchiveEntry>  $entries */
    public function __construct(
        public array $entries,
        public ?string $next,
    ) {}
}
