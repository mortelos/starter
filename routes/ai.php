<?php

declare(strict_types=1);

use Laravel\Mcp\Facades\Mcp;
use Mortel\Api\Http\Middleware\ResolveApiContext;
use Mortel\MCP\Servers\MortelServer;

// laravel/mcp loads this file itself.
Mcp::oauthRoutes();

Mcp::web('/mcp/mortel', MortelServer::class)->middleware(['auth:api', ResolveApiContext::class]);
