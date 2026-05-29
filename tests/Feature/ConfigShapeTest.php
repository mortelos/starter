<?php

declare(strict_types=1);

it('exposes the complete auth contract surface', function (): void {
    $auth = config('starter.auth');

    expect($auth)->toHaveKeys([
        'post_login_redirect_resolver',
        'passkey_form_component',
        'password_form_component',
        'controllers',
    ]);

    expect($auth['controllers'])->toHaveKeys([
        'accept_invitation',
        'passkey_authenticated',
        'passkey_authentication_options',
        'password_login',
        'tenant_select',
    ]);
});

it('exposes the optional layout, navigation, governance, users, dashboard, inbox and chat surfaces', function (string $key, array $expectedSubkeys): void {
    expect(config("starter.{$key}"))->toHaveKeys($expectedSubkeys);
})->with([
    ['layout',     ['sidebar_nav_component', 'topbar_component', 'universal_search_component']],
    ['navigation', ['sidebar_resolver', 'universal_search_resolver']],
    ['governance', ['resolver', 'access_resolver', 'proposal_queue_component', 'stats_component', 'trust_config_component', 'learning_patterns_component', 'channel_status_component']],
    ['users',      ['resolver']],
    ['onboarding', ['resolver']],
    ['dashboard',  ['proud_message_resolver', 'primary_widgets', 'secondary_widgets']],
    ['inbox',      ['item_type_resolver', 'intake_detail_types']],
    ['chat',       ['settings_service', 'conversation_panel_component']],
]);

it('defaults the password form component to the package blade', function (): void {
    expect(config('starter.auth.password_form_component'))
        ->toBe('mortelos-starter::auth.password-form');
});

it('defaults the chat conversation panel component', function (): void {
    expect(config('starter.auth.passkey_form_component'))->toBeNull();
    expect(config('starter.chat.conversation_panel_component'))->toBe('chat::conversation-panel');
});

it('lists default dashboard widgets', function (): void {
    expect(config('starter.dashboard.primary_widgets'))
        ->toContain('dashboard.ai-performance')
        ->toContain('dashboard.team-activity');

    expect(config('starter.dashboard.secondary_widgets'))
        ->toContain('dashboard.pending-proposals')
        ->toContain('dashboard.notification-list');
});
