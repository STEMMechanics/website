<?php

return [
    'altcha_enabled' => env('ALTCHA_ENABLED', true),
    'altcha_trust_minutes' => env('ALTCHA_TRUST_MINUTES', 5),
    'form_protection' => [
        'minimum_seconds' => max(0, (int) env('FORM_PROTECTION_MINIMUM_SECONDS', 2)),
        'rate_limit_per_minute' => max(1, (int) env('FORM_PROTECTION_RATE_LIMIT_PER_MINUTE', 5)),
    ],
];
