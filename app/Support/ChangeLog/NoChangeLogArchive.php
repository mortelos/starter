<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Contracts\ChangeLog\ChangeLogArchive;

/** Default of the ChangeLogArchive outlet: an app that started on events has no history from before. */
final class NoChangeLogArchive implements ChangeLogArchive
{
    public function entries(ChangeLogFilter $filter, array $parts, ?string $after, int $limit): ChangeLogArchivePage
    {
        return new ChangeLogArchivePage([], null);
    }
}
