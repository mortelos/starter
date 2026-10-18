<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mortel\Support\Scalar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('shows the redirect host and warns about a self-registered client', function (): void {
    [$admin] = apiSlice();
    $clientId = Scalar::string(postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => ['https://example.test/callback'],
    ])->assertSuccessful()->json('client_id'));

    actingAs($admin)
        ->get('/oauth/authorize?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => 'https://example.test/callback',
            'response_type' => 'code',
            'state' => 'state-1',
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', Str::random(64), true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ]))
        ->assertOk()
        ->assertSeeText('Na toestemming gaat je browser naar example.test.')
        ->assertSeeText('Deze app heeft zichzelf aangemeld. Sta alleen toe als je deze app zelf net hebt gekoppeld.')
        ->assertDontSee('https://example.test/callback')
        ->assertDontSee('state-1')
        ->assertSee('Claude wil toegang tot je werkomgeving')
        ->assertSee('Toestaan')
        ->assertSee('Weigeren');
});
