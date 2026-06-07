<?php

declare(strict_types=1);

namespace App\Providers;

use App\Access\StarterGovernanceGate;
use App\Contracts\GovernanceGate;
use App\Support\SingleTenantResolver;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
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
    }

    public function boot(): void
    {
        // Expose the starter views under a stable namespace so route bindings
        // like 'starter::pages.dashboard.dashboard' resolve regardless of
        // future host customizations of the resources/views/ layout.
        $this->loadViewsFrom(resource_path('views'), 'mortelos-starter');

        Livewire::addNamespace(
            namespace: 'starter',
            viewPath: resource_path('views/livewire'),
        );
    }
}
