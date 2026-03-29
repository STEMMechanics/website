<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

class HtmlContentTransformer
{
    public static function collapseSectionsForDisplay(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previousUseInternalErrors = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="UTF-8"><div id="sm-html-content-root">'.$html.'</div>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            if (! $loaded) {
                return $html;
            }

            $xpath = new DOMXPath($document);
            /** @var \DOMNodeList<DOMElement> $sections */
            $sections = $xpath->query('//details[contains(concat(" ", normalize-space(@data-type), " "), " collapsible-section ")]');
            if ($sections === false) {
                return $html;
            }

            foreach ($sections as $section) {
                $section->removeAttribute('open');
                self::addClass($section, 'sm-collapsible-node');
                self::addClass($section, 'sm-collapsible-node--public');
            }

            $root = $document->getElementById('sm-html-content-root');
            if (! $root instanceof DOMElement) {
                return $html;
            }

            $output = '';
            foreach (iterator_to_array($root->childNodes) as $childNode) {
                $output .= $document->saveHTML($childNode);
            }

            return $output;
        } catch (Throwable) {
            return $html;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseInternalErrors);
        }
    }

    private static function addClass(DOMElement $element, string $className): void
    {
        $classes = preg_split('/\s+/', trim((string) $element->getAttribute('class')) ?: '', -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (! in_array($className, $classes, true)) {
            $classes[] = $className;
        }

        $element->setAttribute('class', trim(implode(' ', array_unique($classes))));
    }
}
