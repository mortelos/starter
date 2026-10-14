<?php

declare(strict_types=1);

namespace App\Providers;

use App\Access\StarterGovernanceGate;
use App\Actions\Invitations\RevokeInvitation;
use App\Contracts\GovernanceGate;
use App\Support\SingleTenantResolver;
use App\Support\StarterTenantInvitationService;
use Illuminate\Support\ServiceProvider;
use Mortel\Actions\ActionResult;
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

        $this->app->scoped(TenantResolver::class, SingleTenantResolver::class);

        $this->app->bind(TenantInvitationService::class, StarterTenantInvitationService::class);
    }

    public function boot(): void
    {
        // Counter-Actions: an Undo in an ActionResult names one of these.
        app(UndoRegistry::class)->register(
            RevokeInvitation::class,
            fn (array $params, Actor $actor): ActionResult => app(RevokeInvitation::class)->handle(Scalar::string($params['invitation_id'] ?? null), $actor),
        );
    }
}
