<?php

namespace App\Services;

use Illuminate\Support\Collection;

class TrafficSourceNormalizer
{
    private const SECOND_LEVEL_SUFFIX_LABELS = [
        'ac', 'asn', 'co', 'com', 'edu', 'firm', 'gen', 'go', 'gov', 'id', 'ind', 'ltd', 'me', 'mil', 'net', 'ne', 'nom', 'or', 'org', 'plc', 'res', 'sch',
    ];

    public function normalize(string $source): string
    {
        $source = trim($source);
        if ($source === '' || strcasecmp($source, 'Direct / unknown') === 0) {
            return 'Direct / unknown';
        }

        $value = strtolower(trim($source, '.'));
        $brand = $this->brandLabel($value);

        return mb_convert_case(str_replace(['-', '_'], ' ', $brand), MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @return Collection<int, \stdClass>
     */
    public function aggregate(Collection $rows, bool $includeCampaign = false): Collection
    {
        return $rows
            ->groupBy(function (\stdClass $row) use ($includeCampaign): string {
                $parts = [
                    $this->normalize((string) $row->source),
                    (string) $row->medium,
                ];
                if ($includeCampaign) {
                    $parts[] = (string) ($row->campaign ?? '');
                }

                return implode("\0", $parts);
            })
            ->map(function (Collection $group) use ($includeCampaign): \stdClass {
                /** @var \stdClass $first */
                $first = $group->first();

                $source = new \stdClass;
                $source->source = $this->normalize((string) $first->source);
                $source->medium = (string) $first->medium;
                $source->campaign = $includeCampaign ? ($first->campaign ?: null) : null;
                $source->sessions = (int) $group->sum(fn (\stdClass $row): int => (int) $row->sessions);
                $source->source_urls = $this->sourceUrls($group->pluck('raw_host')->all());

                return $source;
            })
            ->sortByDesc(fn (\stdClass $source): int => $source->sessions)
            ->values();
    }

    /**
     * @param  array<int, mixed>  $hosts
     * @return array<int, string>
     */
    private function sourceUrls(array $hosts): array
    {
        return collect($hosts)
            ->flatMap(fn ($hosts) => explode(',', (string) $hosts))
            ->map(fn (string $host): string => strtolower(trim($host)))
            ->filter(fn (string $host): bool => preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) === 1)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function brandLabel(string $source): string
    {
        if (! str_contains($source, '.') || filter_var($source, FILTER_VALIDATE_IP)) {
            return $source;
        }

        $labels = array_values(array_filter(explode('.', $source)));
        if (count($labels) >= 4 && in_array($labels[0], ['com', 'net', 'org'], true)) {
            return $labels[1];
        }

        array_pop($labels);
        while (count($labels) > 1 && in_array(end($labels), self::SECOND_LEVEL_SUFFIX_LABELS, true)) {
            array_pop($labels);
        }

        return (string) (end($labels) ?: $source);
    }
}
