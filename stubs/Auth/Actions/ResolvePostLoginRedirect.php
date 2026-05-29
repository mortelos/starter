<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Stub action published by mortelos/starter.
 *
 * Replace with role-aware logic once your roles are in place. The contract:
 *   execute(User $user, string $tenantId): string
 *
 * The starter route bridge (routes/starter.php) calls this after login +
 * tenant-select to decide where the user lands. Common patterns:
 *
 *   - Customers go to /portal/<slug>
 *   - Account managers go to /inbox
 *   - Admins go to /dashboard
 */
final class ResolvePostLoginRedirect
{
    public function execute(Model $user, string $tenantId): string
    {
        // Replace with role-aware routing.
        return route('dashboard');
    }
}
