<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
        } else {
            $this->components->info('layout present at resources/views/layouts/app.blade.php');
        }

        return $missing === [] && $invalid === [] ? self::SUCCESS : self::FAILURE;
    }
}
