<?php

namespace App\Services\Finance;

use App\Models\Workshop;
use Illuminate\Support\Carbon;

class InvoicePdfLines
{
    public static function prepare(array $items): array
    {
        foreach ($items as &$item) {
            if (($item['kind'] ?? '') === 'travel' && isset($item['details_json']['travel']['billable_units']) && ($item['details_json']['travel']['quantity_basis'] ?? '') !== 'hours') {
                $item['quantity'] = $item['details_json']['travel']['billable_units'] / 4;
                $item['unit_price_ex_tax'] = ($item['unit_price_ex_tax'] ?? $item['unit_price'] ?? 0) * 4;
            }
        }
        unset($item);

        return self::withWorkshopDetails($items);
    }

    public static function withWorkshopDetails(iterable $items): array
    {
        $items = collect($items)->toArray();
        $linkedEntries = collect($items)
            ->reject(fn (array $item): bool => isset($item['workshop_details']))
            ->flatMap(fn (array $item): array => self::workshopEntries($item))
            ->filter(fn (array $entry): bool => trim((string) ($entry['id'] ?? '')) !== '')
            ->values();
        $workshops = $linkedEntries->isEmpty()
            ? collect()
            : Workshop::query()
                ->whereIn('id', $linkedEntries->pluck('id')->unique()->all())
                ->get()
                ->keyBy(fn (Workshop $workshop): string => (string) $workshop->getKey());

        $result = [];
        foreach ($items as $item) {
            if (! isset($item['workshop_details'])) {
                $details = self::presentableWorkshops($item, $workshops);
                if ($details !== []) {
                    $item['workshop_details'] = $details;
                }
            }
            $result[] = $item;
        }

        return $result;
    }

    public static function notesForPdf(array $item): string
    {
        $notes = trim((string) ($item['notes'] ?? ''));
        $noteLines = preg_split('/\r\n|\r|\n/', $notes) ?: [];
        $existing = array_map(fn (string $line): string => trim(preg_replace('/^[-*]\s+/', '', trim($line))), $noteLines);
        $workshopNotes = collect($item['workshop_details'] ?? [])
            ->filter(fn (array $workshop): bool => $workshop['label'] !== '' && ! in_array($workshop['label'], $existing, true))
            ->map(fn (array $workshop): string => '- '.$workshop['label'])
            ->implode("\n");

        return implode("\n", array_filter([$notes, $workshopNotes], fn (string $value): bool => $value !== ''));
    }

    private static function workshopEntries(array $item): array
    {
        $kind = (string) ($item['kind'] ?? '');
        if ($kind === 'workshop') {
            $settings = is_array($item['details_json']['workshop'] ?? null) ? $item['details_json']['workshop'] : [];

            if ($settings === [] && ! isset($item['workshop_date']) && ! isset($item['workshop_hours']) && ! isset($item['workshop_seats'])) {
                return [];
            }

            $entry = self::workshopEntry($settings, $item);

            return $entry !== [] ? [$entry] : [];
        }

        if ($kind !== 'multi_workshop') {
            return [];
        }

        $entries = [];
        foreach (($item['details_json']['multi_workshop']['rows'] ?? $item['workshops'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $settings = is_array($row['details_json']['workshop'] ?? null) ? $row['details_json']['workshop'] : [];
            $entry = self::workshopEntry($settings, $row);
            if ($entry !== []) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private static function workshopEntry(array $settings, array $source): array
    {
        $id = trim((string) ($settings['linked_workshop_id'] ?? ''));
        $description = trim((string) ($source['description'] ?? ''));
        if ($id === '' && $description === '') {
            return [];
        }

        return [
            'id' => $id,
            'description' => $description,
            'date' => $source['workshop_date'] ?? $settings['date'] ?? null,
            'hours' => $source['workshop_hours'] ?? $settings['hours'] ?? null,
            'seats' => $source['workshop_seats'] ?? $settings['seats'] ?? null,
        ];
    }

    private static function presentableWorkshops(array $item, $workshops): array
    {
        return collect(self::workshopEntries($item))
            ->map(function (array $entry) use ($workshops): array {
                $workshop = $workshops->get($entry['id']);
                $title = trim((string) (data_get($workshop, 'title') ?? $entry['description'] ?? ''));
                $date = self::formatDate($entry['date'] ?? null, $workshop);
                $hours = is_numeric($entry['hours'] ?? null) ? (float) $entry['hours'] : null;
                $seats = is_numeric($entry['seats'] ?? null) ? (float) $entry['seats'] : null;
                $details = $hours !== null && $seats !== null
                    ? '('.self::formatNumber($hours).' '.($hours === 1.0 ? 'hr' : 'hrs').' × '.self::formatNumber($seats).' seats)'
                    : '';
                $label = implode(' - ', array_filter([$date, $title, $details], fn (string $part): bool => $part !== ''));

                return ['id' => $entry['id'], 'title' => $title, 'label' => $label];
            })
            ->filter(fn (array $workshop): bool => $workshop['label'] !== '')
            ->values()
            ->all();
    }

    private static function formatDate(mixed $value, ?Workshop $workshop): string
    {
        $raw = trim((string) ($value ?? data_get($workshop, 'starts_at') ?? ''));
        if ($raw === '') {
            return '';
        }

        try {
            return Carbon::parse($raw)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
