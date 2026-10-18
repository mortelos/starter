<?php

declare(strict_types=1);

namespace App\Providers;

use App\Access\StarterGovernanceGate;
use App\Actions\Invitations\RevokeInvitation;
use App\Contracts\GovernanceGate;
use App\Support\SingleTenantResolver;
use App\Support\StarterTenantInvitationService;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Mortel\Actions\ActionResult;
use Mortel\Actions\Inbox\AuthorizeInboxItemAccess;
use Mortel\Actions\Inbox\OwnerScopedInboxItemAccess;
use Mortel\Actions\UndoRegistry;
use Mortel\Contracts\TenantInvitationService;
use Mortel\Contracts\TenantResolver;
use Mortel\Support\Actor;
use Mortel\Support\Scalar;

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
        Passport::authorizationView('auth.oauth-authorize');

        // Counter-Actions: an Undo in an ActionResult names one of these.
        app(UndoRegistry::class)->register(
            RevokeInvitation::class,
            fn (array $params, Actor $actor): ActionResult => app(RevokeInvitation::class)->handle(Scalar::string($params['invitation_id'] ?? null), $actor),
        );
    }
}
