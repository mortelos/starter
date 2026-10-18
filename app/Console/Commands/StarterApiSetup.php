<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Mortel\Support\Scalar;
use RuntimeException;

/**
 * Prepares the Mortel API on this machine; part of `composer setup` and safe to run again:
 * it only adds what is missing.
 */
final class StarterApiSetup extends Command
{
    protected $signature = 'starter:api-setup';

    protected $description = 'Prepare the Mortel API: Passport keys, a personal access client and Reverb credentials in .env';

    public function handle(ClientRepository $clients): int
    {
        if (is_file(Passport::keyPath('oauth-private.key'))) {
            $this->components->info('De Passport-sleutels zijn er al.');
        } elseif ($this->call('passport:keys') !== self::SUCCESS) {
            return self::FAILURE;
        }

        try {
            $clients->personalAccessClient('users');
            $this->components->info('De personal access client is er al.');
        } catch (RuntimeException) {
            $clients->createPersonalAccessGrantClient(Scalar::string(config('app.name')).' personal access', 'users');
            $this->components->info('Personal access client aangemaakt.');
        }

        $this->fillReverbCredentials();

        return self::SUCCESS;
    }

    /** Fills REVERB_APP_ID, REVERB_APP_KEY and REVERB_APP_SECRET in .env when they are empty, as reverb:install does. */
    private function fillReverbCredentials(): void
    {
        $path = app()->environmentFilePath();

        if (! is_file($path)) {
            $this->components->warn('Geen .env gevonden, dus de Reverb-gegevens zijn niet ingevuld.');

            return;
        }

        $values = [
            'REVERB_APP_ID' => (string) random_int(100_000, 999_999),
            'REVERB_APP_KEY' => Str::lower(Str::random(20)),
            'REVERB_APP_SECRET' => Str::lower(Str::random(20)),
        ];
        $contents = (string) file_get_contents($path);
        $filled = $contents;

        foreach ($values as $key => $value) {
            $filled = (string) preg_replace('/^'.$key.'=$/m', $key.'='.$value, $filled);
        }

        if ($filled === $contents) {
            $this->components->info('De Reverb-gegevens staan al in .env.');

            return;
        }

        file_put_contents($path, $filled);
        $this->components->info('Reverb-gegevens ingevuld in .env.');
    }
}
