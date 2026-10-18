<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mortel\Support\Scalar;

use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

function authorizeUrl(): string
{
    $clientId = Scalar::string(postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://example.test/callback'],
    ])->assertSuccessful()->json('client_id'));

    $query = [
        'client_id' => $clientId,
        'redirect_uri' => 'https://example.test/callback',
        'response_type' => 'code',
        'state' => 'state-1',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', Str::random(64), true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ];

    // Laravel stores the full url with its query sorted by key.
    ksort($query);

    return url('/oauth/authorize?'.http_build_query($query));
}

it('sends a guest back to the consent page after a password login', function (): void {
    apiSlice();
    $authorizeUrl = authorizeUrl();

    get($authorizeUrl)->assertRedirect(route('login'));

    post(route('auth.password-login'), ['email' => 'admin@example.test', 'password' => 'password'])
        ->assertRedirect($authorizeUrl);
});

it('lands on home after a password login without an intended url', function (): void {
    apiSlice();

    post(route('auth.password-login'), ['email' => 'admin@example.test', 'password' => 'password'])
        ->assertRedirect(route('home'));
});

it('never follows an intended url that points at another host', function (): void {
    apiSlice();

    // A guest POST to an auth route makes Laravel store the Referer as the intended url.
    from('https://evil.example/phish')->post(route('logout'))->assertRedirect(route('login'));

    post(route('auth.password-login'), ['email' => 'admin@example.test', 'password' => 'password'])
        ->assertRedirect(route('home'));
});
