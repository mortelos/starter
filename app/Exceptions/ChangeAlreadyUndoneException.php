<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ChangeAlreadyUndoneException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Deze wijziging is al ongedaan gemaakt.');
    }
}
