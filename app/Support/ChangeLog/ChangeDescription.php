<?php

declare(strict_types=1);

namespace App\Support\ChangeLog;

use Mortel\Actions\Undo;

/**
 * What a describer says about one stored event: a readable sentence, an optional second line, and either the Undo
 * recipe that reverses it (a counter-Action registered in the UndoRegistry) or, when it cannot be reversed, why not.
 */
final readonly class ChangeDescription
{
    public function __construct(
        public string $sentence,
        public ?string $detail = null,
        public ?Undo $undo = null,
        public ?string $noUndoReason = null,
    ) {}
}
