<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'square' => [
        'enabled' => env('SQUARE_ENABLED', false),
        'environment' => env('SQUARE_ENVIRONMENT', 'sandbox'),
        'access_token' => env('SQUARE_ACCESS_TOKEN'),
        'application_id' => env('SQUARE_APPLICATION_ID'),
        'location_id' => env('SQUARE_LOCATION_ID'),
        'api_version' => env('SQUARE_API_VERSION', '2025-01-23'),
        'webhook_signature_key' => env('SQUARE_WEBHOOK_SIGNATURE_KEY'),
        'webhook_url' => env('SQUARE_WEBHOOK_URL'),
    ],

    'deploy' => [
        'script_path' => env('DEPLOY_SCRIPT_PATH', '/app/scripts/deploy.sh'),
        'output_log' => env('DEPLOY_OUTPUT_LOG', '/var/tmp/stemmechanics_deploy.log'),
    ],

];
