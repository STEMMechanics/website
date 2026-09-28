<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class NewsletterNoteContent
{
    public static function hasText(array $note): bool
    {
        $text = html_entity_decode(strip_tags(self::html($note)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\s\x{00a0}]+/u', ' ', $text) ?? $text;

        return trim($text) !== '';
    }

    public static function html(array $note, bool $email = false): string
    {
        $body = (string) ($note['body'] ?? '');
        if (($note['format'] ?? 'text') !== 'html') {
            $body = collect(preg_split('/\R\s*\R/u', trim($body)))->map(fn ($paragraph) => '<p>'.nl2br(e($paragraph)).'</p>')->implode('');
        }
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$body.'</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            return self::children($document->getElementsByTagName('body')->item(0), $email);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function children(?DOMNode $parent, bool $email): string
    {
        $html = '';
        foreach ($parent->childNodes ?? [] as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $html .= e($node->textContent);

                continue;
            }
            if (! $node instanceof DOMElement || in_array(strtolower($node->tagName), ['script', 'style', 'iframe', 'object', 'svg', 'math', 'template'], true)) {
                continue;
            }
            $tag = strtolower($node->tagName);
            $content = self::children($node, $email);
            if (! in_array($tag, ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'mark', 'ul', 'ol', 'li', 'a'], true)) {
                $html .= $content;

                continue;
            }
            $attributes = '';
            if ($tag === 'a') {
                $href = self::url($node->getAttribute('href'));
                if ($href === null) {
                    $html .= $content;

                    continue;
                }
                $attributes = ' href="'.e($href).'"';
            }
            if ($email) {
                $style = match ($tag) {
                    'p' => 'margin:0 0 16px; color:#334155; font-size:16px; line-height:1.7; text-align:left;',
                    'a' => 'color:#0284c7; text-decoration:underline;',
                    'ul', 'ol' => 'margin:0 0 16px; padding-left:24px;',
                    'mark' => 'background:#fef08a;',
                    default => '',
                };
                if ($style !== '') {
                    $attributes .= ' style="'.$style.'"';
                }
            }
            $html .= $tag === 'br' ? '<br>' : '<'.$tag.$attributes.'>'.$content.'</'.$tag.'>';
        }

        return $html;
    }

    private static function url(string $href): ?string
    {
        $href = trim($href);
        if (str_starts_with($href, '/') && ! str_starts_with($href, '//') && ! str_contains($href, '\\')) {
            return url($href);
        }
        if (preg_match('/^https?:\/\//i', $href) && filter_var($href, FILTER_VALIDATE_URL)) {
            return $href;
        }
        if (str_starts_with(strtolower($href), 'mailto:') && filter_var(substr($href, 7), FILTER_VALIDATE_EMAIL)) {
            return $href;
        }

        return null;
    }
}
