<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class StarterServiceProvider extends ServiceProvider
{
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
