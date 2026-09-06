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
            try {
                if (Cache::add('csp-report:'.hash('sha256', $directive.$kind), true, 300)) {
                    Log::notice('CSP report-only violation', ['directive' => $directive, 'kind' => $kind]);
                }
            } catch (\Throwable) {
                // Reporting must not turn cache outages into exception storms.
            }
        }

        return response('', 204);
    }
}
