<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Mortel\Contracts\TenantResolver;
use Mortel\Models\UteqStoredEvent;
use Mortel\Repositories\UteqStoredEventRepository;
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
        $this->checkSingleTenantFrameworkBaseline($missing, $invalid);

        return $missing === [] && $invalid === [] ? self::SUCCESS : self::FAILURE;
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

        foreach (['tenants', 'tenant_user', 'entities', 'entity_links', 'roles', 'policies'] as $table) {
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
