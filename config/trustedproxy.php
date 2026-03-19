<?php

$trustedProxies = env('TRUSTED_PROXIES', '*');

if (is_string($trustedProxies)) {
    $trustedProxies = trim($trustedProxies);

    if ($trustedProxies === '') {
        $trustedProxies = null;
    } elseif (str_contains($trustedProxies, ',')) {
        $trustedProxies = array_values(array_filter(array_map('trim', explode(',', $trustedProxies))));
    }
}

return [
    'proxies' => $trustedProxies,
];
