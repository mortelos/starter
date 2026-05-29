<?php

declare(strict_types=1);

namespace Mortelos\Starter;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class MortelosStarterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/starter.php', 'starter');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mortelos-starter');

        Livewire::addNamespace(
            namespace: 'starter',
            viewPath: __DIR__.'/../resources/views/livewire',
        );

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/starter.php' => config_path('starter.php'),
            ], 'mortelos-starter');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/mortelos-starter'),
            ], 'mortelos-starter-views');

            $this->publishes([
                __DIR__.'/../stubs/Auth/Controllers/PasswordLoginController.php' => app_path('Http/Controllers/Auth/PasswordLoginController.php'),
                __DIR__.'/../stubs/Auth/Controllers/PasskeyAuthenticatedController.php' => app_path('Http/Controllers/Auth/PasskeyAuthenticatedController.php'),
                __DIR__.'/../stubs/Auth/Controllers/AcceptInvitationController.php' => app_path('Http/Controllers/Auth/AcceptInvitationController.php'),
                __DIR__.'/../stubs/Auth/Controllers/TenantSelectController.php' => app_path('Http/Controllers/Auth/TenantSelectController.php'),
                __DIR__.'/../stubs/Auth/Actions/ResolvePostLoginRedirect.php' => app_path('Actions/Auth/ResolvePostLoginRedirect.php'),
                __DIR__.'/../stubs/config/starter.php' => config_path('starter.php'),
            ], 'mortelos-starter-stubs');
        }
    }
}
