<?php

declare(strict_types=1);

use App\Actions\Auth\ResolvePostLoginRedirect;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\PasskeyAuthenticatedController;
use App\Http\Controllers\Auth\PasswordLoginController;

return [

    'auth' => [
        'post_login_redirect_resolver' => ResolvePostLoginRedirect::class,
        'passkey_form_component' => null,
        'password_form_component' => 'mortelos-starter::auth.password-form',

        'controllers' => [
            'accept_invitation' => AcceptInvitationController::class,
            'passkey_authenticated' => PasskeyAuthenticatedController::class,
            'passkey_authentication_options' => null,
            'password_login' => PasswordLoginController::class,
            'tenant_select' => null,
        ],
    ],

    'chat' => [
        'settings_service' => null,
        'conversation_panel_component' => 'chat::conversation-panel',
    ],

    'layout' => [
        'sidebar_nav_component' => null,
        'topbar_component' => 'starter::shared.topbar',
        'universal_search_component' => null,
    ],

    'navigation' => [
        'sidebar_resolver' => null,
        'universal_search_resolver' => null,
    ],

    'onboarding' => [
        'resolver' => null,
    ],

    'governance' => [
        'resolver' => null,
        'access_resolver' => null,
        'proposal_queue_component' => null,
        'stats_component' => null,
        'trust_config_component' => null,
        'learning_patterns_component' => null,
        'channel_status_component' => null,
    ],

    'users' => [
        'resolver' => null,
        'access_resolver' => null,
    ],

    'dashboard' => [
        'proud_message_resolver' => null,

        'primary_widgets' => [],

        'secondary_widgets' => [],
    ],

    'inbox' => [
        'item_type_resolver' => null,

        'intake_detail_types' => [
            'compliance_intake',
            'policy_review',
            'audit',
        ],
    ],

];
