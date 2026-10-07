<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| MortelOS Spine
|--------------------------------------------------------------------------
|
| The governance rails every MortelOS portal inherits. See the contract in
| .mortelos/spine-contract.md (published with this file).
|
| Kept free of env and other Laravel helpers on purpose: the PHPStan
| discipline rule in mortelos/dev-tools includes this file directly, outside
| a booted application.
|
*/

return [

    /*
    | Models whose rows are governed state: they may only change through an
    | aggregate, a projector, or a tagged governed action (C2). Class strings,
    | not imports — this package does not depend on mortelos/framework.
    */

    'governed_models' => [
        'Mortel\Models\Entity',
        'Mortel\Models\EntityLink',
        'Mortel\Models\Policy',
        'Mortel\Models\Role',
        'Mortel\Models\InboxItem',
        'Mortel\Models\Channel',
        'Mortel\Models\Workflow',
        'Mortel\Models\WorkflowRun',
        'Mortel\Models\Document',
        'Mortel\Models\DocumentVersion',
    ],

    /*
    | Classes allowed to write governed models directly. fnmatch() patterns
    | against the fully qualified class name of the writing class.
    */

    'writers' => [
        'Mortel\Aggregates\*',
        'Mortel\Projectors\*',
        'App\Aggregates\*',
        'App\Projectors\*',
    ],

    /*
    | Attributes that mark a class or method as a sanctioned governed writer,
    | for writers that do not fit the namespace patterns above.
    */

    'writer_attributes' => [
        'Mortelos\AppStandards\Spine\GovernedWriter',
    ],

];
