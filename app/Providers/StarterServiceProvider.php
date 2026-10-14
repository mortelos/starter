<?php

declare(strict_types=1);

namespace App\Providers;

use App\Access\StarterGovernanceGate;
use App\Contracts\GovernanceGate;
use App\Support\SingleTenantResolver;
use Illuminate\Support\ServiceProvider;
use Mortel\Actions\Inbox\AuthorizeInboxItemAccess;
use Mortel\Actions\Inbox\OwnerScopedInboxItemAccess;
use Mortel\Contracts\TenantResolver;

final class StarterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the deny-by-default governance gate (D11). The governance/users
        // surfaces fall back to this contract when no access_resolver is
        // configured, so config/starter.php stays untouched.
        $this->app->bind(GovernanceGate::class, StarterGovernanceGate::class);

        $this->app->scoped(TenantResolver::class, SingleTenantResolver::class);

        // The framework denies every inbox item by default; the owner may view and decide on their own items.
        $this->app->bind(AuthorizeInboxItemAccess::class, OwnerScopedInboxItemAccess::class);
    }
}
