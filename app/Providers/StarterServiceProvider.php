<?php

declare(strict_types=1);

namespace App\Providers;

use App\Access\StarterGovernanceGate;
use App\Actions\Invitations\RevokeInvitation;
use App\Contracts\GovernanceGate;
use App\Support\SingleTenantResolver;
use App\Support\StarterTenantInvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Mortel\Actions\ActionResult;
use Mortel\Actions\Inbox\AuthorizeInboxItemAccess;
use Mortel\Actions\Inbox\OwnerScopedInboxItemAccess;
use Mortel\Actions\UndoRegistry;
use Mortel\Contracts\TenantInvitationService;
use Mortel\Contracts\TenantResolver;
use Mortel\Support\Actor;
use Mortel\Support\Scalar;
use Symfony\Component\HttpFoundation\Response;

final class StarterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind the deny-by-default governance gate (D11). The governance/users
        // surfaces fall back to this contract when no access_resolver is
        // configured, so config/starter.php stays untouched.
        $this->app->bind(GovernanceGate::class, StarterGovernanceGate::class);

        // Contract outlet of mortelos/framework: see `php artisan mortelos:outlets:list framework`.
        $this->app->scoped(TenantResolver::class, SingleTenantResolver::class);

        $this->app->bind(TenantInvitationService::class, StarterTenantInvitationService::class);

        // The framework denies every inbox item by default; the owner may view and decide on their own items.
        $this->app->bind(AuthorizeInboxItemAccess::class, OwnerScopedInboxItemAccess::class);
    }

    public function boot(): void
    {
        // The consent screen of /oauth/authorize, for MCP clients and other OAuth apps.
        Passport::authorizationView(fn (array $parameters): Response => response()->view('auth.oauth-authorize', $parameters + self::consentDetails($parameters)));

        // Counter-Actions: an Undo in an ActionResult names one of these.
        app(UndoRegistry::class)->register(
            RevokeInvitation::class,
            fn (array $params, Actor $actor): ActionResult => app(RevokeInvitation::class)->handle(Scalar::string($params['invitation_id'] ?? null), $actor),
        );
    }

    /**
     * Anyone can register a client with any name, so the consent screen shows where the browser goes and who registered the client.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{redirectHost: string|null, selfRegistered: bool}
     */
    private static function consentDetails(array $parameters): array
    {
        $client = $parameters['client'] ?? null;
        $request = $parameters['request'] ?? null;
        if (! $client instanceof Client || ! $request instanceof Request) {
            return ['redirectHost' => null, 'selfRegistered' => false];
        }

        // The request's redirect_uri is already checked against the client's registered ones; without it Passport uses the registered one.
        $redirectUri = $request->query('redirect_uri');
        $registered = $client->getAttribute('redirect_uris');
        $redirectUri = is_string($redirectUri) && $redirectUri !== '' ? $redirectUri : Scalar::string(is_array($registered) ? ($registered[0] ?? null) : null);
        $host = parse_url($redirectUri, PHP_URL_HOST) ?: parse_url($redirectUri, PHP_URL_SCHEME);

        return [
            // Only the host, so a look-alike client name cannot hide where the authorization code goes.
            'redirectHost' => is_string($host) && $host !== '' ? $host : null,
            // laravel/mcp's dynamic registration stores no marker: it creates a public client (no secret) without an owner.
            // Every client of that shape counts as self-registered, also one made by hand with passport:client --public.
            'selfRegistered' => $client->firstParty() && ! $client->confidential(),
        ];
    }
}
