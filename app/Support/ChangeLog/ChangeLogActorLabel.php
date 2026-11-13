<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use App\Enums\ChangeLogActorKind;

/** Who made a change, as the change log shows it. */
final readonly class ChangeLogActorLabel
{
    public function __construct(
        public ChangeLogActorKind $kind,
        public string $name,
        public ?string $onBehalfOf = null,
    ) {}

    public function full(): string
    {
        return $this->onBehalfOf === null ? $this->name : $this->name.' namens '.$this->onBehalfOf;
    }
}
