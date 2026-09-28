<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the browser suite free of 304 responses. Flux answers a revalidated /flux/flux.js with an
 * empty 304; the in-process server of pest-plugin-browser 4.3 sends that chunked, which leaves stray
 * bytes on the keep-alive socket, so the next request on it fails on Linux and the test hangs.
 *
 * ponytail: test-only workaround; remove once pestphp/pest-plugin-browser#254 is released.
 */
final class WithoutNotModified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->has('If-Modified-Since') || $request->headers->has('If-None-Match')) {
            fwrite(STDERR, 'DEBUG conditional request '.$request->path()."\n");
        }

        $request->headers->remove('If-Modified-Since');
        $request->headers->remove('If-None-Match');

        /** @var Response */
        return $next($request);
    }
}
