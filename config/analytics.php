<?php

return [
    'enabled' => env('ANALYTICS_ENABLED', true),
    'ignore_path_prefixes' => [
        '/admin',
        '/up',
        '/altcha-challenge',
        '/webhooks',
    ],
    'ignore_route_prefixes' => [
        'admin.',
        'livewire.',
        'debugbar.',
    ],
    'ignore_bot_user_agents' => [
        'bot',
        'spider',
        'crawler',
        'bingpreview',
        'headless',
        'lighthouse',
        'curl/',
        'uptimerobot',
        'monitor',
    ],
];
