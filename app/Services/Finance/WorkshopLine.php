<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\Validator;

class WorkshopLine
{
    public static function normalize(array $item): array
    {
        if (($item['kind'] ?? '') === 'multi_workshop') {
            return self::normalizeMultiple($item);
        }
        if (($item['kind'] ?? '') === 'travel' && isset($item['travel_hours']) && $item['travel_hours'] !== '') {
            $data = Validator::make($item, ['travel_hours' => 'required|numeric|min:0|max:2500|multiple_of:0.25', 'supplied_categories' => 'sometimes|array', 'supplied_categories.*' => 'boolean'])->validate();
            $item['details_json'] = is_array($item['details_json'] ?? null) ? $item['details_json'] : [];
            $item['quantity'] = (float) $data['travel_hours'];
            $item['details_json']['travel'] = ['billable_units' => (int) round($item['quantity'] * 4), 'quantity_basis' => 'hours', 'supplied_categories' => $data['supplied_categories'] ?? $item['details_json']['travel']['supplied_categories'] ?? []];
            return $item;
        }
        if (($item['kind'] ?? '') === 'travel' && isset($item['travel_units']) && $item['travel_units'] !== '') {
            $data = Validator::make(['units' => $item['travel_units'], 'supplied_categories' => $item['supplied_categories'] ?? $item['details_json']['travel']['supplied_categories'] ?? []], ['units' => 'required|integer|min:0|max:10000', 'supplied_categories' => 'array|max:100', 'supplied_categories.*' => 'boolean'])->validate();
            $item['details_json'] = is_array($item['details_json'] ?? null) ? $item['details_json'] : [];
            $item['details_json']['travel'] = ['billable_units' => (int) $data['units'], 'supplied_categories' => array_map(fn ($value) => (bool) $value, $data['supplied_categories'])];
            $item['quantity'] = (int) $data['units'];
        }
        if (($item['kind'] ?? '') !== 'workshop') {
            return $item;
        }
        $details = is_array($item['details_json'] ?? null) ? $item['details_json'] : [];
        $hours = $item['workshop_hours'] ?? $details['workshop']['hours'] ?? null;
        $seats = $item['workshop_seats'] ?? $details['workshop']['seats'] ?? null;
        // Historical combined lines retain their original quantity until explicitly described.
        if (($hours === null || $hours === '') && ($seats === null || $seats === '')) {
            if (! empty($details['workshop'])) {
                $hours = $details['workshop']['hours'] ?? null;
                $seats = $details['workshop']['seats'] ?? null;
            } else {
                return $item;
            }
        }
        $data = Validator::make(['hours' => $hours, 'seats' => $seats, 'supplied_categories' => $item['supplied_categories'] ?? $details['workshop']['supplied_categories'] ?? [], 'venue_supplied' => $item['venue_supplied'] ?? $details['workshop']['venue_supplied'] ?? true], [
            'hours' => 'required|numeric|min:0.01|max:24',
            'seats' => 'required|integer|min:1|max:10000',
            'venue_supplied' => 'required|boolean',
            'supplied_categories' => 'array|max:100',
            'supplied_categories.*' => 'boolean',
        ])->validate();
        $date = $item['workshop_date'] ?? $details['workshop']['date'] ?? null;
        Validator::make(['date' => $date], ['date' => 'nullable|date_format:Y-m-d'])->validate();
        $details['workshop'] = ['date' => $date ?: null, 'hours' => round((float) $data['hours'], 2), 'seats' => (int) $data['seats'], 'venue_supplied' => (bool) $data['venue_supplied'], 'supplied_categories' => array_map(fn ($value) => (bool) $value, $data['supplied_categories'])];
        $item['details_json'] = $details;
        $item['quantity'] = round($details['workshop']['hours'] * $data['seats'], 2);

        return $item;
    }

