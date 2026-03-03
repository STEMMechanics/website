<?php

namespace App\Support;

class ForumContent
{
    private const MEANINGFUL_NON_TEXT_TAGS = [
        'img',
        'figure',
        'table',
        'iframe',
        'video',
        'audio',
        'embed',
        'object',
        'svg',
    ];

    public static function normalize(string $html): string
    {
        $normalized = trim($html);
        $normalized = preg_replace('/<\s*(\/?)h[1-6]\b/i', '<$1p', $normalized) ?? $normalized;

        return $normalized;
    }

    public static function hasMeaningfulContent(string $html): bool
    {
        if (static::plainText($html) !== '') {
            return true;
        }

        $pattern = '/<\s*('.implode('|', self::MEANINGFUL_NON_TEXT_TAGS).')\b/i';

        return preg_match($pattern, $html) === 1;
    }

    public static function plainText(string $html): string
    {
        $decoded = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $decoded));
    }
}
