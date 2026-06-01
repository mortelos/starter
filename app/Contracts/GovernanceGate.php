<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The page-gate seam (R7).
 *
 * Normalizes framework's two divergent access signatures into one cheap host
 * contract that the existing governance/users surfaces already call:
 * `config('starter.governance.access_resolver')` is pointed at this contract and
 * the blade does `app($resolver)->canManage(auth()->user())`.
 *
 * The default implementation (StarterGovernanceGate) is FAIL-CLOSED: it returns
 * false unless a role explicitly grants management. When the framework is
 * installed it can delegate to Mortel\Actions\Policies\CheckPolicy unchanged;
 * the contract stays stable either way.
 */
interface GovernanceGate
{
    public function canManage(?Authenticatable $user): bool;
}
