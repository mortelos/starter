<?php

declare(strict_types=1);

namespace App\Access;

/**
 * Marker interface for any actor passed to a governance/access decision.
 *
 * Mirrors Mortel\Access\AccessActor 1:1 (R4) so adopting the framework later is
 * a find-replace `App\Access` -> `Mortel\Access`, not a rewrite. The resolver
 * pattern-matches on the concrete type:
 *  - SystemActor  -> bypass person-filtering (maintenance/system calls)
 *  - ActorContext -> full person + tenant + role enforcement
 *  - DeniedActor  -> fail-closed sentinel; all decisions return false
 */
interface AccessActor {}
