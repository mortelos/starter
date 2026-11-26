<?php

declare(strict_types=1);

use App\Http\Middleware\SharePolicyCacheForRequest;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mortel\Agent\Tools\AgentToolRegistry;
use Mortel\Enums\PolicyScope;
use Mortel\Models\AgentRun;
use Mortel\Support\PolicyCache;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/*
| One policy cache per HTTP request (SharePolicyCacheForRequest). It is in the container only while the request
| runs: a queue worker must never find one, or AgentToolRegistry keeps it for the rest of the process.
*/

it('shares one cache inside a request and a different one in the next request', function (): void {
    $seen = [];
    Route::middleware('web')->get('/_probe/cache', function () use (&$seen) {
        $seen[] = [app(PolicyCache::class), app(PolicyCache::class)];

        return response('ok');
    });

    get('/_probe/cache')->assertOk();
    get('/_probe/cache')->assertOk();

    expect($seen[0][0])->toBe($seen[0][1])
        ->and($seen[1][0])->toBe($seen[1][1])
        ->and($seen[0][0])->not->toBe($seen[1][0]);
});

it('leaves nothing in the container after a request, so a queue worker gets a fresh cache for every check', function (): void {
    get('/up')->assertOk();

    expect(app()->isShared(PolicyCache::class))->toBeFalse()
        ->and(app(PolicyCache::class))->not->toBe(app(PolicyCache::class));
});

it('puts back a cache that was already there instead of dropping it', function (): void {
    $outer = new PolicyCache;
    app()->instance(PolicyCache::class, $outer);

    (new SharePolicyCacheForRequest)->handle(request(), fn () => response('ok'));

    expect(app(PolicyCache::class))->toBe($outer);
});

it('makes a cache that was already there read policies again after a nested request, so a right revoked inside it counts', function (): void {
    $outer = new PolicyCache;
    app()->instance(PolicyCache::class, $outer);
    $reads = 0;
    DB::listen(function (QueryExecuted $query) use (&$reads): void {
        if (preg_match('/\bfrom\s+["`]?policies["`]?(\s|$)/i', $query->sql) === 1) {
            $reads++;
        }
    });

    $outer->forRole('role', PolicyScope::Action);
    $outer->forRole('role', PolicyScope::Action);
    (new SharePolicyCacheForRequest)->handle(request(), fn () => response('ok'));
    $outer->forRole('role', PolicyScope::Action);

    expect($reads)->toBe(2);
});

it('puts the middleware in the web group', function (): void {
    expect(app('router')->getMiddlewareGroups()['web'])->toContain(SharePolicyCacheForRequest::class);
});

it('keeps no cache in a queue worker after an agent tool call', function (): void {
    // AgentToolRegistry puts back whatever cache it found bound; outside a request that must be none.
    try {
        app(AgentToolRegistry::class)->execute(new AgentRun, 'no.such.tool', []);
    } catch (Throwable) {
        // The call itself does not matter, only what the registry leaves in the container.
    }

    expect(app()->isShared(PolicyCache::class))->toBeFalse();
});
