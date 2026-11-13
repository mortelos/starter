<?php

declare(strict_types=1);

namespace Mortel\OwnTables;

use RuntimeException;

/*
 * The starter is on mortelos/framework 0.7.1; OwnTableConflict arrives in 0.7.68. The change log recognises it by
 * class name, so the tests declare the same class with the same message shape when the framework does not have it.
 */
if (! class_exists(OwnTableConflict::class, false) && ! class_exists(OwnTableConflict::class)) {
    final class OwnTableConflict extends RuntimeException {}
}
