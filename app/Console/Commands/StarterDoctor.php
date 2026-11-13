<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;
use Mortel\Contracts\TenantResolver;
use Mortel\Models\UteqStoredEvent;
use Mortel\Repositories\UteqStoredEventRepository;
use Mortel\SocialLogin\SocialLogin;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

final class StarterDoctor extends Command
{
    protected $signature = 'starter:doctor';

    protected $description = 'Diagnose MortelOS Starter wiring: auth contracts, route bridge, layout, namespaces';

    public function handle(): int
    {
        $required = [
            'starter.auth.post_login_redirect_resolver',
            'starter.auth.controllers.password_login',
            'starter.auth.controllers.passkey_authenticated',
            'starter.auth.controllers.accept_invitation',
            'starter.users.resolver',
            'starter.users.access_resolver',
        ];

        $missing = [];
        $invalid = [];

        foreach ($required as $key) {
            $value = config($key);

            if (! is_string($value) || $value === '') {
                $missing[] = $key;
                $this->components->error($key.' is empty');

                continue;
            }

            if (! class_exists($value)) {
                $invalid[] = $key;
                $this->components->error($key.' points to a class that does not exist: '.$value);

                continue;
            }

            $this->components->info($key.' → '.$value);
        }

        // Host fills these in; empty is a valid state, so a warning that says what stays off.
        $optional = [
            'starter.navigation.sidebar_resolver' => 'the sidebar stays empty',
            'starter.onboarding.resolver' => '/onboarding shows an empty state instead of the wizard',
            'starter.inbox.item_type_resolver' => 'the inbox never opens the intake detail pane',
        ];

        foreach ($optional as $key => $consequence) {
            $value = config($key);

            if (! is_string($value) || $value === '') {
                $this->components->warn($key.' is empty: '.$consequence);

                continue;
            }

            if (! class_exists($value) && ! app()->bound($value)) {
                $invalid[] = $key;
                $this->components->error($key.' is neither a class nor a container binding: '.$value);

                continue;
            }

            $this->components->info($key.' → '.$value);
        }

        if (! is_file(base_path('routes/starter.php'))) {
            $this->components->error('routes/starter.php is missing');
        } else {
            $this->components->info('routes/starter.php present');
        }

        $webRoutes = file_get_contents(base_path('routes/web.php')) ?: '';
        if (! str_contains($webRoutes, "require __DIR__.'/starter.php'")) {
            $this->components->warn('routes/web.php does not require routes/starter.php');
        } else {
            $this->components->info('routes/web.php requires starter.php');
        }

        $view = resource_path('views/layouts/app.blade.php');
        if (! is_file($view)) {
            $this->components->error('resources/views/layouts/app.blade.php is missing');
            $missing[] = 'resources.views.layouts.app';
        } else {
            $this->components->info('layout present at resources/views/layouts/app.blade.php');
        }

        $this->checkEventStore($missing, $invalid);
        $this->checkTwoFactor($missing, $invalid);
        $this->checkSocialLogin($missing, $invalid);
        $this->checkSingleTenantFrameworkBaseline($missing, $invalid);

        // The spine rails of mortelos/app-standards. EnforcePolicy only sits on the routes
        // after step 2b (governance on the framework); until then it is skipped on purpose.
        if ($this->call('mortelos:spine:check', ['--skip' => ['enforce-policy-route']]) !== self::SUCCESS) {
            $invalid[] = 'spine';
        }

        if ($this->call('mortelos:outlets:check') !== self::SUCCESS) {
            $invalid[] = 'outlets';
        }

        return $missing === [] && $invalid === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Fortify two-factor (docs/two-factor.md): the feature, the guard social login hands over to, the columns, the
     * limiter and the challenge routes.
     *
     * @param  list<string>  $missing
     * @param  list<string>  $invalid
     */
    private function checkTwoFactor(array &$missing, array &$invalid): void
    {
        if (! Features::enabled(Features::twoFactorAuthentication())) {
            $invalid[] = 'fortify.features';
            $this->components->error('fortify.features does not enable two-factor authentication');
        } else {
            $this->components->info('Fortify two-factor feature on');
        }

        $guard = config('fortify.guard');

        if ($guard !== config('auth.defaults.guard')) {
            $invalid[] = 'fortify.guard';
            $this->components->error('fortify.guard must be the default guard ('.(is_string(config('auth.defaults.guard')) ? config('auth.defaults.guard') : '?').'), or a sign-in through social login throws');
        } else {
            $this->components->info('fortify.guard → '.(is_string($guard) ? $guard : '?'));
        }

        if (Schema::hasTable('users')) {
            foreach (['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    $missing[] = 'database.users.'.$column;
                    $this->components->error('database column users.'.$column.' is missing');
                }
            }
        }

        if (RateLimiter::limiter('two-factor') === null) {
            $invalid[] = 'rate-limiter.two-factor';
            $this->components->error('rate limiter two-factor is not defined: every code would end in a 500');
        } else {
            $this->components->info('rate limiter two-factor defined');
        }

        foreach (['two-factor.login', 'two-factor.login.store'] as $name) {
            if (! Route::has($name)) {
                $invalid[] = 'route.'.$name;
                $this->components->error('route '.$name.' is missing from routes/starter.php');
            }
        }
    }

    /**
     * Social login (docs/social-login.md). A provider without keys is a valid state (no button), so that is a
     * warning that says what stays off; a provider that is listed but unknown is an error.
     *
     * @param  list<string>  $missing
     * @param  list<string>  $invalid
     */
    private function checkSocialLogin(array &$missing, array &$invalid): void
    {
        if (! Schema::hasTable('social_identities')) {
            $missing[] = 'database.social_identities';
            $this->components->error('database table social_identities is missing (publish and run the social-login migration)');
        } else {
            $this->components->info('database table social_identities present');
        }

        $listed = config('social-login.providers');
        $listed = is_array($listed) ? $listed : [];
        $known = array_keys(SocialLogin::PROVIDERS);

        foreach ($listed as $name) {
            if (! is_string($name) || ! in_array($name, $known, true)) {
                $invalid[] = 'social-login.providers';
                $this->components->error('social-login.providers lists an unknown provider: '.(is_string($name) ? $name : get_debug_type($name)));
            }
        }

        $available = array_map(static fn ($provider): string => $provider->name(), app(SocialLogin::class)->available());

        if ($available === []) {
            $this->components->warn('no social login provider has its client id and secret: the login page shows no Google or Microsoft button');
        } else {
            $this->components->info('social login buttons for: '.implode(', ', $available));
        }

        foreach ($known as $name) {
            if (in_array($name, $listed, true) && ! in_array($name, $available, true)) {
                $this->components->warn('social login provider '.$name.' is listed but has no client id and secret: no button');
            }
        }
    }

    /**
     * @param  list<string>  $missing
     * @param  list<string>  $invalid
     */
    private function checkEventStore(array &$missing, array &$invalid): void
    {
        if (! Schema::hasTable('events')) {
            $missing[] = 'database.events';
            $this->components->error('database table events is missing');
        } else {
            $this->components->info('database table events present');
        }

        $storedEventModel = config('event-sourcing.stored_event_model');
        if ($storedEventModel !== UteqStoredEvent::class) {
            $invalid[] = 'event-sourcing.stored_event_model';
            $this->components->error('event-sourcing.stored_event_model must be '.UteqStoredEvent::class);
        } else {
            $this->components->info('event-sourcing.stored_event_model → '.$storedEventModel);
        }

        $storedEventRepository = config('event-sourcing.stored_event_repository');
        if ($storedEventRepository !== UteqStoredEventRepository::class) {
            $invalid[] = 'event-sourcing.stored_event_repository';
            $this->components->error('event-sourcing.stored_event_repository must be '.UteqStoredEventRepository::class);
        } else {
            $this->components->info('event-sourcing.stored_event_repository → '.$storedEventRepository);
        }

        try {
            /** @var StoredEventRepository $repository */
            $repository = app(StoredEventRepository::class);
        } catch (\Throwable $exception) {
            $invalid[] = 'event-sourcing.repository_binding';
            $this->components->error('StoredEventRepository cannot be resolved: '.$exception->getMessage());

            return;
        }

        if (! $repository instanceof UteqStoredEventRepository) {
            $invalid[] = 'event-sourcing.repository_binding';
            $this->components->error('StoredEventRepository resolves to '.get_debug_type($repository));

            return;
        }

        $this->components->info('StoredEventRepository resolves to Mortel event store repository');
    }

    /**
     * @param  list<string>  $missing
     * @param  list<string>  $invalid
     */
    private function checkSingleTenantFrameworkBaseline(array &$missing, array &$invalid): void
    {
        try {
            /** @var TenantResolver $resolver */
            $resolver = app(TenantResolver::class);
        } catch (\Throwable $exception) {
            $invalid[] = 'tenancy.resolver';
            $this->components->error('TenantResolver cannot be resolved: '.$exception->getMessage());

            return;
        }

        $tenantId = $resolver->id();

        if (! is_string($tenantId) || $tenantId === '') {
            $invalid[] = 'tenancy.resolver.id';
            $this->components->error('TenantResolver must return a non-empty tenant id');
        } else {
            $this->components->info('TenantResolver tenant id → '.$tenantId);
        }

        if (! $resolver->initialized()) {
            $invalid[] = 'tenancy.resolver.initialized';
            $this->components->error('TenantResolver must be initialized for the single-tenant baseline');
        } else {
            $this->components->info('TenantResolver is initialized');
        }

        foreach (['tenants', 'tenant_user', 'entities', 'entity_links', 'roles', 'policies', 'snapshots', 'invitations'] as $table) {
            if (! Schema::hasTable($table)) {
                $missing[] = 'database.'.$table;
                $this->components->error('database table '.$table.' is missing');

                continue;
            }

            $this->components->info('database table '.$table.' present');
        }

        $requiredColumns = [
            'roles' => ['org_id', 'branch_id', 'scope'],
            'policies' => ['org_id', 'branch_id', 'scope', 'actions'],
            'entities' => ['org_id', 'branch_id', 'field_classifications', 'owner_user_id', 'visibility', 'classification', 'access_metadata'],
            'tenant_user' => ['tenant_id', 'user_id', 'role', 'role_id'],
        ];

        foreach ($requiredColumns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    $missing[] = 'database.'.$table.'.'.$column;
                    $this->components->error('database column '.$table.'.'.$column.' is missing');
                }
            }
        }

        if (is_string($tenantId) && $tenantId !== '' && Schema::hasTable('tenants')) {
            $exists = \DB::table('tenants')->where('id', $tenantId)->exists();

            if (! $exists) {
                $missing[] = 'database.tenants.default';
                $this->components->error('default tenant row is missing');
            } else {
                $this->components->info('default tenant row present');
            }
        }
    }
}
