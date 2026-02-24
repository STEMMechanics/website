<?php

namespace App\Support;

class EmailMessageFormatter
{
    public static function normalizeForMarkdown(string $message): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $message);
        $normalized = trim($normalized);

        if ($normalized === '') {
            return '';
        }

        // Markdown treats single newlines as soft-wrapped text.
        // Convert single newlines to hard breaks while preserving blank lines.
        return (string) preg_replace('/(?<!\n)\n(?!\n)/', "  \n", $normalized);
    }
}
