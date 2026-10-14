<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * The invitation can no longer change this way. The message is shown on the
 * screen as is, so it is Dutch.
 */
final class InvitationNotOpenException extends RuntimeException
{
    public static function unknown(): self
    {
        return new self('Deze uitnodiging bestaat niet.');
    }

    public static function accepted(): self
    {
        return new self('Deze uitnodiging is al gebruikt.');
    }

    public static function revoked(): self
    {
        return new self('Deze uitnodiging is ingetrokken.');
    }

    public static function expired(): self
    {
        return new self('Deze uitnodiging is verlopen.');
    }
}
