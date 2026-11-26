<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Mortel\Support\PolicyCache;
use Symfony\Component\HttpFoundation\Response;

/**
 * One policy cache per HTTP request (the web group, so Livewire updates too): a screen that checks several rights
 * (the change log asks one per row action, the topbar one) reads `policies` once.
 *
 * The cache is put in the container for the length of the request and removed in a `finally`, never bound for good:
 * a binding makes `app()->bound(PolicyCache::class)` true in a queue worker too, and the framework's
 * AgentToolRegistry then "puts back" the cache it found there for the rest of the process, across jobs and tenants.
 * Outside a request (a queue job, an agent run, a command) nothing is bound, so every CheckPolicy gets a fresh cache,
 * as mortelos/framework intends, so a right that another process revokes counts in the next step. The framework's
 * PolicyProjector invalidates the same instance when a policy changes during the request. Under Octane the `finally`
 * ends the cache before the next request on the worker.
 */
final class SharePolicyCacheForRequest
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $previous = app()->isShared(PolicyCache::class) ? app(PolicyCache::class) : null;
        app()->instance(PolicyCache::class, new PolicyCache);

        try {
            return $next($request);
        } finally {
            $previous instanceof PolicyCache
                ? app()->instance(PolicyCache::class, $previous)
                : app()->forgetInstance(PolicyCache::class);
        }
    }
}
