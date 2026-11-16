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
        if (($item['kind'] ?? '') === 'multi_travel') {
            return self::normalizeMultipleTravel($item);
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
            'seats' => 'required|integer|min:0|max:10000',
            'venue_supplied' => 'required|boolean',
            'supplied_categories' => 'array|max:100',
            'supplied_categories.*' => 'boolean',
        ])->validate();
        $date = $item['workshop_date'] ?? $details['workshop']['date'] ?? null;
        Validator::make(['date' => $date], ['date' => 'nullable|date_format:Y-m-d'])->validate();
        $details['workshop'] = ['date' => $date ?: null, 'hours' => round((float) $data['hours'], 2), 'seats' => (int) $data['seats'], 'venue_supplied' => (bool) $data['venue_supplied'], 'supplied_categories' => array_map(fn ($value) => (bool) $value, $data['supplied_categories'])];
        if (array_key_exists('name', $item['details_json']['workshop'] ?? [])) {
            $name = $item['details_json']['workshop']['name'];
            Validator::make(['name' => $name], ['name' => 'nullable|string|max:500'])->validate();
            $details['workshop']['name'] = trim((string) $name);
        }
        $details['workshop'] += WorkshopFunding::settings(array_merge($item['details_json']['workshop'] ?? [], $item, ['seats' => $data['seats']]));
        if (($details['workshop']['allocation_basis'] ?? null) === 'manual') $details['workshop']['allocation_seats'] = (int) $data['seats'];
        $item['details_json'] = $details;
        $item['quantity'] = round($details['workshop']['hours'] * $data['seats'], 2);

        return $item;
    }

    private static function normalizeMultiple(array $item): array
    {
        $details = is_array($item['details_json'] ?? null) ? $item['details_json'] : [];
        $rows = $item['workshops'] ?? $details['multi_workshop']['rows'] ?? [];
        if (is_array($rows)) {
            $rows = array_values(array_filter($rows, fn ($row): bool => ! is_array($row) || self::isSpacingRow($row) || self::hasWorkshopData($row)));
            $rows = array_map(fn ($row) => is_array($row) && self::isSpacingRow($row)
                ? array_merge($row, ['row_type' => 'spacing', 'description' => '- (space)'])
                : $row, $rows);
        }
        if ($rows === []) {
            $details['multi_workshop'] = ['rows' => [], 'quantity_basis' => 'seat_hours'];
            $item['details_json'] = $details;
            $item['quantity'] = 0;
            if (! array_key_exists('notes', $item)) {
                $item['notes'] = '';
            }

            return $item;
        }
        Validator::make(['rows' => $rows], [
            'rows' => 'required|array|min:1|max:100',
            'rows.*' => 'required|array',
            'rows.*.description' => 'required|string|max:500',
            'rows.*.row_type' => 'nullable|in:workshop,spacing,heading',
            'rows.*.spacer_after' => 'sometimes|boolean',
        ])->validate();
        $notes = [];
        $normalized = [];
        foreach ($rows as $row) {
            if (self::isSpacingRow($row)) {
                if ($notes !== [] && end($notes) !== '') {
                    $notes[] = '';
                }
                $normalized[] = ['row_type' => 'spacing', 'description' => '- (space)'];

                continue;
            }
            Validator::make(['row' => $row], [
                'row.description' => 'required|string|max:500',
                'row.workshop_hours' => 'required|numeric|min:0.01|max:24',
                'row.workshop_seats' => 'required|integer|min:0|max:10000',
                'row.spacer_after' => 'sometimes|boolean',
            ])->validate();
            $row['kind'] = 'workshop';
            $row = self::normalize($row);
            $workshop = $row['details_json']['workshop'];
            $normalized[] = [
                'details_json' => ['workshop' => WorkshopFunding::settings($workshop)],
                'description' => trim($row['description']),
                'workshop_date' => $workshop['date'],
                'workshop_hours' => $workshop['hours'],
                'workshop_seats' => $workshop['seats'],
                'venue_supplied' => $workshop['venue_supplied'],
                'supplied_categories' => $workshop['supplied_categories'],
                'spacer_after' => (bool) ($row['spacer_after'] ?? false),
            ];
            $date = $workshop['date'] ? \Illuminate\Support\Carbon::parse($workshop['date'])->format('d/m/Y').' - ' : '';
            $notes[] = '- '.$date.trim($row['description']).' - ('.$workshop['hours'].((float) $workshop['hours'] === 1.0 ? ' hr' : ' hrs').' × '.$workshop['seats'].' seats)';
            if ($row['spacer_after'] ?? false) {
                $notes[] = '';
            }
        }
        $quantity = round(array_sum(array_map(fn ($row) => (float) ($row['workshop_hours'] ?? 0) * (int) ($row['workshop_seats'] ?? 0), $normalized)), 2);
        // Preserve the amount of previously saved one-group lines when adopting seat-hours.
        if ($quantity > 0 && (float) ($item['quantity'] ?? 0) === 1.0 && empty($details['multi_workshop']['quantity_basis'])) {
            if (!isset($details['inclusive_unit_price'])) {
                $rate = GstRate::forLine($item);
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
        if (! array_key_exists('notes', $item)) {
            $item['notes'] = implode("\n", $notes);
        }
        return $item;
    }

    private static function normalizeMultipleTravel(array $item): array
    {
        $details = is_array($item['details_json'] ?? null) ? $item['details_json'] : [];
        $rows = $item['travel_rows'] ?? $details['multi_travel']['rows'] ?? [];
        if (is_array($rows)) {
            $rows = array_values(array_filter($rows, fn ($row): bool => ! is_array($row) || self::isSpacingRow($row) || self::hasTravelData($row)));
            $rows = array_map(fn ($row) => is_array($row) && self::isSpacingRow($row)
                ? array_merge($row, ['row_type' => 'spacing', 'description' => '- (space)'])
                : $row, $rows);
        }
        if ($rows === []) {
            $details['multi_travel'] = ['rows' => [], 'quantity_basis' => 'hours'];
            $item['details_json'] = $details;
            $item['quantity'] = 0;
            if (! array_key_exists('notes', $item)) {
                $item['notes'] = '';
            }

            return $item;
        }
        Validator::make(['rows' => $rows], [
            'rows' => 'required|array|min:1|max:100',
            'rows.*' => 'required|array',
        ])->validate();

        $notes = [];
        $normalized = [];
        foreach ($rows as $row) {
            if (self::isSpacingRow($row)) {
                if ($notes !== [] && end($notes) !== '') {
                    $notes[] = '';
                }
                $normalized[] = ['row_type' => 'spacing', 'description' => '- (space)'];

                continue;
            }
            Validator::make(['row' => $row], [
                'row.description' => 'required|string|max:500',
                'row.travel_date' => 'nullable|date_format:Y-m-d',
                'row.travel_hours' => 'required|numeric|min:0|max:2500|multiple_of:0.25',
                'row.supplied_categories' => 'array|max:100',
                'row.supplied_categories.*' => 'boolean',
            ])->validate();
            $rowDetails = is_array($row['details_json']['travel'] ?? null) ? $row['details_json']['travel'] : [];
            $date = $row['travel_date'] ?? $row['workshop_date'] ?? $rowDetails['date'] ?? null;
            Validator::make(['date' => $date], ['date' => 'nullable|date_format:Y-m-d'])->validate();
            $hours = round((float) $row['travel_hours'], 2);
            $suppliedCategories = array_map(
                fn ($value): bool => (bool) $value,
                $row['supplied_categories'] ?? $rowDetails['supplied_categories'] ?? []
            );
            $normalized[] = [
                'details_json' => ['travel' => [
                    'billable_units' => (int) round($hours * 4),
                    'quantity_basis' => 'hours',
                    'supplied_categories' => $suppliedCategories,
                ]],
                'description' => trim((string) $row['description']),
                'travel_date' => $date ?: null,
                'travel_hours' => $hours,
                'supplied_categories' => $suppliedCategories,
            ];
            $dateLabel = $date ? \Illuminate\Support\Carbon::parse($date)->format('d/m/Y').' - ' : '';
            $hoursLabel = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
            $notes[] = '- '.$dateLabel.trim((string) $row['description']).' - '.$hoursLabel.($hours === 1.0 ? ' hr' : ' hrs');
        }

        $quantity = round(array_sum(array_map(fn (array $row): float => (float) ($row['travel_hours'] ?? 0), $normalized)), 2);
        $details['multi_travel'] = ['rows' => $normalized, 'quantity_basis' => 'hours'];
        $item['details_json'] = $details;
        $item['quantity'] = $quantity;
        if (! array_key_exists('notes', $item)) {
            $item['notes'] = implode("\n", $notes);
        }

        return $item;
    }

    /** Expand explicit groups for cost calculations, never for presentation or billing. */
    public static function allocationLines(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if (($item['kind'] ?? '') === 'multi_travel') {
                foreach ($item['details_json']['multi_travel']['rows'] ?? [] as $row) {
                    if (self::isSpacingRow($row) || ($row['travel_hours'] ?? '') === '') {
                        continue;
                    }
                    $result[] = self::normalize(array_merge($row, [
                        'kind' => 'travel',
                        'tax_rate' => GstRate::forLine($item),
                        'line_number' => $item['line_number'] ?? null,
                    ]));
                }

                continue;
            }
            if (($item['kind'] ?? '') !== 'multi_workshop') {
                $result[] = $item;
                continue;
            }
            foreach ($item['details_json']['multi_workshop']['rows'] ?? [] as $row) {
                if (self::isSpacingRow($row)) {
                    continue;
                }
                if (isset($item['details_json']['pricing_basis'])) $row['details_json']['pricing_basis'] = $item['details_json']['pricing_basis'];
                $result[] = self::normalize(array_merge($row, ['kind' => 'workshop', 'tax_rate' => GstRate::forLine($item), 'line_number' => $item['line_number'] ?? null]));
            }
        }
        return $result;
    }

    private static function isSpacingRow(array $row): bool
    {
        $description = strtolower((string) preg_replace('/\s+/', ' ', trim((string) ($row['description'] ?? ''))));

        return ($row['row_type'] ?? '') === 'heading'
            || (($row['row_type'] ?? '') === 'spacing' && ($description === '' || in_array($description, ['-', 'space', 'spacing', '- spacing', '- (space)'], true)))
            || in_array($description, ['-', 'space', 'spacing', '- spacing', '- (space)'], true);
    }

    private static function hasWorkshopData(array $row): bool
    {
        return trim((string) ($row['description'] ?? '')) !== ''
            || trim((string) ($row['workshop_date'] ?? '')) !== ''
            || ($row['workshop_hours'] ?? '') !== ''
            || ($row['workshop_seats'] ?? '') !== '';
    }

    private static function hasTravelData(array $row): bool
    {
        return trim((string) ($row['description'] ?? '')) !== ''
            || trim((string) ($row['travel_date'] ?? $row['workshop_date'] ?? '')) !== ''
            || ($row['travel_hours'] ?? '') !== '';
    }

    public static function amounts(array $item, float $quantity, float $unitPrice, float $taxRate): array
    {
        $net = round($quantity * $unitPrice, 2);
        $tax = round($net * $taxRate, 2);
        $inclusive = $item['details_json']['inclusive_unit_price'] ?? null;
        if ($inclusive !== null) {
            Validator::make(['price' => $inclusive], ['price' => 'numeric|between:-100000,100000'])->validate();
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
