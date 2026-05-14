<?php

declare(strict_types=1);

use App\Support\TailwindMerge;

if (! function_exists('twMerge')) {
    function twMerge(...$args): string
    {
        return app(TailwindMerge::class)->merge(...$args);
    }
}

if (! function_exists('money')) {
    function money(float|int|string|null $amount, string $symbol = '$', int $precision = 2): string
    {
        $numeric = (float) ($amount ?? 0);
        $formatted = number_format(abs($numeric), $precision);

        return ($numeric < 0 ? '-' : '').$symbol.$formatted;
    }
}

if (! function_exists('inlineSvgAsset')) {
    function inlineSvgAsset(string $path, string $class = ''): string
    {
        $absolutePath = public_path($path);
        if (! is_file($absolutePath)) {
            return '';
        }

        $svgContent = file_get_contents($absolutePath);
        if ($svgContent === false || trim($svgContent) === '') {
            return '';
        }

        // Strip declarations so inlined SVGs remain safe in Blade-compiled views.
        $svgContent = preg_replace('/^\s*<\?xml[^>]*\?>\s*/i', '', $svgContent) ?? $svgContent;
        $svgContent = preg_replace('/^\s*<!DOCTYPE[^>]*>\s*/i', '', $svgContent) ?? $svgContent;

        if ($class !== '') {
            $escapedClass = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');

            if (preg_match('/<svg\b[^>]*\bclass="/i', $svgContent) === 1) {
                $svgContent = preg_replace(
                    '/(<svg\b[^>]*\bclass=")([^"]*)(")/i',
                    '$1$2 '.$escapedClass.'$3',
                    $svgContent,
                    1
                ) ?? $svgContent;
            } else {
                $svgContent = preg_replace('/<svg\b/i', '<svg class="'.$escapedClass.'"', $svgContent, 1) ?? $svgContent;
            }
        }

        return $svgContent;
    }
}

if (! function_exists('formatPhoneNumber')) {
    function formatPhoneNumber(?string $phone): string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return '';
        }

        $normalized = preg_replace('/[^\d+]/', '', $phone) ?? $phone;

        if (str_starts_with($normalized, '+')) {
            if (preg_match('/^\+(\d{2})(\d)(\d{4})(\d{4})$/', $normalized, $matches) === 1) {
                return sprintf('+%s %s %s %s', $matches[1], $matches[2], $matches[3], $matches[4]);
            }

            return $phone;
        }

        if (str_starts_with($normalized, '04')) {
            if (preg_match('/^(04\d{2})(\d{3})(\d{3})$/', $normalized, $matches) === 1) {
                return sprintf('%s %s %s', $matches[1], $matches[2], $matches[3]);
            }

            return $phone;
        }

        if (preg_match('/^(\d{2})(\d{4})(\d{4})$/', $normalized, $matches) === 1) {
            return sprintf('%s %s %s', $matches[1], $matches[2], $matches[3]);
        }

        return $phone;
    }
}
