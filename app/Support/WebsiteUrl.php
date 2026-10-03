<?php

namespace App\Support;

final class WebsiteUrl
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $value, $matches) === 1) {
            return strtolower($matches[0]).substr($value, strlen($matches[0]));
        }

        return 'https://'.ltrim($value, '/');
    }

    public static function validationRules(): array
    {
        return ['nullable', 'url', 'starts_with:http://,https://', 'max:2048'];
    }
}
