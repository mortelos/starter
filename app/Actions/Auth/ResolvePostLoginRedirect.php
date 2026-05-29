<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Database\Eloquent\Model;

/**
 * Default post-login redirect resolver.
 *
 * The starter route bridge (routes/starter.php) calls this after login +
 * tenant-select to decide where the user lands. Replace the body with
 * role-aware routing as your roles are introduced. Common patterns:
 *
 *   - Customers     → /portal/<slug>
 *   - Account mgrs  → /inbox
 *   - Admins        → /dashboard
 */
final class ResolvePostLoginRedirect
{
    public function execute(Model $user, string $tenantId): string
    {
        return route('dashboard');
    }
}