    private static function normalizeMultiple(array $item): array
    {
        $details = is_array($item['details_json'] ?? null) ? $item['details_json'] : [];
        $rows = $item['workshops'] ?? $details['multi_workshop']['rows'] ?? [];
        Validator::make(['rows' => $rows], [
            'rows' => 'required|array|min:1|max:100',
            'rows.*' => 'required|array',
            'rows.*.description' => 'required|string|max:500',
            'rows.*.workshop_hours' => 'required|numeric|min:0.01|max:24',
            'rows.*.workshop_seats' => 'required|integer|min:1|max:10000',
        ])->validate();
        $notes = [];
        $normalized = [];
        foreach ($rows as $row) {
            $row['kind'] = 'workshop';
            $row = self::normalize($row);
            $workshop = $row['details_json']['workshop'];
            $normalized[] = [
                'description' => trim($row['description']),
                'workshop_date' => $workshop['date'],
                'workshop_hours' => $workshop['hours'],
                'workshop_seats' => $workshop['seats'],
                'venue_supplied' => $workshop['venue_supplied'],
                'supplied_categories' => $workshop['supplied_categories'],
            ];
            $date = $workshop['date'] ? \Illuminate\Support\Carbon::parse($workshop['date'])->format('d/m/Y').' - ' : '';
            $notes[] = '- '.$date.trim($row['description']).' - ('.$workshop['hours'].' hr / '.$workshop['seats'].' seats)';
        }
        $quantity = round(array_sum(array_map(fn ($row) => $row['workshop_hours'] * $row['workshop_seats'], $normalized)), 2);
        // Preserve the amount of previously saved one-group lines when adopting seat-hours.
        if ((float) ($item['quantity'] ?? 0) === 1.0 && empty($details['multi_workshop']['quantity_basis'])) {
            if (!isset($details['inclusive_unit_price'])) {
                $rate = (float) ($item['tax_rate'] ?? (($item['gst_applicable'] ?? true) ? 0.1 : 0));
                $net = round((float) ($item['unit_price_ex_tax'] ?? $item['unit_price'] ?? 0), 2);
                $details['inclusive_unit_price'] = round($net + round($net * $rate, 2), 2);
            }
            foreach (['unit_price', 'unit_price_ex_tax', 'unit_price_inc_tax'] as $field) {
                if (isset($item[$field])) $item[$field] = (float) $item[$field] / $quantity;
            }
            if (isset($details['inclusive_unit_price'])) $details['inclusive_unit_price'] /= $quantity;
        }
        $details['multi_workshop'] = ['rows' => $normalized, 'quantity_basis' => 'seat_hours'];
        $item['details_json'] = $details;
        $item['quantity'] = $quantity;
        $item['notes'] = implode("\n", $notes);
        return $item;
    }

    /** Expand explicit groups for cost calculations, never for presentation or billing. */
    public static function allocationLines(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (($item['kind'] ?? '') !== 'multi_workshop') {
                $result[] = $item;
                continue;
            }
            foreach ($item['details_json']['multi_workshop']['rows'] ?? [] as $row) {
                $result[] = self::normalize(array_merge($row, ['kind' => 'workshop', 'tax_rate' => $item['tax_rate'] ?? 0.1, 'line_number' => $item['line_number'] ?? null]));
            }
        }
        return $result;
    }

    public static function amounts(array $item, float $quantity, float $unitPrice, float $taxRate): array
    {
        $net = round($quantity * $unitPrice, 2);
        $tax = round($net * $taxRate, 2);
        $inclusive = $item['details_json']['inclusive_unit_price'] ?? null;
        if ($inclusive !== null) {
            Validator::make(['price' => $inclusive], ['price' => 'numeric|min:0|max:100000'])->validate();
            $gross = round($quantity * (float) $inclusive, 2);
            $net = round($gross / (1 + $taxRate), 2);
            $tax = round($gross - $net, 2);
        }
        return ['net' => $net, 'tax' => $tax, 'gross' => round($net + $tax, 2)];
    }

    public static function quantityLabel(array $item): string
    {
        $format = fn ($number) => rtrim(rtrim(number_format((float) $number, 2, '.', ''), '0'), '.');
        $workshop = $item['details_json']['workshop'] ?? null;
        if (($item['kind'] ?? '') === 'workshop' && is_array($workshop) && isset($workshop['hours'], $workshop['seats'])) {
            return $format($workshop['hours']).' × '.$format($workshop['seats']);
        }

        return $format($item['quantity'] ?? 0);
    }
}
