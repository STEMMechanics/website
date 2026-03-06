<?php

namespace App\Models;

use App\Support\MinecraftPlayerStatFormatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class MinecraftPlayerStat extends Model
{
    use HasFactory;

    public const PERIOD_ALL = 'all';

    public const PERIOD_WEEK = 'week';

    public const PERIOD_MONTH = 'month';

    public const PERIOD_YEAR = 'year';

    public const PERIODS = [
        self::PERIOD_ALL,
        self::PERIOD_WEEK,
        self::PERIOD_MONTH,
        self::PERIOD_YEAR,
    ];

    private const PERIOD_ALIASES = [
        'all' => self::PERIOD_ALL,
        'week' => self::PERIOD_WEEK,
        'last_week' => self::PERIOD_WEEK,
        '7d' => self::PERIOD_WEEK,
        'month' => self::PERIOD_MONTH,
        'last_month' => self::PERIOD_MONTH,
        '30d' => self::PERIOD_MONTH,
        'year' => self::PERIOD_YEAR,
        'last_year' => self::PERIOD_YEAR,
        '365d' => self::PERIOD_YEAR,
    ];

    protected $fillable = [
        'uuid',
        'username',
        'period',
        'period_days',
        'captured_at',
        'fetched_at',
        'stats',
    ];

    protected $casts = [
        'period_days' => 'integer',
        'captured_at' => 'datetime',
        'fetched_at' => 'datetime',
        'stats' => 'array',
    ];

    public function account(): HasOne
    {
        return $this->hasOne(MinecraftAccount::class, 'uuid', 'uuid');
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', self::normalizePeriod($period));
    }

    public static function normalizePeriod(?string $period): string
    {
        return self::resolvePeriod($period) ?? self::PERIOD_ALL;
    }

    public static function resolvePeriod(?string $period): ?string
    {
        $normalized = strtolower(trim((string) $period));

        if ($normalized === '') {
            return self::PERIOD_ALL;
        }

        return self::PERIOD_ALIASES[$normalized] ?? null;
    }

    public static function periodLabel(string $period): string
    {
        return match (self::normalizePeriod($period)) {
            self::PERIOD_WEEK => 'Last 7 days',
            self::PERIOD_MONTH => 'Last 30 days',
            self::PERIOD_YEAR => 'Last 365 days',
            default => 'All time',
        };
    }

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     value: int|float|string|bool|null,
     *     updated_at: string|null,
     *     formatted_value: string
     * }>
     */
    public function statRows(): array
    {
        /** @var mixed $stats */
        $stats = $this->stats;

        if (! is_array($stats)) {
            return [];
        }

        $rows = [];

        foreach ($stats as $stat) {
            if (! is_array($stat)) {
                continue;
            }

            $key = trim((string) ($stat['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $title = trim((string) ($stat['title'] ?? ''));
            $description = trim((string) ($stat['description'] ?? ''));
            $value = $this->normalizeStatValue($stat['value'] ?? null);
            $updatedAt = $this->normalizeTimestamp($stat['updated_at'] ?? null);

            $rows[] = [
                'key' => $key,
                'title' => $title !== '' ? $title : MinecraftPlayerStatFormatter::fallbackTitle($key),
                'description' => $description,
                'value' => $value,
                'updated_at' => $updatedAt?->toIso8601String(),
                'formatted_value' => MinecraftPlayerStatFormatter::formatValue($key, $value, $description),
            ];
        }

        return MinecraftPlayerStatFormatter::sortStatRows($rows);
    }

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     value: int|float|string|bool|null,
     *     updated_at: string|null,
     *     formatted_value: string
     * }>
     */
    public function featuredStatRows(int $limit = 6): array
    {
        return array_slice($this->statRows(), 0, $limit);
    }

    /**
     * @return list<array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     value: int|float|string|bool|null,
     *     updated_at: string|null,
     *     formatted_value: string
     * }>
     */
    public function additionalStatRows(int $offset = 6): array
    {
        return array_slice($this->statRows(), $offset);
    }

    private function normalizeStatValue(mixed $value): int|float|string|bool|null
    {
        if (is_numeric($value)) {
            return $value + 0;
        }

        if (is_bool($value) || is_string($value) || $value === null) {
            return $value;
        }

        return null;
    }

    private function normalizeTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone((string) config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
