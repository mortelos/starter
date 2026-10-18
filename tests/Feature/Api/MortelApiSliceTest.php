<?php

declare(strict_types=1);

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\ClientRepository;
use Mortel\Api\Live\BrainChanged;
use Mortel\Events\Entity\EntityUpdated;
use Mortel\Models\UteqStoredEvent;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('lists the entities query and the rename action in discovery', function (): void {
    [, $token] = apiSlice();

    $discovery = getJson('/api/mortel/v1', bearer($token))->assertOk();

    expect($discovery->json('brain.id'))->toBe(brainId())
        ->and($discovery->collect('queries')->firstWhere('name', 'entities'))->toMatchArray(['denied' => false])
        ->and($discovery->collect('actions')->firstWhere('name', 'entities.rename'))->toMatchArray(['denied' => false])
        ->and($discovery->json('live'))->toBe(['channel' => 'mortel.brain.'.brainId(), 'type' => 'private']);
});

it('returns the seeded entities from the entities query', function (): void {
    [, $token, $entities] = apiSlice();

    $rows = getJson('/api/mortel/v1/queries/entities', bearer($token))
        ->assertOk()
        ->assertJsonPath('total', 2)
        ->assertJsonPath('rows.0.can', ['entities.rename'])
        ->collect('rows');

    expect($rows->pluck('values.name')->sort()->values()->all())->toBe(['Bakkerij Jansen', 'Garage Pietersen'])
        ->and($rows->pluck('item.ref')->sort()->values()->all())->toBe(collect($entities)->pluck('id')->sort()->values()->all());
});

it('renames an entity with a done receipt, one stored event and a BrainChanged on the brain channel', function (): void {
    [, $token, [$entity]] = apiSlice();
    Event::fake([BrainChanged::class]);

    $item = ['brain' => brainId(), 'type' => 'entities', 'ref' => $entity->id, 'revision' => '1'];

    postJson('/api/mortel/v1/actions/entities.rename', ['item' => $item, 'expected_revision' => '1', 'input' => ['name' => 'Bakkerij De Vries']], [...bearer($token), 'Idempotency-Key' => 'rename-1'])
        ->assertOk()
        ->assertJsonPath('status', 'done')
        ->assertJsonPath('changed.queries', ['entities'])
        ->assertJsonPath('undo.input.name', 'Bakkerij Jansen');

    expect($entity->fresh()?->name)->toBe('Bakkerij De Vries')
        ->and(UteqStoredEvent::where('aggregate_uuid', $entity->id)->where('event_class', EntityUpdated::class)->count())->toBe(1);

    Event::assertDispatchedTimes(BrainChanged::class, 1);
    Event::assertDispatched(BrainChanged::class, function (BrainChanged $event): bool {
        return array_map(fn (PrivateChannel $channel): string => $channel->name, $event->broadcastOn()) === ['private-mortel.brain.'.brainId()]
            && $event->broadcastWith() === ['brain' => brainId(), 'queries' => ['entities']];
    });
});

it('authorises the brain channel for a bearer token', function (): void {
    [, $token] = apiSlice();
    useReverbBroadcaster();

    postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-mortel.brain.'.brainId()], bearer($token))
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('refuses the brain channel without a token', function (): void {
    apiSlice();
    useReverbBroadcaster();

    postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-mortel.brain.'.brainId()])
        ->assertUnauthorized();
});

it('refuses every channel but the brain channel for a bearer token', function (): void {
    [, $token] = apiSlice();
    useReverbBroadcaster();
    Broadcast::channel('test.{id}', fn (): bool => true);

    postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-test.1'], bearer($token))
        ->assertForbidden();
    postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-mortel.brain.'.brainId()], bearer($token))
        ->assertOk();
});

it('refuses another brain channel for a bearer token', function (): void {
    [, $token] = apiSlice();
    useReverbBroadcaster();

    postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-mortel.brain.other'], bearer($token))
        ->assertForbidden();
});

it('asks for consent in Dutch on the OAuth authorize screen', function (): void {
    [$admin] = apiSlice();
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient('Claude', ['https://claude.example/callback']);

    actingAs($admin)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => 'https://claude.example/callback',
            'response_type' => 'code',
            'state' => 'state-1',
        ]))
        ->assertOk()
        ->assertSee('Claude wil toegang tot je werkomgeving')
        ->assertSee('Toestaan')
        ->assertSee('Weigeren');
});

/**
 * phpunit.xml uses the null broadcaster, which authorises every channel; Reverb signs without
 * connecting. Channels belong to the broadcaster that was the default when they were registered,
 * so they are registered again on Reverb.
 */
function useReverbBroadcaster(): void
{
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', '1');

    require base_path('routes/channels.php');
}
