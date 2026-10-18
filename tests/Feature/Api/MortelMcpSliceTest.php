<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mortel\Events\Entity\EntityUpdated;
use Mortel\Models\UteqStoredEvent;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('lists the query tool and the action tool', function (): void {
    [, $token] = apiSlice();

    $tools = mcp($token, 'tools/list')->assertOk()->collect('result.tools')->pluck('name');

    expect($tools)->toContain('query_entities', 'action_entities-rename');
});

it('returns the seeded entities from the query tool', function (): void {
    [, $token] = apiSlice();

    mcp($token, 'tools/call', ['name' => 'query_entities', 'arguments' => []])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.total', 2);
});

it('renames an entity through the action tool', function (): void {
    [, $token, [$entity]] = apiSlice();
    $item = ['brain' => brainId(), 'type' => 'entities', 'ref' => $entity->id, 'revision' => '1'];

    mcp($token, 'tools/call', ['name' => 'action_entities-rename', 'arguments' => [
        'item' => $item,
        'expected_revision' => '1',
        'idempotency_key' => 'mcp-rename-1',
        'input' => ['name' => 'Bakkerij De Vries'],
    ]])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.status', 'done');

    expect($entity->fresh()?->name)->toBe('Bakkerij De Vries')
        ->and(UteqStoredEvent::where('aggregate_uuid', $entity->id)->where('event_class', EntityUpdated::class)->count())->toBe(1);
});

it('refuses the MCP server without a token', function (): void {
    postJson('/mcp/mortel', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertUnauthorized();
});

/**
 * @param  array<string, mixed>  $params
 * @return TestResponse<Response>
 */
function mcp(string $token, string $method, array $params = []): TestResponse
{
    return postJson('/mcp/mortel', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], bearer($token));
}
