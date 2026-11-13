<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

/** A part of the app (an "onderdeel"): a stable key for rights and filters, and the word a user reads. */
final readonly class ChangePart
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}
}
