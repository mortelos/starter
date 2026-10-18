<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Mortel\Api\MortelApi;

// The Mortel API (/api/mortel/v1) for bearer tokens. The starter is single-tenant,
// so the brain is always its one tenant and no tenancy middleware is needed.
// Channel authorisation for bearer tokens (/api/broadcasting/auth) is in bootstrap/app.php.
Route::middleware('auth:api')->group(fn () => MortelApi::routes());
