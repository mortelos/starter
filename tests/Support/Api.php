<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Mortel\Aggregates\EntityAggregate;
use Mortel\Jobs\GenerateEmbeddingJob;
use Mortel\Models\Entity;

use function Pest\Laravel\seed;

/**
 * Seeds the starter, two entities of its brain and returns the seeded admin with a real
 * personal access token. Creates the Passport keys once per machine (storage/*.key is ignored by git).
 *
 * @return array{0: User, 1: string, 2: list<Entity>}
 */
function apiSlice(): array
{
    // Every entity change queues an embedding, which would call the AI provider.
    Queue::fake([GenerateEmbeddingJob::class]);
    seed(DatabaseSeeder::class);

    if (! is_file(Passport::keyPath('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }

    app(ClientRepository::class)->createPersonalAccessGrantClient('Test personal access', 'users');

    $entities = [];

    foreach (['Bakkerij Jansen', 'Garage Pietersen'] as $name) {
        $id = strtolower((string) Str::ulid());
        EntityAggregate::retrieve($id)->create($name, 'customer', [], brainId(), 'main', visibility: 'tenant')->persist();
        $entities[] = Entity::query()->findOrFail($id);
    }

    $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();

    return [$admin, $admin->createToken('test')->accessToken, $entities];
}

function brainId(): string
{
    $brain = config('starter.tenancy.default_tenant_id', 'default');

    return is_string($brain) ? $brain : 'default';
}

/** @return array<string, string> */
function bearer(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}
