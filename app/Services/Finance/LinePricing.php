<?php

namespace App\Services\Finance;

use Illuminate\Contracts\Support\Arrayable;

/** Inclusive presentation never recalculates a saved financial amount. */
class LinePricing
{
    public static function savedAmounts(array|Arrayable $item): array
    {
        $item = is_array($item) ? $item : $item->toArray();
        $net = (float) ($item['line_total_ex_tax'] ?? $item['line_total'] ?? 0);
        $rate = (float) ($item['tax_rate'] ?? (($item['gst_applicable'] ?? true) ? 0.1 : 0));
        $tax = (float) ($item['tax_amount'] ?? round($net * $rate, 2));
        $gross = (float) ($item['line_total_inc_tax'] ?? round($net + $tax, 2));

        return ['net' => $net, 'tax' => $tax, 'gross' => $gross];
    }

    public static function inclusiveUnit(array|Arrayable $item): float
    {
        $item = is_array($item) ? $item : $item->toArray();
        $quantity = (float) ($item['quantity'] ?? 0);
        $amounts = self::savedAmounts($item);
        $price = $item['details_json']['inclusive_unit_price'] ?? $item['unit_price_inc_tax'] ?? null;
        if ($price !== null && abs(round($quantity * (float) $price, 2) - $amounts['gross']) < 0.005) {
            return (float) $price;
        }
        if (abs($quantity) > 0.000001) {
            return round($amounts['gross'] / $quantity, 8);
        }

        $rate = (float) ($item['tax_rate'] ?? (($item['gst_applicable'] ?? true) ? 0.1 : 0));

        return (float) ($price ?? ((float) ($item['unit_price_ex_tax'] ?? $item['unit_price'] ?? 0) * (1 + $rate)));
    }

    public static function formatUnit(array|Arrayable $item): string
    {
        $price = self::inclusiveUnit($item);
        if (abs($price - round($price, 2)) < 0.00000001) {
            return number_format($price, 2);
        }

        return rtrim(number_format($price, 8), '0');
    }

    public static function forEditor(array $item): array
    {
        $price = self::inclusiveUnit($item);
        $item['unit_price_inc_tax'] = $price;
        $item['saved_pricing'] = self::savedAmounts($item) + [
            'quantity' => (float) ($item['quantity'] ?? 0), 'price' => $price,
            'rate' => (float) ($item['tax_rate'] ?? (($item['gst_applicable'] ?? true) ? 0.1 : 0)),
        ];

        return $item;
    }

    /** Compare submitted prices with server-owned history, never with client totals. */
    public static function unchanged(array $item, array $saved): bool
    {
        $quantity = (float) ($item['quantity'] ?? 0);
        $savedQuantity = (float) ($saved['quantity'] ?? 0);
        $rate = (float) ($item['tax_rate'] ?? (($item['gst_applicable'] ?? true) ? 0.1 : 0));
        $savedRate = (float) ($saved['tax_rate'] ?? (($saved['gst_applicable'] ?? true) ? 0.1 : 0));
        $price = $item['details_json']['inclusive_unit_price'] ?? $item['unit_price_inc_tax'] ?? null;
        // Legacy API callers can continue submitting exclusive prices.
        if ($price === null) {
            return $quantity === $savedQuantity && $rate === $savedRate
                && abs((float) ($item['unit_price_ex_tax'] ?? $item['unit_price'] ?? 0) - (float) ($saved['unit_price_ex_tax'] ?? $saved['unit_price'] ?? 0)) < 0.00000001;
        }
        // Historic travel lines are displayed in hours instead of quarter-hours.
        $factor = ($saved['kind'] ?? '') === 'travel' && isset($saved['details_json']['travel']['billable_units'])
            && ($saved['details_json']['travel']['quantity_basis'] ?? '') !== 'hours'
            && ($item['details_json']['travel']['quantity_basis'] ?? '') === 'hours' ? 4 : 1;

        if (($saved['kind'] ?? '') === 'multi_workshop' && $savedQuantity === 1.0 && $quantity > 0
            && empty($saved['details_json']['multi_workshop']['quantity_basis'])
            && ($item['details_json']['multi_workshop']['quantity_basis'] ?? '') === 'seat_hours') {
            $factor = 1 / $quantity;
        }

        return abs($quantity - $savedQuantity / $factor) < 0.00000001 && $rate === $savedRate
            && abs((float) $price - self::inclusiveUnit($saved) * $factor) < 0.0000001;
    }

    public static function sameTotals(array $items, array $saved): bool
    {
        if (count($items) !== count($saved)) {
            return false;
        }
        $fingerprint = fn ($rows) => collect($rows)->map(fn ($row) => json_encode(self::savedAmounts($row)))->sort()->values()->all();

        return $fingerprint($items) === $fingerprint($saved);
    }
}
