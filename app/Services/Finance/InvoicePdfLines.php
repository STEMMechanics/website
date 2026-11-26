<?php

namespace App\Services\Finance;

use App\Models\Location;
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
        $entries = collect($items)
            ->reject(fn (array $item): bool => isset($item['workshop_details']))
            ->flatMap(fn (array $item): array => self::workshopEntries($item))
            ->values();
        $linkedEntries = $entries
            ->filter(fn (array $entry): bool => trim((string) ($entry['id'] ?? '')) !== '')
            ->values();
        $workshops = $linkedEntries->isEmpty()
            ? collect()
            : Workshop::query()
                ->with('location')
                ->whereIn('id', $linkedEntries->pluck('id')->unique()->all())
                ->get()
                ->keyBy(fn (Workshop $workshop): string => (string) $workshop->getKey());
        $locationIds = $entries
            ->pluck('location_id')
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values();
        $locations = $locationIds->isEmpty()
            ? collect()
            : Location::query()->whereIn('id', $locationIds->all())->get()->keyBy(fn (Location $location): string => (string) $location->getKey());

        $result = [];
        foreach ($items as $item) {
            if (! isset($item['workshop_details'])) {
                $details = self::presentableWorkshops($item, $workshops, $locations);
                if ($details !== []) {
                    $item['workshop_details'] = $details;
                }
            }
            $result[] = $item;
        }

        return $result;
    }

    public static function descriptionForPdf(array $item): string
    {
        $description = (string) ($item['description'] ?? '');
        if (($item['kind'] ?? '') === 'workshop'
            && ! empty($item['workshop_details'])
            && ! array_key_exists('name', $item['details_json']['workshop'] ?? [])
            && ! str_contains($description, '{location}')) {
            $description = 'Charged per hour, per seat';
        }

        if (str_contains($description, '{location}')) {
            $locations = collect($item['workshop_details'] ?? [])
                ->pluck('location_name')
                ->map(fn ($name): string => trim((string) $name))
                ->filter()
                ->unique()
                ->implode(', ');

            if ($locations === '') {
                $description = preg_replace('/\{location\}\s*[-–,]\s*/', '', $description) ?? $description;
                $description = preg_replace('/\s*[-–,]\s*\{location\}/', '', $description) ?? $description;
            } else {
                $description = str_replace('{location}', $locations, $description);
            }
        }

        return trim($description);
    }

    public static function notesForPdf(array $item): string
    {
        if (($item['kind'] ?? '') === 'multi_travel') {
            return self::multiTravelNotes($item);
        }
        $notes = trim((string) ($item['notes'] ?? ''));
        if (in_array($item['kind'] ?? '', ['workshop', 'multi_workshop'], true)
            && (($item['details_json']['workshop_notes_mode'] ?? '') === 'template' || preg_match('/\{(?:date|workshop|duration|seats|location)\}/', $notes))) {
            return self::renderWorkshopText($notes, $item['workshop_details'] ?? []);
        }
        $noteLines = preg_split('/\r\n|\r|\n/', $notes) ?: [];
        if (($item['kind'] ?? '') === 'workshop' && ! empty($item['workshop_details'])) {
            $entry = self::workshopEntries($item)[0] ?? [];
            if (is_numeric($entry['hours'] ?? null) && is_numeric($entry['seats'] ?? null)) {
                $hours = self::formatNumber((float) $entry['hours']);
                $seats = self::formatNumber((float) $entry['seats']);
                $calculation = $hours.' '.((float) $entry['hours'] === 1.0 ? 'hr' : 'hrs').' × '.$seats.' seats';
                $redundant = [
                    $calculation,
                    $hours.' hr / '.$seats.' seats',
                    $calculation.' = '.self::formatNumber(round($entry['hours'] * $entry['seats'], 2)).' seat-hours',
                ];
                $noteLines = array_filter($noteLines, fn (string $line): bool => ! in_array(trim(preg_replace('/^[-*]\s+/', '', trim($line))), $redundant, true));
                $notes = trim(implode("\n", $noteLines));
            }
        }
        $existing = array_map(fn (string $line): string => trim(preg_replace('/^[-*]\s+/', '', trim($line))), $noteLines);
        $workshopNoteLines = [];
        foreach ($item['workshop_details'] ?? [] as $workshop) {
            if ($workshop['spacing'] ?? false) {
                if ($workshopNoteLines !== [] && end($workshopNoteLines) !== '') {
                    $workshopNoteLines[] = '';
                }

                continue;
            }
            if ($workshop['label'] === '' || in_array($workshop['label'], $existing, true)) {
                continue;
            }
            $workshopNoteLines[] = '- '.$workshop['label'];
            if ($workshop['spacer_after'] ?? false) {
                $workshopNoteLines[] = '';
            }
        }
        $workshopNotes = implode("\n", $workshopNoteLines);

        return implode("\n", array_filter([$notes, $workshopNotes], fn (string $value): bool => $value !== ''));
    }

    private static function multiTravelNotes(array $item): string
    {
        $rows = $item['details_json']['multi_travel']['rows'] ?? $item['travel_rows'] ?? [];
        $generated = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (self::isSpacingRow($row)) {
                if ($generated !== [] && end($generated) !== '') {
                    $generated[] = '';
                }

                continue;
            }
            $date = self::formatDate($row['travel_date'] ?? $row['workshop_date'] ?? data_get($row, 'details_json.travel.date'), null);
            $description = trim((string) ($row['description'] ?? ''));
            $hours = is_numeric($row['travel_hours'] ?? null) ? self::formatNumber((float) $row['travel_hours']) : '';
            if ($description === '' && $hours === '') {
                continue;
            }
            $label = implode(' - ', array_filter([$date, $description, $hours !== '' ? $hours.' '.((float) $row['travel_hours'] === 1.0 ? 'hr' : 'hrs') : ''], fn (string $part): bool => $part !== ''));
            if ($label !== '') {
                $generated[] = '- '.$label;
            }
        }

        $generated = rtrim(implode("\n", $generated));
        $notes = trim((string) ($item['notes'] ?? ''));
        $withoutSpacing = static fn (string $value): string => (string) preg_replace('~\n+~', "\n", trim($value));
        if ($notes === '' || $withoutSpacing($notes) === $withoutSpacing($generated)) {
            return $generated;
        }

        return implode("\n", array_filter([$notes, $generated], fn (string $value): bool => $value !== ''));
    }

    private static function renderWorkshopText(string $template, array $workshops): string
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $template) ?: [] as $line) {
            if (! preg_match('/\{(?:date|workshop|duration|seats|location)\}/', $line)) {
                $lines[] = $line;

                continue;
            }
            foreach ($workshops as $workshopIndex => $workshop) {
                if ($workshop['spacing'] ?? false) {
                    if ($lines !== [] && end($lines) !== '') {
                        $lines[] = '';
                    }
                    continue;
                }
                $text = $line;
                foreach (['date', 'workshop', 'location'] as $optional) {
                    if (($workshop[$optional] ?? '') === '') {
                        $text = preg_replace('/\{'.$optional.'\}\s*-\s*/', '', $text);
                    }
                }
                $lines[] = strtr($text, [
                    '{date}' => $workshop['date'], '{workshop}' => $workshop['workshop'],
                    '{duration}' => $workshop['duration'], '{seats}' => $workshop['seats'],
                    '{location}' => $workshop['location'],
                ]);
                if ($workshop['spacer_after'] ?? false) {
                    $lines[] = '';
                }
            }
        }

        return trim(implode("\n", $lines));
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
        if (self::isSpacingRow($source)) {
            return [
                'id' => '', 'description' => '- (space)', 'date' => null, 'hours' => null, 'seats' => null,
                'spacing' => true,
            ];
        }
        $id = trim((string) ($settings['linked_workshop_id'] ?? ''));
        $description = trim((string) ($settings['name'] ?? $source['description'] ?? ''));
        if (($source['kind'] ?? '') === 'workshop' && in_array(strtolower($description), ['charged per hour, per seat', 'workshop delivery'], true)) {
            $description = '';
        }
        if ($id === '' && $description === '' && ! isset($source['workshop_hours'], $source['workshop_seats']) && ! isset($settings['hours'], $settings['seats'])) {
            return [];
        }

        return [
            'id' => $id,
            'description' => $description,
            'date' => $source['workshop_date'] ?? $settings['date'] ?? null,
            'hours' => $source['workshop_hours'] ?? $settings['hours'] ?? null,
            'seats' => $source['workshop_seats'] ?? $settings['seats'] ?? null,
            'location_id' => $settings['location_id'] ?? $source['location_id'] ?? null,
            'spacer_after' => (bool) ($source['spacer_after'] ?? false),
        ];
    }

    private static function presentableWorkshops(array $item, $workshops, $locations): array
    {
        return collect(self::workshopEntries($item))
            ->map(function (array $entry) use ($workshops, $locations): array {
                if ($entry['spacing'] ?? false) {
                    return [
                        'id' => '', 'title' => '- (space)', 'label' => '',
                        'date' => '', 'workshop' => '', 'duration' => '', 'seats' => '', 'location' => '',
                        'spacing' => true, 'spacer_after' => false,
                    ];
                }
                $workshop = $workshops->get($entry['id']);
                $explicitLocation = $locations->get((string) ($entry['location_id'] ?? ''));
                $location = $explicitLocation ?? $workshop?->location;
                $locationName = trim((string) ($location->name ?? ''));
                if ($locationName === '' && $workshop instanceof Workshop) {
                    $locationName = $workshop->getLocationName();
                }
                if ($locationName === '-') {
                    $locationName = '';
                }
                $title = trim((string) ($entry['description'] !== '' ? $entry['description'] : data_get($workshop, 'title', '')));
                $date = self::formatDate($entry['date'] ?? null, $workshop);
                $hours = is_numeric($entry['hours'] ?? null) ? (float) $entry['hours'] : null;
                $seats = is_numeric($entry['seats'] ?? null) ? (float) $entry['seats'] : null;
                $details = $hours !== null && $seats !== null
                    ? '('.self::formatNumber($hours).' '.($hours === 1.0 ? 'hr' : 'hrs').' × '.self::formatNumber($seats).' seats)'
                    : '';
                $label = implode(' - ', array_filter([$date, $title, $details], fn (string $part): bool => $part !== ''));

                return [
                    'id' => $entry['id'], 'title' => $title, 'label' => $label,
                    'date' => $date, 'workshop' => $title,
                    'duration' => $hours !== null ? self::formatNumber($hours).' '.($hours === 1.0 ? 'hr' : 'hrs') : '',
                    'seats' => $seats !== null ? self::formatNumber($seats).' seats' : '',
                    'location_name' => $locationName,
                    'location' => $locationName,
                    'spacer_after' => (bool) ($entry['spacer_after'] ?? false),
                ];
            })
            ->filter(fn (array $workshop): bool => ($workshop['spacing'] ?? false) || $workshop['label'] !== '')
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

    private static function isSpacingRow(array $source): bool
    {
        $description = strtolower((string) preg_replace('/\s+/', ' ', trim((string) ($source['description'] ?? ''))));

        return ($source['row_type'] ?? '') === 'heading'
            || (($source['row_type'] ?? '') === 'spacing' && ($description === '' || in_array($description, ['-', 'space', 'spacing', '- spacing', '- (space)'], true)))
            || in_array($description, ['-', 'space', 'spacing', '- spacing', '- (space)'], true);
    }
}
