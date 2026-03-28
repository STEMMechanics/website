<?php

return [
    'url' => env('LIVEKIT_URL', 'wss://live.stemmechanics.com.au'),
    'api_key' => env('LIVEKIT_API_KEY'),
    'api_secret' => env('LIVEKIT_API_SECRET'),
    'token_ttl' => (int) env('LIVEKIT_TOKEN_TTL', 900),
];
