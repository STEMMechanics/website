<?php

namespace App\Services;

use App\Support\ShopShippingSettings;
use Illuminate\Support\Collection;

class StoreShippingService
{
    public function quote(Collection $lines, ?string $country = null): array
    {
        $physicalLines = $lines
            ->filter(fn ($line) => $line->product->isPhysical() && (int) $line->quantity > 0)
            ->values();

        if ($physicalLines->isEmpty()) {
            return [
                'can_checkout' => true,
                'boxed_shipping_required' => false,
                'requires_manual_quote' => false,
                'method' => 'No shipping required',
                'package_summary' => null,
                'reason' => null,
                'parcel_count' => 0,
                'parcels' => [],
                'satchel_breakdown' => [],
                'known_weight_grams' => 0,
                'amount' => 0.0,
            ];
        }

        $hasInvalidSatchelItem = $physicalLines->contains(function ($line): bool {
            $shippingUnits = round(max(0, (float) ($line->unit_shipping_units ?? $line->shipping_units ?? 0)), 2);
            $boxOnly = (bool) ($line->box_only ?? false);

            return ! $boxOnly && $shippingUnits <= 0;
        });
        if ($hasInvalidSatchelItem) {
            return $this->boxedShippingQuote('Some physical products do not have satchel shipping units configured.');
        }

        $packUnits = $this->expandLines($physicalLines);
        if ($packUnits->isEmpty()) {
            return $this->boxedShippingQuote('Some physical products do not have satchel shipping units configured.');
        }

        if ($packUnits->contains(fn ($unit) => $unit['box_only'])) {
            return $this->boxedShippingQuote('This order contains items that must ship in a box.');
        }

        $satchels = $this->satchels();
        if ($satchels->isEmpty()) {
            return $this->boxedShippingQuote('No satchel options are configured.');
        }

        $parcels = [];

        foreach ($packUnits as $unit) {
            $placed = $this->placeIntoExistingParcel($parcels, $unit, $satchels);
            if ($placed) {
                continue;
            }

            $parcel = $this->newParcelForUnit($unit, $satchels);
            if ($parcel === null) {
                return $this->boxedShippingQuote('This order cannot be packed into the configured satchels.');
            }

            $parcels[] = $parcel;
        }

        $parcelCollection = collect($parcels)->map(function (array $parcel): array {
            $satchel = $parcel['satchel'];

            return [
                'code' => (string) ($satchel['code'] ?? ''),
                'label' => (string) ($satchel['label'] ?? 'Satchel'),
                'rank' => (int) ($satchel['rank'] ?? 0),
                'capacity' => round((float) ($satchel['capacity'] ?? 0), 2),
                'price' => round((float) ($satchel['price'] ?? 0), 2),
                'used_capacity' => round((float) ($parcel['used_capacity'] ?? 0), 2),
                'remaining_capacity' => round(max(0, (float) ($satchel['capacity'] ?? 0) - (float) ($parcel['used_capacity'] ?? 0)), 2),
                'total_weight_grams' => (int) ($parcel['total_weight_grams'] ?? 0),
                'items' => array_values($parcel['items'] ?? []),
            ];
        })->values();

        $satchelBreakdown = $parcelCollection
            ->groupBy('code')
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'code' => (string) ($first['code'] ?? ''),
                    'label' => (string) ($first['label'] ?? 'Satchel'),
                    'count' => $group->count(),
                    'unit_price' => round((float) ($first['price'] ?? 0), 2),
                    'subtotal' => round((float) $group->sum('price'), 2),
                ];
            })
            ->sortBy(fn (array $item) => (int) $this->satchelRankForCode((string) $item['code'], $satchels))
            ->values();

        $packageSummary = $satchelBreakdown
            ->map(fn (array $item) => $item['count'].' x '.$item['label'].' Satchel'.($item['count'] === 1 ? '' : 's'))
            ->implode(', ');

        return [
            'can_checkout' => true,
            'boxed_shipping_required' => false,
            'requires_manual_quote' => false,
            'method' => 'Satchel shipping',
            'package_summary' => $packageSummary !== '' ? $packageSummary : null,
            'reason' => null,
            'parcel_count' => $parcelCollection->count(),
            'parcels' => $parcelCollection->all(),
            'satchel_breakdown' => $satchelBreakdown->all(),
            'known_weight_grams' => (int) $parcelCollection->sum('total_weight_grams'),
            'amount' => round((float) $parcelCollection->sum('price'), 2),
        ];
    }

    /**
     * @return Collection<int, array{code:string,label:string,rank:int,capacity:float,price:float}>
     */
    public function satchels(): Collection
    {
        return ShopShippingSettings::satchels()
            ->filter(fn (array $satchel): bool => $satchel['active'] !== false)
            ->map(function (array $satchel): array {
                return [
                    'code' => (string) $satchel['code'],
                    'label' => (string) $satchel['label'],
                    'rank' => max(1, (int) $satchel['rank']),
                    'capacity' => round(max(0.01, (float) $satchel['capacity']), 2),
                    'price' => round(max(0, (float) $satchel['price']), 2),
                ];
            })
            ->sortBy('rank')
            ->values();
    }

    private function expandLines(Collection $lines): Collection
    {
        return $lines
            ->flatMap(function ($line): array {
                $quantity = max(0, (int) $line->quantity);
                if ($quantity <= 0) {
                    return [];
                }

                $shippingUnits = round(max(0, (float) ($line->unit_shipping_units ?? $line->shipping_units ?? 0)), 2);
                $minSatchelRank = max(1, (int) ($line->unit_min_satchel_rank ?? $line->min_satchel_rank ?? 1));
                $boxOnly = (bool) ($line->box_only ?? false);

                if (! $boxOnly && $shippingUnits <= 0) {
                    return [];
                }

                return collect(range(1, $quantity))
                    ->map(fn () => [
                        'display_title' => (string) ($line->display_title ?? $line->product->title ?? 'Item'),
                        'shipping_units' => $shippingUnits,
                        'min_satchel_rank' => $minSatchelRank,
                        'weight_grams' => ($line->unit_weight_grams ?? null) !== null ? max(0, (int) $line->unit_weight_grams) : null,
                        'box_only' => $boxOnly,
                    ])
                    ->all();
            })
            ->sort(function (array $left, array $right): int {
                return [$right['min_satchel_rank'], $right['shipping_units'], $right['weight_grams'] ?? 0]
                    <=> [$left['min_satchel_rank'], $left['shipping_units'], $left['weight_grams'] ?? 0];
            })
            ->values();
    }

    private function placeIntoExistingParcel(array &$parcels, array $unit, Collection $satchels): bool
    {
        $directCandidates = collect($parcels)
            ->map(fn (array $parcel, int $index) => ['index' => $index, 'parcel' => $parcel])
            ->filter(fn (array $candidate) => $this->parcelCanFitUnit($candidate['parcel'], $unit))
            ->sortBy(fn (array $candidate) => [
                $this->parcelRemainingCapacityAfter($candidate['parcel'], $unit),
                (int) ($candidate['parcel']['satchel']['rank'] ?? 0),
                $candidate['index'],
            ])
            ->values();

        $direct = $directCandidates->first();
        if (is_array($direct)) {
            $this->appendUnitToParcel($parcels[$direct['index']], $unit);

            return true;
        }

        $upgradeCandidates = collect($parcels)
            ->map(function (array $parcel, int $index) use ($unit, $satchels): ?array {
                $upgradedSatchel = $this->smallestSatchelForUsage(
                    max((int) ($parcel['satchel']['rank'] ?? 1), (int) $unit['min_satchel_rank']),
                    (float) ($parcel['used_capacity'] ?? 0) + (float) $unit['shipping_units'],
                    $this->newKnownWeight($parcel, $unit),
                    $satchels,
                );

                if ($upgradedSatchel === null) {
                    return null;
                }

                return [
                    'index' => $index,
                    'satchel' => $upgradedSatchel,
                ];
            })
            ->filter()
            ->sortBy(fn (array $candidate) => [
                (int) ($candidate['satchel']['rank'] ?? 0),
                (float) ($candidate['satchel']['capacity'] ?? 0),
                $candidate['index'],
            ])
            ->values();

        $upgrade = $upgradeCandidates->first();
        if (is_array($upgrade)) {
            $parcels[$upgrade['index']]['satchel'] = $upgrade['satchel'];
            $this->appendUnitToParcel($parcels[$upgrade['index']], $unit);

            return true;
        }

        return false;
    }

    private function newParcelForUnit(array $unit, Collection $satchels): ?array
    {
        $satchel = $this->smallestSatchelForUsage(
            (int) $unit['min_satchel_rank'],
            (float) $unit['shipping_units'],
            $unit['weight_grams'],
            $satchels,
        );

        if ($satchel === null) {
            return null;
        }

        $parcel = [
            'satchel' => $satchel,
            'used_capacity' => 0.0,
            'total_weight_grams' => 0,
            'items' => [],
        ];

        $this->appendUnitToParcel($parcel, $unit);

        return $parcel;
    }

    private function appendUnitToParcel(array &$parcel, array $unit): void
    {
        $parcel['used_capacity'] = round((float) ($parcel['used_capacity'] ?? 0) + (float) $unit['shipping_units'], 2);
        $parcel['total_weight_grams'] = $this->newKnownWeight($parcel, $unit) ?? 0;
        $parcel['items'][] = [
            'title' => (string) $unit['display_title'],
            'shipping_units' => round((float) $unit['shipping_units'], 2),
            'weight_grams' => $unit['weight_grams'],
        ];
    }

    private function parcelCanFitUnit(array $parcel, array $unit): bool
    {
        $satchel = $parcel['satchel'] ?? null;
        if (! is_array($satchel)) {
            return false;
        }

        if ((int) ($satchel['rank'] ?? 0) < (int) $unit['min_satchel_rank']) {
            return false;
        }

        if ($this->parcelRemainingCapacityAfter($parcel, $unit) < -0.0001) {
            return false;
        }

        return $this->isWithinWeightLimit($this->newKnownWeight($parcel, $unit));
    }

    private function parcelRemainingCapacityAfter(array $parcel, array $unit): float
    {
        $satchelCapacity = (float) (($parcel['satchel']['capacity'] ?? 0));

        return round($satchelCapacity - ((float) ($parcel['used_capacity'] ?? 0) + (float) $unit['shipping_units']), 2);
    }

    private function newKnownWeight(array $parcel, array $unit): ?int
    {
        $current = (int) ($parcel['total_weight_grams'] ?? 0);
        $weight = $unit['weight_grams'];
        if ($weight === null) {
            return $current > 0 ? $current : null;
        }

        return $current + (int) $weight;
    }

    private function smallestSatchelForUsage(int $minRank, float $requiredCapacity, ?int $knownWeightGrams, Collection $satchels): ?array
    {
        return $satchels
            ->first(function (array $satchel) use ($minRank, $requiredCapacity, $knownWeightGrams): bool {
                if ((int) ($satchel['rank'] ?? 0) < $minRank) {
                    return false;
                }

                if ((float) ($satchel['capacity'] ?? 0) + 0.0001 < $requiredCapacity) {
                    return false;
                }

                return $this->isWithinWeightLimit($knownWeightGrams);
            });
    }

    private function isWithinWeightLimit(?int $knownWeightGrams): bool
    {
        if ($knownWeightGrams === null) {
            return true;
        }

        $limit = ShopShippingSettings::maxSatchelWeightGrams();
        if ($limit <= 0) {
            return true;
        }

        return $knownWeightGrams <= $limit;
    }

    private function satchelRankForCode(string $code, Collection $satchels): int
    {
        $satchel = $satchels->first(fn (array $item) => (string) ($item['code'] ?? '') === $code);

        return is_array($satchel) ? (int) ($satchel['rank'] ?? 0) : 0;
    }

    private function boxedShippingQuote(string $reason): array
    {
        $config = ShopShippingSettings::boxedShipping();
        $amount = $config['amount'] !== null ? round((float) $config['amount'], 2) : null;

        return [
            'can_checkout' => $amount !== null,
            'boxed_shipping_required' => true,
            'requires_manual_quote' => $amount === null,
            'method' => $config['label'],
            'package_summary' => null,
            'reason' => $reason !== '' ? $reason : $config['message'],
            'parcel_count' => 0,
            'parcels' => [],
            'satchel_breakdown' => [],
            'known_weight_grams' => 0,
            'amount' => $amount ?? 0.0,
        ];
    }
}
