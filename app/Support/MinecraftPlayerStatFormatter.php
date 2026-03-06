<?php

namespace App\Support;

use Illuminate\Support\Str;

class MinecraftPlayerStatFormatter
{
    private const PREFERRED_KEY_ORDER = [
        'play_time',
        'damage_dealt',
        'damage_taken',
        'deaths',
        'player_kills',
        'mob_kills',
        'fish_caught',
        'bucket_fills',
        'distance_walked_cm',
        'distance_sprinted_cm',
        'distance_swam_cm',
        'distance_flown_cm',
        'distance_fallen_cm',
        'jumps',
        'blocks_placed_survival',
        'blocks_placed_creative',
        'blocks_broken_survival',
        'blocks_broken_creative',
    ];

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function sortStatRows(array $rows): array
    {
        usort($rows, static function (array $left, array $right): int {
            $leftKey = trim((string) ($left['key'] ?? ''));
            $rightKey = trim((string) ($right['key'] ?? ''));
            $leftPriority = self::sortPriority($leftKey);
            $rightPriority = self::sortPriority($rightKey);

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            $leftTitle = trim((string) ($left['title'] ?? ''));
            $rightTitle = trim((string) ($right['title'] ?? ''));
            $titleComparison = strnatcasecmp($leftTitle, $rightTitle);

            if ($titleComparison !== 0) {
                return $titleComparison;
            }

            return strnatcasecmp($leftKey, $rightKey);
        });

        return $rows;
    }

    public static function fallbackTitle(string $key): string
    {
        return Str::headline(str_replace(['.', '-', '_'], ' ', trim($key)));
    }

    public static function formatValue(string $key, mixed $value, ?string $description = null): string
    {
        if (is_numeric($value)) {
            $numericValue = $value + 0;
            $normalizedDescription = strtolower(trim((string) $description));

            if (in_array($key, ['play_time', 'play_one_minute'], true)) {
                return self::formatPlayTime($numericValue, $description);
            }

            if (str_starts_with($key, 'time_in_')) {
                return self::formatDurationSeconds(((float) $numericValue) * 3600);
            }

            if (
                str_ends_with($key, '_km')
                || (self::looksLikeDistanceKey($key) && str_contains($normalizedDescription, 'kilometer'))
            ) {
                return self::formatDistanceKilometers($numericValue);
            }

            if (
                str_ends_with($key, '_m')
                || (self::looksLikeDistanceKey($key) && str_contains($normalizedDescription, 'meter') && ! str_contains($normalizedDescription, 'centimeter'))
            ) {
                return self::formatDistanceMeters($numericValue);
            }

            if (str_ends_with($key, '_cm') || str_ends_with($key, '_one_cm')) {
                return self::formatDistanceCentimeters($numericValue);
            }

            return self::formatNumber($numericValue);
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return trim((string) $value) !== '' ? (string) $value : '-';
    }

    public static function formatDurationSeconds(int|float $seconds): string
    {
        $seconds = max(0, (int) round((float) $seconds));
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        $parts = [];

        if ($days > 0) {
            $parts[] = $days.'d';
        }

        if ($hours > 0) {
            $parts[] = $hours.'h';
        }

        if ($minutes > 0) {
            $parts[] = $minutes.'m';
        }

        if ($parts === [] || count($parts) < 2) {
            if ($remainingSeconds > 0 || $parts === []) {
                $parts[] = $remainingSeconds.'s';
            }
        }

        return implode(' ', array_slice($parts, 0, 2));
    }

    private static function sortPriority(string $key): int
    {
        $index = array_search($key, self::PREFERRED_KEY_ORDER, true);

        return $index === false ? 1000 : $index;
    }

    private static function formatTicks(int|float $ticks): string
    {
        $seconds = max(0, (int) round(((float) $ticks) / 20));

        return self::formatDurationSeconds($seconds);
    }

    private static function formatPlayTime(int|float $value, ?string $description = null): string
    {
        $normalizedDescription = strtolower(trim((string) $description));

        if ($normalizedDescription !== '') {
            if (str_contains($normalizedDescription, 'hour')) {
                return self::formatDurationSeconds(((float) $value) * 3600);
            }

            if (str_contains($normalizedDescription, 'minute')) {
                return self::formatDurationSeconds(((float) $value) * 60);
            }

            if (str_contains($normalizedDescription, 'second')) {
                return self::formatDurationSeconds((float) $value);
            }
        }

        // Default/backward-compatible behavior: plugin value is ticks.
        return self::formatTicks($value);
    }

    private static function formatDistanceCentimeters(int|float $centimeters): string
    {
        $meters = ((float) $centimeters) / 100;

        return self::formatDistanceMeters($meters);
    }

    private static function formatDistanceKilometers(int|float $kilometers): string
    {
        $meters = ((float) $kilometers) * 1000;

        return self::formatDistanceMeters($meters);
    }

    private static function formatDistanceMeters(int|float $meters): string
    {
        $absoluteMeters = abs($meters);

        if ($absoluteMeters >= 1000) {
            return number_format($meters / 1000, 2).' km';
        }

        if ($absoluteMeters >= 100) {
            return number_format($meters, 0).' m';
        }

        if ($absoluteMeters >= 10) {
            return number_format($meters, 1).' m';
        }

        return number_format($meters, 2).' m';
    }

    private static function looksLikeDistanceKey(string $key): bool
    {
        return str_contains($key, 'distance');
    }

    private static function formatNumber(int|float $value): string
    {
        if ((float) $value === (float) round((float) $value)) {
            return number_format((int) round((float) $value));
        }

        return number_format((float) $value, 2);
    }
}
