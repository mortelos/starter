<?php

declare(strict_types=1);

/**
 * Host config template published by mortelos/starter.
 *
 * This is the recommended shape: start from the package defaults via
 * array_replace_recursive, then fill the required auth contracts and any
 * optional resolvers your portal needs.
 *
 * Required to boot:
 *   - auth.post_login_redirect_resolver
 *   - auth.controllers.password_login
 *   - auth.controllers.passkey_authenticated
 *   - auth.controllers.accept_invitation
 *   - auth.controllers.tenant_select
 *
 * Everything else is optional and degrades silently. Add a resolver or
 * component when the capability map calls for it.
 */

use App\Actions\Auth\ResolvePostLoginRedirect;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\PasskeyAuthenticatedController;
use App\Http\Controllers\Auth\PasswordLoginController;
use App\Http\Controllers\Auth\TenantSelectController;

$defaults = require __DIR__.'/../vendor/mortelos/starter/config/starter.php';

return array_replace_recursive($defaults, [

    'auth' => [
        'post_login_redirect_resolver' => ResolvePostLoginRedirect::class,

        'controllers' => [
            'accept_invitation'     => AcceptInvitationController::class,
            'passkey_authenticated' => PasskeyAuthenticatedController::class,
            'password_login'        => PasswordLoginController::class,
            'tenant_select'         => TenantSelectController::class,
        ],
    ],

    // 'layout' => [
    //     'sidebar_nav_component'      => 'shared.sidebar-nav',
    //     'universal_search_component' => 'starter::shared.universal-search',
    // ],

    // 'navigation' => [
    //     'sidebar_resolver'          => App\Support\StarterSidebarNavigationResolver::class,
    //     'universal_search_resolver' => App\Support\StarterUniversalSearchResolver::class,
    // ],

    // 'governance' => [
    //     'resolver'        => App\Support\StarterGovernanceResolver::class,
    //     'access_resolver' => App\Support\StarterGovernanceAccessResolver::class,
    // ],

    // 'users' => [
    //     'resolver' => App\Support\StarterUsersResolver::class,
    // ],

    // 'onboarding' => [
    //     'resolver' => App\Support\StarterOnboardingResolver::class,
    // ],

    // 'dashboard' => [
    //     'proud_message_resolver' => App\Support\StarterDashboardProudMessageResolver::class,
    // ],

    // 'inbox' => [
    //     'item_type_resolver' => App\Support\StarterInboxItemTypeResolver::class,
    // ],

    // 'chat' => [
    //     'settings_service' => App\Support\Chat\TenantChatSettings::class,
    // ],

]);
