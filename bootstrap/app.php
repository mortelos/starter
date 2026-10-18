<?php

use App\Http\Middleware\OnlyBrainChannels;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\LaravelFlare\Facades\Flare;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Channel authorisation for bearer tokens: /api/broadcasting/auth, brain channels only.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api',
        'middleware' => ['api', 'auth:api', OnlyBrainChannels::class],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Flare::handles($exceptions);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'mcp/*'),
        );
    })->create();
