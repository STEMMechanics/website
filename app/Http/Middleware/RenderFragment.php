<?php

namespace App\Http\Middleware;

use Closure;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RenderFragment
{
    public function handle(Request $request, Closure $next): Response
    {
        $fragment = $request->header('X-SM-Fragment', '');
        $enabled = $request->isMethod('GET') && preg_match('/^(record|list:[a-zA-Z0-9_-]+)$/D', $fragment);
        if ($enabled) {
            $request->attributes->set('sm_fragment', $fragment);
        }
        $response = $next($request);
        $response->setVary('X-SM-Fragment', false);
        if (! $enabled || ! $response->isSuccessful() || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NONET);
            $xpath = new DOMXPath($document);
            $expression = $fragment === 'record' ? '//*[@data-record-form]' : '//*[@data-dynamic-list="'.substr($fragment, 5).'"]';
            $node = $xpath->query($expression)?->item(0);
            if ($node === null) {
                // The browser falls back to a normal navigation for incompatible pages.
                return response('', 409)->withHeaders(['Vary' => 'X-SM-Fragment']);
            }
            $response->setContent($document->saveHTML($node));
            $response->headers->set('X-SM-Fragment', $fragment);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $response;
    }
}
