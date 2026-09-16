<?php

namespace App\Support;

use Illuminate\Http\Request;

class VisitorDetails
{
    public function browser(string $userAgent): string
    {
        $browser = 'Unknown browser';
        foreach ([
            'Edge' => 'Edg(?:A|iOS)?',
            'Opera' => 'OPR',
            'Samsung Internet' => 'SamsungBrowser',
            'Firefox' => '(?:Firefox|FxiOS)',
            'Chrome' => '(?:Chrome|CriOS)',
            'Safari' => 'Version',
        ] as $name => $pattern) {
            if (preg_match('~'.$pattern.'/([0-9.]+)~i', $userAgent, $matches)) {
                $browser = $name.' '.$matches[1];
                break;
            }
        }
        $platform = match (true) {
            (bool) preg_match('/iPhone|iPad|iPod/i', $userAgent) => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'CrOS') => 'ChromeOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $browser.($platform ? ' on '.$platform : '');
    }

    public function location(Request $request): ?string
    {
        // Geo headers must be supplied/overwritten by the configured trusted ingress.
        if (! $request->isFromTrustedProxy()) {
            return null;
        }
        $parts = [];
        foreach (['CF-IPCity', 'CF-Region', 'CF-IPCountry', 'CF-Timezone'] as $header) {
            $value = mb_substr(trim((string) $request->header($header)), 0, 100);
            if ($value !== '' && ! in_array($value, ['XX', 'T1'], true)) {
                $parts[] = $value;
            }
        }

        return $parts ? implode(', ', $parts) : null;
    }
}
