<?php

namespace App\Support;

final class UiLink
{
    /** Navigation links may be relative or use these non-script protocols. */
    public const PROTOCOLS = ['http', 'https', 'mailto', 'tel', 'sms'];

    public static function href(?string $value): ?string
    {
        $value = trim(html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || preg_match('/[\x00-\x1f\x7f\\\\]/', $value) === 1) {
            return null;
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $value, $matches) === 1) {
            return in_array(strtolower($matches[1]), self::PROTOCOLS, true) ? $value : null;
        }

        // A colon in a relative URL's first segment can disguise an invalid scheme.
        $firstSegment = (preg_split('~[/#?]~', $value, 2) ?: [$value])[0];

        return str_contains($firstSegment, ':') ? null : $value;
    }

    public static function rel(?string $value): string
    {
        $tokens = preg_split('/\s+/', strtolower(trim((string) $value)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_unique([...array_diff($tokens, ['opener']), 'noopener']));
    }
}
