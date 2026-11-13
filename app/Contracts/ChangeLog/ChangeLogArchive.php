<?php

declare(strict_types=1);

namespace App\Contracts\ChangeLog;

use App\Support\ChangeLog\ChangeLogArchivePage;
use App\Support\ChangeLog\ChangeLogFilter;

/**
 * Contract outlet: history from before the app moved to events, read only and without undo. The log shows it under
 * "Vóór de overstap" once the stored events of the filter run out, and the CSV export appends it. The default
 * (App\Support\ChangeLog\NoChangeLogArchive) has none.
 */
interface ChangeLogArchive
{
    /**
     * Archive rows that match the filter, newest first, after the cursor `$after` (null: from the newest).
     *
     * @param  list<string>  $parts  the part keys the user may see; leave out rows of other parts
     */
    public function entries(ChangeLogFilter $filter, array $parts, ?string $after, int $limit): ChangeLogArchivePage;
}
