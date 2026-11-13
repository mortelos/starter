<?php

declare(strict_types=1);

namespace App\Exceptions;

use Mortel\Actions\ActionResult;
use RuntimeException;

/** The counter-Action refused; thrown inside the undo transaction so nothing of the undo stays behind. */
final class UndoRefusedException extends RuntimeException
{
    public function __construct(public readonly ActionResult $result)
    {
        parent::__construct($result->message);
    }
}
