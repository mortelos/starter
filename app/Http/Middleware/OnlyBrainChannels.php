<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class OnlyBrainChannels
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        // Bearer tokens belong to OAuth clients: they may only listen to brain channels, never to app channels added to routes/channels.php.
        abort_unless(str_starts_with($request->string('channel_name')->toString(), 'private-mortel.brain.'), 403);

        return $next($request);
    }
}
