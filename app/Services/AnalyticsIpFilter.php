<?php

namespace App\Services;

use App\Models\SiteOption;

class AnalyticsIpFilter
{
    public const OPTION = 'analytics.ignored-ips';

    public function ignores(?string $ip): bool
    {
        $ip = $this->normalise($ip);

        return $ip !== null && in_array($ip, $this->ignoredIps(), true);
    }

    /**
     * @return list<string>
     */
    public function ignoredIps(): array
    {
        $value = SiteOption::value(self::OPTION, SiteOption::defaultValue(self::OPTION));
        $tokens = preg_split('/[\s,]+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ips = [];

        foreach ($tokens as $token) {
            $ip = $this->normalise($token);
            if ($ip !== null) {
                $ips[$ip] = true;
            }
        }

        return array_keys($ips);
    }

    private function normalise(?string $ip): ?string
    {
        $ip = trim((string) $ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $packed = inet_pton($ip);

        return $packed === false ? null : inet_ntop($packed);
    }
}
