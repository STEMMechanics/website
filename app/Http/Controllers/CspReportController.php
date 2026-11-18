<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_if(strlen($request->getContent()) > 16384, 413);
        $body = json_decode($request->getContent(), true);
        $report = is_array($body) ? ($body['csp-report'] ?? []) : [];
        if (is_array($report)) {
            // Never retain document URLs, source URLs, referrers or script samples.
            $directive = $report['effective-directive'] ?? '';
            $blocked = $report['blocked-uri'] ?? '';
            $directive = is_string($directive) && preg_match('/\A[a-z-]{1,60}\z/', $directive) ? $directive : 'unknown';
            $kind = in_array($blocked, ['inline', 'eval', 'self'], true) ? $blocked : 'external';
            $routeName = $this->reportedRouteName($report);
            $line = $this->reportPosition($report['line-number'] ?? null);
            $column = $this->reportPosition($report['column-number'] ?? null);
            try {
                $dedupeKey = $kind === 'eval'
                    ? [$directive, $kind, 'global']
                    : [$directive, $kind, $routeName, $line ?? 'unknown'];
                if (Cache::add('csp-report:'.hash('sha256', implode('|', $dedupeKey)), true, 86400)) {
                    Log::notice('CSP report-only violation', [
                        'directive' => $directive,
                        'kind' => $kind,
                        'route' => $routeName,
                        'line' => $line,
                        'column' => $column,
                    ]);
                }
            } catch (\Throwable) {
                // Reporting must not turn cache outages into exception storms.
            }
        }

        return response('', 204);
    }

    /** @param array<string, mixed> $report */
    private function reportedRouteName(array $report): string
    {
        $documentUri = $report['document-uri'] ?? $report['documentURL'] ?? null;
        if (! is_string($documentUri) || strlen($documentUri) > 2048) {
            return 'unknown';
        }

        $path = parse_url($documentUri, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return 'unknown';
        }

        try {
            $routeName = app('router')->getRoutes()->match(Request::create($path, 'GET'))->getName();
        } catch (\Throwable) {
            return 'unknown';
        }

        return is_string($routeName) && preg_match('/\A[a-zA-Z0-9_.:-]{1,120}\z/', $routeName)
            ? $routeName
            : 'unnamed';
    }

    private function reportPosition(mixed $position): ?int
    {
        if (! is_numeric($position)) {
            return null;
        }

        $position = (int) $position;

        return $position >= 0 && $position <= 1000000 ? $position : null;
    }
}
