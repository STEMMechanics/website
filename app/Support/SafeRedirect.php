<?php

namespace App\Support;

class SafeRedirect
{
    public static function allows(mixed $url): bool
    {
        if (! is_string($url) || $url === '' || preg_match('/[\x00-\x20\\\\]/', $url)) {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        $target = parse_url($url);
        $origin = parse_url((string) config('app.url'));

        return is_array($target) && is_array($origin)
            && in_array($target['scheme'] ?? '', ['http', 'https'], true)
            && strtolower($target['host'] ?? '') === strtolower($origin['host'] ?? '')
            && ($target['scheme'] ?? '') === ($origin['scheme'] ?? '')
            && ($target['port'] ?? null) === ($origin['port'] ?? null)
            && ! isset($target['user']) && ! isset($target['pass']);
    }
}
