<?php

return [
    'admin_mfa_required' => env('ADMIN_MFA_REQUIRED', env('APP_ENV') !== 'testing'),
    'generic_login' => env('GENERIC_LOGIN', env('APP_ENV') !== 'testing'),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
    'trusted_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_HOSTS', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'))))),
    'indexable' => env('SITE_INDEXABLE', env('APP_ENV') === 'production'),
    'canonical_redirect' => env('CANONICAL_HOST_REDIRECT', env('APP_ENV') === 'production'),
    'csp_report_only' => env('CSP_REPORT_ONLY', true),
    'error_recipients' => env('SECURITY_ERROR_RECIPIENTS', ''),
    'altcha_enabled' => env('ALTCHA_ENABLED', true),
    'altcha_trust_minutes' => env('ALTCHA_TRUST_MINUTES', 5),
    'form_protection' => [
        'minimum_seconds' => max(0, (int) env('FORM_PROTECTION_MINIMUM_SECONDS', 2)),
        'rate_limit_per_minute' => max(1, (int) env('FORM_PROTECTION_RATE_LIMIT_PER_MINUTE', 5)),
    ],
];
