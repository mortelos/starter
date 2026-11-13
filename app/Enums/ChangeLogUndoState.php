<?php

declare(strict_types=1);

namespace App\Enums;

/** What the change log offers on one row. */
enum ChangeLogUndoState: string
{
    /** The viewer may undo it now. */
    case Available = 'available';

    /** It was undone; the row links to the undo. */
    case Undone = 'undone';

    /** There is no undo, it expired, or the viewer may not; the row says why when it can. */
    case Unavailable = 'unavailable';
}
