<?php

declare(strict_types=1);

return [

    'auth' => [
        'post_login_redirect_resolver' => null,
        'passkey_form_component' => null,
        'password_form_component' => 'mortelos-starter::auth.password-form',

        'controllers' => [
            'accept_invitation' => null,
            'passkey_authenticated' => null,
            'passkey_authentication_options' => null,
            'password_login' => null,
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
        'stats_component' => null,
        'trust_config_component' => null,
        'learning_patterns_component' => null,
        'channel_status_component' => null,
    ],

    'users' => [
        'resolver' => null,
    ],

    'dashboard' => [
        'proud_message_resolver' => null,

        'primary_widgets' => [
            'dashboard.ai-performance',
            'dashboard.team-activity',
            'dashboard.overdue-items',
            'dashboard.roi-overview',
        ],

        'secondary_widgets' => [
            'dashboard.pending-proposals',
            'dashboard.notification-list',
            'dashboard.recent-activity',
            'dashboard.deadline-list',
        ],
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
