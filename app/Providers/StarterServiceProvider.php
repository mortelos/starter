<?php

declare(strict_types=1);

namespace App\Providers;

use App\Access\StarterGovernanceGate;
use App\Actions\Invitations\RevokeInvitation;
use App\Contracts\ChangeLog\ChangeLogAccess;
use App\Contracts\ChangeLog\ChangeLogAgents;
use App\Contracts\ChangeLog\ChangeLogArchive;
use App\Contracts\GovernanceGate;
use App\Support\ChangeLog\ChangeLogDescriptions;
use App\Support\ChangeLog\ChangeLogReader;
use App\Support\ChangeLog\GenericChangeLogAgents;
use App\Support\ChangeLog\NoChangeLogArchive;
use App\Support\ChangeLog\StarterChangeDescriptions;
use App\Support\ChangeLog\ViewersSeeEverything;
use App\Support\SingleTenantResolver;
use App\Support\StarterTenantInvitationService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Mortel\Actions\ActionResult;
use Mortel\Actions\Inbox\AuthorizeInboxItemAccess;
use Mortel\Actions\Inbox\OwnerScopedInboxItemAccess;
use Mortel\Actions\UndoRegistry;
use Mortel\Contracts\TenantInvitationService;
use Mortel\Contracts\TenantResolver;
use Mortel\SocialLogin\Contracts\DetectsSignInMethods;
use Mortel\SocialLogin\Contracts\ProvisionsSocialUsers;
use Mortel\SocialLogin\Provisioning\RefuseUnknownUsers;
use Mortel\SocialLogin\Support\SignInMethods;
use Mortel\Support\Actor;
use Mortel\Support\PolicyCache;
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

        // Social login (mortelos/social-login), the strict way. Only people who already have an account sign in with
        // Google or Microsoft, and nobody is linked by e-mail address once they can sign in some other way (a
        // password counts): they log in first and link on the settings page. These are the package defaults,
        // bound here so the policy is visible and a test guards it; do not loosen it with a custom class.
        $this->app->bind(ProvisionsSocialUsers::class, RefuseUnknownUsers::class);
        $this->app->bind(DetectsSignInMethods::class, SignInMethods::class);

        // The framework denies every inbox item by default; the owner may view and decide on their own items.
        $this->app->bind(AuthorizeInboxItemAccess::class, OwnerScopedInboxItemAccess::class);

        // One policy cache per request or job instead of one per CheckPolicy: a screen that checks several rights
        // (the change log checks one per row action, the topbar one) reads `policies` once. The framework's
        // PolicyProjector invalidates this same instance when a policy changes, and a queue worker gets a fresh one
        // per job, so a revoked right is not served from an old cache.
        $this->app->scoped(PolicyCache::class);

        // Change log (docs/change-log.md): the registry of sentences per event type, built from the classes tagged
        // ChangeLogDescriptions::TAG (no shared state), and the three contract outlets with their defaults (bindIf: a
        // binding of the app wins, in whichever provider it sits).
        $this->app->bind(ChangeLogDescriptions::class, fn (Application $app): ChangeLogDescriptions => new ChangeLogDescriptions($app->tagged(ChangeLogDescriptions::TAG)));
        $this->app->tag([StarterChangeDescriptions::class], ChangeLogDescriptions::TAG);
        $this->app->scoped(ChangeLogReader::class);
        $this->app->bindIf(ChangeLogAgents::class, GenericChangeLogAgents::class);
        $this->app->bindIf(ChangeLogAccess::class, ViewersSeeEverything::class);
        $this->app->bindIf(ChangeLogArchive::class, NoChangeLogArchive::class);
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
