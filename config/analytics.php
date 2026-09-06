<?php

return [
    'queue_connection' => env('ANALYTICS_QUEUE_CONNECTION'),
    'dashboard_snapshot_seconds' => (int) env('DASHBOARD_SNAPSHOT_SECONDS', env('APP_ENV') === 'testing' ? 0 : 300),
    'profile_requests' => env('PROFILE_REQUESTS', false),
    'enabled' => env('ANALYTICS_ENABLED', true),
    'internal_referrer_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('ANALYTICS_INTERNAL_REFERRER_HOSTS', 'stemmechanics.com.au,stemmechanics.com,stemmechanics.net'))
    ))),
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
