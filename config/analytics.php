<?php

return [
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
