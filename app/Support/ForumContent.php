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

    public static function emailPreviewText(string $html): string
    {
        $normalized = static::normalize($html);
        $normalized = preg_replace('/<\s*br\s*\/?>/iu', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('/<\s*li\b[^>]*>/iu', '- ', $normalized) ?? $normalized;
        $normalized = preg_replace('/<\s*\/\s*(p|div|li|ul|ol|blockquote)\s*>/iu', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('/<\s*(p|div|ul|ol|blockquote)\b[^>]*>/iu', '', $normalized) ?? $normalized;

        $decoded = html_entity_decode(strip_tags($normalized), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace("\r\n", "\n", $decoded);
        $decoded = str_replace("\r", "\n", $decoded);

        $lines = array_map(
            static fn (string $line): string => trim((string) preg_replace('/[ \t]+/u', ' ', $line)),
            explode("\n", $decoded)
        );

        $result = [];
        $previousBlank = false;

        foreach ($lines as $line) {
            if ($line === '') {
                if ($previousBlank || $result === []) {
                    continue;
                }

                $result[] = '';
                $previousBlank = true;

                continue;
            }

            $result[] = $line;
            $previousBlank = false;
        }

        while ($result !== [] && end($result) === '') {
            array_pop($result);
        }

        $result = array_values(array_filter(
            $result,
            static function (string $line, int $index) use ($result): bool {
                if ($line !== '') {
                    return true;
                }

                $previous = $result[$index - 1] ?? null;
                $next = $result[$index + 1] ?? null;

                return ! self::isBulletLine($previous);
            },
            ARRAY_FILTER_USE_BOTH
        ));

        return implode("\n", $result);
    }

    private static function isBulletLine(?string $line): bool
    {
        if ($line === null) {
            return false;
        }

        return str_starts_with(trim($line), '- ');
    }

    public static function renderTitleMarkdown(string $value, bool $allowStrikethrough = true): string
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

        if (! $allowStrikethrough) {
            $html = preg_replace('/<del\b[^>]*>.*?<\/del>/is', '', $html) ?? $html;
        }

        $allowedTags = $allowStrikethrough ? '<strong><em><del>' : '<strong><em>';

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($html, $allowedTags)));
    }

    public static function plainTitleMarkdown(string $value): string
    {
        return static::plainText(static::renderTitleMarkdown($value));
    }
}
