<?php

namespace App\Support;

use Illuminate\Support\Str;

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

    private const TITLE_MARKDOWN_ESCAPE_MAP = [
        '[' => '&#91;',
        ']' => '&#93;',
        '(' => '&#40;',
        ')' => '&#41;',
        '!' => '&#33;',
        '`' => '&#96;',
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

    public static function renderTitleMarkdown(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $escaped = strtr($value, self::TITLE_MARKDOWN_ESCAPE_MAP);
        $html = Str::inlineMarkdown($escaped, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'commonmark' => [
                'use_underscore' => false,
            ],
        ]);

        return trim(strip_tags($html, '<strong><em><del>'));
    }

    public static function plainTitleMarkdown(string $value): string
    {
        return static::plainText(static::renderTitleMarkdown($value));
    }
}
