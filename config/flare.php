<?php

use Spatie\FlareClient\Enums\CollectType;
use Spatie\LaravelFlare\FlareConfig;

return [
    'key' => env('FLARE_KEY'),

    'collects' => FlareConfig::defaultCollects(
        ignore: [CollectType::StackFrameArguments],
        extra: [
            CollectType::Queries->value => [
                'include_bindings' => false,
            ],
        ],
    ),

    'censor' => [
        'body_fields' => ['*'],
        'headers' => [
            'API-KEY',
            'Authorization',
            'Cookie',
            'Set-Cookie',
            'X-CSRF-TOKEN',
            'X-XSRF-TOKEN',
        ],
        'client_ips' => true,
        'cookies' => true,
        'session' => true,
    ],

    'report' => env('FLARE_REPORT', true),
    'enable_share_button' => false,
    'trace' => env('FLARE_TRACE', false),
    'log' => env('FLARE_LOG', false),
];
