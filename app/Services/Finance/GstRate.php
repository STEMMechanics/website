<?php

namespace App\Services\Finance;

use App\Support\RequestMemo;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GstRate
{
    // Financial records created before configurable GST used 10%, even when a rate was not saved.
    public const LEGACY = 0.10;

    public static function current(): float
    {
        return self::forDate(today());
    }

    public static function forDate(DateTimeInterface|string|null $date): float
    {
        $date = $date === null ? today()->toDateString() : Carbon::parse($date)->setTimezone(config('app.timezone'))->toDateString();
        $rate = self::LEGACY;
        foreach (self::schedule() as $entry) {
            if ($entry['effective_from'] > $date) {
                break;
            }
            $rate = $entry['rate'];
        }

        return $rate;
    }

    /** @return list<array{id: int|null, effective_from: string, rate: float, price_basis: string}> */
    public static function schedule(): array
    {
        return app(RequestMemo::class)->remember('finance:gst-rates', function (): array {
            if (! Schema::hasTable('finance_gst_rates')) {
                return [];
            }

            return DB::table('finance_gst_rates')->orderBy('effective_from')->get()
                ->map(fn ($row) => ['id' => (int) $row->id, 'effective_from' => $row->effective_from, 'rate' => (float) $row->rate, 'price_basis' => $row->price_basis])->all();
        });
    }

    public static function forLine(array|Arrayable $line, float $fallback = self::LEGACY): float
    {
        $line = $line instanceof Arrayable ? $line->toArray() : $line;
        if (! filter_var($line['gst_applicable'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return 0.0;
        }

        return (float) ($line['tax_rate'] ?? $fallback);
    }

    public static function inclusiveTax(float $amount, ?float $rate = null): float
    {
        $rate ??= self::current();

        return round($amount - $amount / (1 + $rate), 2);
    }

    public static function effectiveFrom(): string
    {
        $date = '2000-07-01';
        foreach (self::schedule() as $entry) {
            if ($entry['effective_from'] > today()->toDateString()) {
                break;
            }
            $date = $entry['effective_from'];
        }

        return $date;
    }

    public static function priceMultiplier(?string $enteredUnder): float
    {
        $enteredUnder ??= '2000-07-01';
        $previous = self::LEGACY;
        $multiplier = 1.0;
        foreach (self::schedule() as $entry) {
            if ($entry['effective_from'] > today()->toDateString()) {
                break;
            }
            if ($entry['effective_from'] > $enteredUnder && $entry['price_basis'] === 'exclusive') {
                $multiplier *= (1 + $entry['rate']) / (1 + $previous);
            }
            $previous = $entry['rate'];
        }

        return $multiplier;
    }
}
