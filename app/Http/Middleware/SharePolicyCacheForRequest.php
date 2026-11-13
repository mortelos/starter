<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Mortel\Support\PolicyCache;
use Symfony\Component\HttpFoundation\Response;

/**
 * One policy cache per HTTP request (the web group, so Livewire updates too): a screen that checks several rights
 * (the change log asks one per row action, the topbar one) reads `policies` once. The cache lives on the request, so
 * it ends with it. Outside a request (a queue job, an agent run, a command) every CheckPolicy gets a fresh cache, as
 * mortelos/framework intends, so a right that another process revokes counts in the next step. The framework's
 * PolicyProjector invalidates the same instance when a policy changes during the request.
 */
final class SharePolicyCacheForRequest
{
    public const string ATTRIBUTE = 'mortel.policy_cache';

    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATTRIBUTE, new PolicyCache);

        return $next($request);
    }

    /** The binding of PolicyCache: the cache of the current request, or a fresh one. */
    public static function resolve(Container $container): PolicyCache
    {
        $request = $container->bound('request') ? $container->make('request') : null;
        $cache = $request instanceof Request ? $request->attributes->get(self::ATTRIBUTE) : null;

        return $cache instanceof PolicyCache ? $cache : new PolicyCache;
    }
}
