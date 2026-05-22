<?php

namespace App\Services;

use App\Models\StoreShippingMethod;
use App\Support\ShopShippingSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StoreShippingService
{
    public const REQUEST_QUOTE_METHOD_CODE = 'request_quote';

    public function __construct(
        private readonly StoreShippingMethodService $shippingMethods
    ) {}

    /**
     * @return Collection<int, StoreShippingMethod>
     */
    public function availableMethods(Collection $lines): Collection
    {
        return $this->shippingMethods->activeForLines($lines);
    }

    public function selectedMethod(Collection $lines, ?string $methodCode = null): ?StoreShippingMethod
    {
        return $this->shippingMethods->resolveForLines($lines, $methodCode);
    }

    public function isValidMethod(Collection $lines, ?string $methodCode = null): bool
    {
        return $this->shippingMethods->isValidForLines($lines, $methodCode);
    }

    public function quote(
        Collection $lines,
        ?string $country = null,
        ?string $methodCode = null,
        bool $consolidateShipments = false,
    ): array {
        $physicalLines = $lines
            ->filter(fn ($line) => $line->product->isPhysical() && (int) $line->quantity > 0)
            ->values();
        $processingPauseUntil = ShopShippingSettings::processingPauseUntil();

        if ($physicalLines->isEmpty()) {
            return $this->applyProcessingPauseMetadata($this->decorateAggregateQuote([
                'can_checkout' => true,
                'boxed_shipping_required' => false,
                'requires_manual_quote' => false,
                'method' => 'No shipping required',
                'package_summary' => null,
                'reason' => null,
                'parcel_count' => 0,
                'parcels' => [],
                'package_breakdown' => [],
                'satchel_breakdown' => [],
                'known_weight_grams' => 0,
                'amount' => 0.0,
                'manual_quote_line_keys' => [],
                'shipments' => [],
                'shipment_count' => 0,
                'split_shipments' => false,
                'consolidate_shipments' => false,
                'offers_consolidation' => false,
                'delayed_item_count' => 0,
                'second_shipment_charge_amount' => 0.0,
                'delayed_dispatch_date' => null,
            ], null), $processingPauseUntil);
        }

        $selectedMethod = $this->selectedMethod($lines, $methodCode);
        $shipmentGroups = $this->shipmentGroups($physicalLines, $consolidateShipments, $selectedMethod, $processingPauseUntil);
        $consolidationSavingsAmount = $this->consolidationSavingsAmount($shipmentGroups, $selectedMethod);
        $shipmentQuotes = collect($shipmentGroups['groups'] ?? [])->map(function (array $group) use ($selectedMethod): array {
            return [
                'group' => $group,
                'quote' => $this->singleShipmentQuote($group['lines'], $selectedMethod),
            ];
        })->values();

        $canCheckout = ! $shipmentQuotes->contains(
            fn (array $shipment) => ! (bool) ($shipment['quote']['can_checkout'] ?? true)
        );

        $shipments = $shipmentQuotes->map(function (array $shipment, int $index) use ($selectedMethod): array {
            $group = $shipment['group'];
            $quote = $shipment['quote'];

            return [
                'key' => (string) $group['type'],
                'title' => (string) $group['title'],
                'title_primary' => $group['title_primary'] ?? null,
                'title_meta' => $group['title_meta'] ?? null,
                'type' => (string) $group['type'],
                'amount' => round((float) ($quote['amount'] ?? 0), 2),
                'package_summary' => $quote['package_summary'] ?? null,
                'parcel_count' => (int) ($quote['parcel_count'] ?? 0),
                'known_weight_grams' => (int) ($quote['known_weight_grams'] ?? 0),
                'dispatch_date' => $group['dispatch_date'] ?? null,
                'dispatch_label' => $group['dispatch_label'] ?? null,
                'delivery_estimate_label' => $selectedMethod?->deliveryEstimateLabel(),
                'contains_preorder' => (bool) $group['contains_preorder'],
                'contains_backorder' => (bool) $group['contains_backorder'],
                'item_count' => (int) $group['item_count'],
                'line_count' => (int) $group['line_count'],
                'is_pickup' => (bool) ($quote['is_pickup'] ?? false),
                'can_checkout' => (bool) ($quote['can_checkout'] ?? true),
                'requires_manual_quote' => (bool) ($quote['requires_manual_quote'] ?? false),
                'shipment_number' => $index + 1,
                'items' => $group['items'],
            ];
        })->values();

        $mergedBreakdown = $shipmentQuotes
            ->flatMap(fn (array $shipment) => $shipment['quote']['package_breakdown'] ?? ($shipment['quote']['satchel_breakdown'] ?? []))
            ->groupBy('code')
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'code' => (string) ($first['code'] ?? ''),
                    'label' => (string) ($first['label'] ?? 'Package'),
                    'count' => (int) $group->sum('count'),
                    'unit_price' => round((float) ($first['unit_price'] ?? 0), 2),
                    'subtotal' => round((float) $group->sum('subtotal'), 2),
                ];
            })
            ->values()
            ->all();

        $singleShipment = $shipmentQuotes->count() === 1 ? $shipmentQuotes->first() : null;
        $packageSummary = is_array($singleShipment)
            ? ($singleShipment['quote']['package_summary'] ?? null)
            : null;

        $firstFailedShipment = $shipmentQuotes->first(
            fn (array $shipment) => ! (bool) ($shipment['quote']['can_checkout'] ?? true)
        );
        $manualQuoteLineKeys = $shipmentQuotes
            ->filter(fn (array $shipment) => (bool) ($shipment['quote']['requires_manual_quote'] ?? false))
            ->flatMap(fn (array $shipment): array => is_array($shipment['quote']['manual_quote_line_keys'] ?? null)
                ? $shipment['quote']['manual_quote_line_keys']
                : [])
            ->map(fn ($key): string => trim((string) $key))
            ->filter(fn (string $key): bool => $key !== '')
            ->unique()
            ->values()
            ->all();

        return $this->applyProcessingPauseMetadata($this->decorateAggregateQuote([
            'can_checkout' => $canCheckout,
            'boxed_shipping_required' => $shipmentQuotes->contains(
                fn (array $shipment) => (bool) ($shipment['quote']['boxed_shipping_required'] ?? false)
            ),
            'requires_manual_quote' => $shipmentQuotes->contains(
                fn (array $shipment) => (bool) ($shipment['quote']['requires_manual_quote'] ?? false)
            ),
            'method' => $selectedMethod instanceof StoreShippingMethod ? (string) $selectedMethod->name : 'Shipping',
            'package_summary' => $packageSummary,
            'reason' => is_array($firstFailedShipment) ? ($firstFailedShipment['quote']['reason'] ?? null) : null,
            'parcel_count' => (int) $shipmentQuotes->sum(fn (array $shipment) => (int) ($shipment['quote']['parcel_count'] ?? 0)),
            'parcels' => $shipmentQuotes->flatMap(fn (array $shipment) => $shipment['quote']['parcels'] ?? [])->values()->all(),
            'package_breakdown' => $mergedBreakdown,
            'satchel_breakdown' => $mergedBreakdown,
            'known_weight_grams' => (int) $shipmentQuotes->sum(fn (array $shipment) => (int) ($shipment['quote']['known_weight_grams'] ?? 0)),
            'amount' => round((float) $shipmentQuotes->sum(fn (array $shipment) => (float) ($shipment['quote']['amount'] ?? 0)), 2),
            'manual_quote_line_keys' => $manualQuoteLineKeys,
            'shipments' => $shipments->all(),
            'shipment_count' => $shipments->count(),
            'split_shipments' => $shipments->count() > 1,
            'consolidate_shipments' => $consolidateShipments && (bool) ($shipmentGroups['offers_consolidation'] ?? false),
            'offers_consolidation' => (bool) ($shipmentGroups['offers_consolidation'] ?? false),
            'delayed_item_count' => (int) ($shipmentGroups['delayed_item_count'] ?? 0),
            'second_shipment_charge_amount' => round((float) $shipments->slice(1)->sum('amount'), 2),
            'consolidation_savings_amount' => $consolidationSavingsAmount,
            'delayed_dispatch_date' => $shipmentGroups['delayed_dispatch_date'] ?? null,
        ], $selectedMethod), $processingPauseUntil);
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

    /**
     * @return Collection<int, array{code:string,label:string,rank:int,capacity:float,price:float}>
     */
    private function packageOptionsForMethod(?StoreShippingMethod $method): Collection
    {
        if (! $method instanceof StoreShippingMethod) {
            return collect();
        }

        if (! Schema::hasTable('store_shipping_method_packages')) {
            return $method->usesPackageCalculation()
                ? $this->legacyPackageOptionsForMethod($method)
                : collect();
        }

        $relation = $method->relationLoaded('packageOptions')
            ? $method->getRelation('packageOptions')
            : $method->packageOptions()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();

        $configuredPackages = collect($relation)
            ->filter(fn ($package) => (bool) ($package->is_active ?? true))
            ->map(function ($package): array {
                return [
                    'code' => trim((string) ($package->code ?? '')),
                    'label' => trim((string) ($package->label ?? 'Package')) ?: 'Package',
                    'rank' => max(1, (int) ($package->sort_order ?? 1)),
                    'capacity' => round(max(0.01, (float) ($package->capacity ?? 0.01)), 2),
                    'price' => round(max(0, (float) ($package->price ?? 0)), 2),
                ];
            })
            ->filter(fn (array $package): bool => $package['code'] !== '')
            ->sortBy('rank')
            ->values();

        if ($configuredPackages->isNotEmpty()) {
            return $configuredPackages
                ->map(function (array $package): array {
                    return [
                        'code' => (string) $package['code'],
                        'label' => (string) $package['label'],
                        'rank' => (int) $package['rank'],
                        'capacity' => (float) $package['capacity'],
                        'price' => (float) $package['price'],
                    ];
                })
                ->values();
        }

        return $method->usesPackageCalculation()
            ? $this->legacyPackageOptionsForMethod($method)
            : collect();
    }

    /**
     * @return Collection<int, array{code:string,label:string,rank:int,capacity:float,price:float}>
     */
    private function legacyPackageOptionsForMethod(StoreShippingMethod $method): Collection
    {
        return $this->satchels()
            ->map(function (array $satchel) use ($method): array {
                return [
                    'code' => (string) $satchel['code'],
                    'label' => (string) $satchel['label'],
                    'rank' => (int) $satchel['rank'],
                    'capacity' => round((float) $satchel['capacity'], 2),
                    'price' => round($method->adjustedAmount((float) $satchel['price']), 2),
                ];
            })
            ->values();
    }

    private function shipmentGroups(
        Collection $physicalLines,
        bool $consolidateShipments,
        ?StoreShippingMethod $method,
        ?Carbon $processingPauseUntil = null
    ): array
    {
        $immediateLines = collect();
        $delayedLines = collect();
        $allLines = collect();
        $delayedItems = 0;
        $shipmentLabel = $this->shipmentLabel($method);
        $immediateStatusLabel = $this->immediateStatusLabel($method);
        $delayedStatusLabel = $this->delayedStatusLabel($method);
        $processingPauseDate = $this->processingPauseDate($processingPauseUntil);

        foreach ($physicalLines as $line) {
            $quantity = max(0, (int) ($line->quantity ?? 0));
            if ($quantity <= 0) {
                continue;
            }

            $availableNowQuantity = $this->availableNowQuantity($line, $quantity);
            $delayedQuantity = max(0, $quantity - $availableNowQuantity);
            $allLines->push($this->cloneLineForShipment($line, $quantity, 'combined'));

            if ($availableNowQuantity > 0) {
                $immediateLines->push($this->cloneLineForShipment($line, $availableNowQuantity, 'immediate'));
            }

            if ($delayedQuantity > 0) {
                $delayedItems += $delayedQuantity;
                $delayedLines->push($this->cloneLineForShipment($line, $delayedQuantity, 'delayed'));
            }
        }

        $offersConsolidation = ! ($method?->isPickup() ?? false) && $immediateLines->isNotEmpty() && $delayedLines->isNotEmpty();
        $delayedDispatchDate = $this->latestDispatchDate($delayedLines);
        $effectiveDelayedDispatchDate = $this->laterDispatchDate($delayedDispatchDate, $processingPauseDate);
        $groups = collect();

        if ($offersConsolidation && $consolidateShipments) {
            $consolidatedTitle = 'Single '.Str::lower($shipmentLabel).' once all items are available';
            $groups->push($this->buildShipmentGroup(
                'consolidated',
                $consolidatedTitle,
                $allLines,
                $effectiveDelayedDispatchDate,
                $effectiveDelayedDispatchDate !== null
                    ? 'Everything together from approximately '.$this->formatDispatchDate($effectiveDelayedDispatchDate).'.'
                    : 'Held until all delayed items are available.',
                $this->estimatedTitleDetail($effectiveDelayedDispatchDate),
            ));
        } else {
            if ($immediateLines->isNotEmpty()) {
                $immediateTitle = $processingPauseDate !== null
                    ? ($delayedLines->isNotEmpty() ? $shipmentLabel.' 1' : $shipmentLabel)
                    : ($delayedLines->isNotEmpty()
                        ? $shipmentLabel.' 1: '.$immediateStatusLabel
                        : $shipmentLabel.': '.$immediateStatusLabel);
                $groups->push($this->buildShipmentGroup(
                    'immediate',
                    $immediateTitle,
                    $immediateLines,
                    $processingPauseDate,
                    null,
                    $this->processingPauseTitleMeta($method, $processingPauseDate),
                ));
            }

            if ($delayedLines->isNotEmpty()) {
                $delayedTitle = $immediateLines->isNotEmpty()
                    ? $shipmentLabel.' 2: '.$delayedStatusLabel
                    : $shipmentLabel.': '.$delayedStatusLabel;
                $groups->push($this->buildShipmentGroup(
                    'delayed',
                    $delayedTitle,
                    $delayedLines,
                    $effectiveDelayedDispatchDate,
                    $effectiveDelayedDispatchDate !== null
                        ? $this->delayedDispatchLabel($method, $effectiveDelayedDispatchDate)
                        : 'Expected once delayed items become available.',
                    $this->estimatedTitleDetail($effectiveDelayedDispatchDate),
                ));
            }
        }

        return [
            'groups' => $groups->values()->all(),
            'offers_consolidation' => $offersConsolidation,
            'delayed_item_count' => $delayedItems,
            'delayed_dispatch_date' => $effectiveDelayedDispatchDate,
            'immediate_lines' => $immediateLines->values(),
            'delayed_lines' => $delayedLines->values(),
            'all_lines' => $allLines->values(),
        ];
    }

    private function buildShipmentGroup(
        string $type,
        string $title,
        Collection $lines,
        ?string $dispatchDate,
        ?string $dispatchLabel,
        ?string $titleMeta = null,
    ): array {
        $resolvedTitle = trim($title);
        $resolvedTitleMeta = trim((string) $titleMeta);

        return [
            'type' => $type,
            'title' => $resolvedTitleMeta !== '' ? $resolvedTitle.' - '.$resolvedTitleMeta : $resolvedTitle,
            'title_primary' => $resolvedTitle,
            'title_meta' => $resolvedTitleMeta !== '' ? $resolvedTitleMeta : null,
            'lines' => $lines->values(),
            'dispatch_date' => $dispatchDate,
            'dispatch_label' => $dispatchLabel,
            'contains_preorder' => $lines->contains(fn ($line) => (string) ($line->delayed_fulfilment_type ?? '') === 'preorder'),
            'contains_backorder' => $lines->contains(fn ($line) => (string) ($line->delayed_fulfilment_type ?? '') === 'backorder'),
            'item_count' => (int) $lines->sum('quantity'),
            'line_count' => $lines->count(),
            'items' => $lines->map(function ($line): array {
                return [
                    'display_title' => (string) ($line->display_title ?? $line->product->title ?? 'Item'),
                    'quantity' => (int) ($line->quantity ?? 0),
                    'delayed_fulfilment_type' => ($line->delayed_quantity ?? 0) > 0
                        ? ((string) ($line->delayed_fulfilment_type ?? '') ?: null)
                        : null,
                ];
            })->values()->all(),
        ];
    }

    private function singleShipmentQuote(Collection $lines, ?StoreShippingMethod $method): array
    {
        if ($lines->isEmpty()) {
            return $this->decorateShipmentQuote([
                'can_checkout' => true,
                'boxed_shipping_required' => false,
                'requires_manual_quote' => false,
                'method' => 'No shipping required',
                'package_summary' => null,
                'reason' => null,
                'parcel_count' => 0,
                'parcels' => [],
                'package_breakdown' => [],
                'satchel_breakdown' => [],
                'known_weight_grams' => 0,
                'amount' => 0.0,
                'manual_quote_line_keys' => [],
            ], $method);
        }

        if ($method?->isPickup()) {
            return $this->decorateShipmentQuote($this->pickupQuote($method), $method);
        }

        $packageOptions = $this->packageOptionsForMethod($method);
        if ($packageOptions->isNotEmpty()) {
            return $this->decorateShipmentQuote($this->configuredPackageQuote($lines, $packageOptions), $method);
        }

        if ($method?->usesFlatRateCalculation()) {
            return $this->decorateShipmentQuote($this->flatRateQuote($method), $method);
        }

        $quote = $this->satchelQuote($lines);
        if ($method instanceof StoreShippingMethod) {
            $quote['amount'] = $method->adjustedAmount((float) ($quote['amount'] ?? 0));
        }

        return $this->decorateShipmentQuote($quote, $method);
    }

    /**
     * @param  Collection<int, array{code:string,label:string,rank:int,capacity:float,price:float}>  $packages
     */
    private function configuredPackageQuote(Collection $physicalLines, Collection $packages): array
    {
        $hasInvalidPackageItem = $physicalLines->contains(function ($line): bool {
            $shippingUnits = round(max(0, (float) ($line->unit_shipping_units ?? $line->shipping_units ?? 0)), 3);

            return $shippingUnits <= 0;
        });
        if ($hasInvalidPackageItem) {
            return $this->boxedShippingQuote(
                'Some physical products do not have package units configured.',
                $this->manualQuoteLineKeysForMissingShippingUnits($physicalLines)
            );
        }

        $packUnits = $this->expandLines($physicalLines);
        if ($packUnits->isEmpty()) {
            return $this->boxedShippingQuote(
                'Some physical products do not have package units configured.',
                $this->manualQuoteLineKeysForMissingShippingUnits($physicalLines)
            );
        }

        $parcels = [];

        foreach ($packUnits as $unit) {
            $placed = $this->placeIntoExistingParcel($parcels, $unit, $packages);
            if ($placed) {
                continue;
            }

            $parcel = $this->newParcelForUnit($unit, $packages);
            if ($parcel === null) {
                return $this->boxedShippingQuote(
                    'This order cannot be packed into the configured package sizes.',
                    $this->manualQuoteLineKeysForAllPhysicalLines($physicalLines)
                );
            }

            $parcels[] = $parcel;
        }

        $parcelCollection = collect($parcels)->map(function (array $parcel): array {
            $package = $parcel['satchel'];

            return [
                'code' => (string) ($package['code'] ?? ''),
                'label' => (string) ($package['label'] ?? 'Package'),
                'rank' => (int) ($package['rank'] ?? 0),
                'capacity' => round((float) ($package['capacity'] ?? 0), 2),
                'price' => round((float) ($package['price'] ?? 0), 2),
                'used_capacity' => round((float) ($parcel['used_capacity'] ?? 0), 2),
                'remaining_capacity' => round(max(0, (float) ($package['capacity'] ?? 0) - (float) ($parcel['used_capacity'] ?? 0)), 2),
                'total_weight_grams' => (int) ($parcel['total_weight_grams'] ?? 0),
                'items' => array_values($parcel['items'] ?? []),
            ];
        })->values();

        $packageBreakdown = $parcelCollection
            ->groupBy('code')
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'code' => (string) ($first['code'] ?? ''),
                    'label' => (string) ($first['label'] ?? 'Package'),
                    'count' => $group->count(),
                    'unit_price' => round((float) ($first['price'] ?? 0), 2),
                    'subtotal' => round((float) $group->sum('price'), 2),
                ];
            })
            ->sortBy(fn (array $item) => (int) $this->satchelRankForCode((string) $item['code'], $packages))
            ->values();

        $packageSummary = $packageBreakdown
            ->map(fn (array $item) => $item['count'].' x '.$item['label'])
            ->implode(', ');

        return [
            'can_checkout' => true,
            'boxed_shipping_required' => false,
            'requires_manual_quote' => false,
            'method' => 'Package shipping',
            'package_summary' => $packageSummary !== '' ? $packageSummary : null,
            'reason' => null,
            'parcel_count' => $parcelCollection->count(),
            'parcels' => $parcelCollection->all(),
            'package_breakdown' => $packageBreakdown->all(),
            'satchel_breakdown' => $packageBreakdown->all(),
            'known_weight_grams' => (int) $parcelCollection->sum('total_weight_grams'),
            'amount' => round((float) $parcelCollection->sum('price'), 2),
            'manual_quote_line_keys' => [],
        ];
    }

    private function satchelQuote(Collection $physicalLines): array
    {
        $hasInvalidSatchelItem = $physicalLines->contains(function ($line): bool {
            $shippingUnits = round(max(0, (float) ($line->unit_shipping_units ?? $line->shipping_units ?? 0)), 3);
            $boxOnly = (bool) ($line->box_only ?? false);

            return ! $boxOnly && $shippingUnits <= 0;
        });
        if ($hasInvalidSatchelItem) {
            return $this->boxedShippingQuote(
                'Some physical products do not have satchel shipping units configured.',
                $this->manualQuoteLineKeysForMissingSatchelUnits($physicalLines)
            );
        }

        $packUnits = $this->expandLines($physicalLines);
        if ($packUnits->isEmpty()) {
            return $this->boxedShippingQuote(
                'Some physical products do not have satchel shipping units configured.',
                $this->manualQuoteLineKeysForMissingSatchelUnits($physicalLines)
            );
        }

        if ($packUnits->contains(fn ($unit) => $unit['box_only'])) {
            return $this->boxedShippingQuote(
                'This order contains items that must ship in a box.',
                $this->manualQuoteLineKeysForBoxOnlyLines($physicalLines)
            );
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
                return $this->boxedShippingQuote(
                    'This order cannot be packed into the configured satchels.',
                    $this->manualQuoteLineKeysForAllPhysicalLines($physicalLines)
                );
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
            'package_breakdown' => $satchelBreakdown->all(),
            'satchel_breakdown' => $satchelBreakdown->all(),
            'known_weight_grams' => (int) $parcelCollection->sum('total_weight_grams'),
            'amount' => round((float) $parcelCollection->sum('price'), 2),
            'manual_quote_line_keys' => [],
        ];
    }

    private function expandLines(Collection $lines): Collection
    {
        return $lines
            ->flatMap(function ($line): array {
                $quantity = max(0, (int) $line->quantity);
                if ($quantity <= 0) {
                    return [];
                }

                $shippingUnits = round(max(0, (float) ($line->unit_shipping_units ?? $line->shipping_units ?? 0)), 3);
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
                (int) $candidate['satchel']['rank'],
                (float) $candidate['satchel']['capacity'],
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
        $parcel['used_capacity'] = round((float) ($parcel['used_capacity'] ?? 0) + (float) $unit['shipping_units'], 3);
        $parcel['total_weight_grams'] = $this->newKnownWeight($parcel, $unit) ?? 0;
        $parcel['items'][] = [
            'title' => (string) $unit['display_title'],
            'shipping_units' => round((float) $unit['shipping_units'], 3),
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
        $satchelCapacity = (float) ($parcel['satchel']['capacity'] ?? 0);

        return round($satchelCapacity - ((float) ($parcel['used_capacity'] ?? 0) + (float) $unit['shipping_units']), 3);
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

    /**
     * @param Collection<int, array{code:string,label:string,rank:int,capacity:float,price:float}> $satchels
     * @return array{code:string,label:string,rank:int,capacity:float,price:float}|null
     */
    private function smallestSatchelForUsage(int $minRank, float $requiredCapacity, ?int $knownWeightGrams, Collection $satchels): ?array
    {
        $satchel = $satchels
            ->first(function (array $satchel) use ($minRank, $requiredCapacity, $knownWeightGrams): bool {
                if ((int) $satchel['rank'] < $minRank) {
                    return false;
                }

                if ((float) $satchel['capacity'] + 0.0001 < $requiredCapacity) {
                    return false;
                }

                return $this->isWithinWeightLimit($knownWeightGrams);
            });

        return is_array($satchel) ? $satchel : null;
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

    private function boxedShippingQuote(string $reason, array $manualQuoteLineKeys = []): array
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
            'package_breakdown' => [],
            'satchel_breakdown' => [],
            'known_weight_grams' => 0,
            'amount' => $amount ?? 0.0,
            'manual_quote_line_keys' => array_values(array_unique(array_filter(array_map(
                fn ($key): string => trim((string) $key),
                $manualQuoteLineKeys
            ), fn (string $key): bool => $key !== ''))),
        ];
    }

    private function pickupQuote(StoreShippingMethod $method): array
    {
        return [
            'can_checkout' => true,
            'boxed_shipping_required' => false,
            'requires_manual_quote' => false,
            'method' => (string) $method->name,
            'package_summary' => null,
            'reason' => null,
            'parcel_count' => 0,
            'parcels' => [],
            'package_breakdown' => [],
            'satchel_breakdown' => [],
            'known_weight_grams' => 0,
            'amount' => 0.0,
            'manual_quote_line_keys' => [],
        ];
    }

    private function flatRateQuote(StoreShippingMethod $method): array
    {
        return [
            'can_checkout' => true,
            'boxed_shipping_required' => false,
            'requires_manual_quote' => false,
            'method' => (string) $method->name,
            'package_summary' => null,
            'reason' => null,
            'parcel_count' => 0,
            'parcels' => [],
            'package_breakdown' => [],
            'satchel_breakdown' => [],
            'known_weight_grams' => 0,
            'amount' => $method->adjustedAmount((float) ($method->flat_rate_amount ?? 0)),
            'manual_quote_line_keys' => [],
        ];
    }

    private function decorateShipmentQuote(array $quote, ?StoreShippingMethod $method): array
    {
        $quote['selected_method_code'] = $method?->code;
        $quote['method'] = $method instanceof StoreShippingMethod ? (string) $method->name : ($quote['method'] ?? 'Shipping');
        $quote['note'] = $method instanceof StoreShippingMethod
            ? (trim((string) $method->description) ?: ($quote['note'] ?? null))
            : ($quote['note'] ?? null);
        $quote['is_pickup'] = $method?->isPickup() ?? (bool) ($quote['is_pickup'] ?? false);
        $quote['delivery_estimate_label'] = $method?->deliveryEstimateLabel();

        return $quote;
    }

    private function decorateAggregateQuote(array $quote, ?StoreShippingMethod $method): array
    {
        $quote['selected_method_code'] = $method instanceof StoreShippingMethod ? $method->code : ($quote['selected_method_code'] ?? null);
        $quote['method'] = $method instanceof StoreShippingMethod ? (string) $method->name : ($quote['method'] ?? 'Shipping');
        $quote['note'] = $method instanceof StoreShippingMethod
            ? (trim((string) $method->description) ?: ($quote['note'] ?? null))
            : ($quote['note'] ?? null);
        $quote['is_pickup'] = $method?->isPickup() ?? (bool) ($quote['is_pickup'] ?? false);
        $quote['delivery_estimate_label'] = $method?->deliveryEstimateLabel();

        return $quote;
    }

    private function applyProcessingPauseMetadata(array $quote, ?Carbon $processingPauseUntil): array
    {
        $processingPauseUntil = $processingPauseUntil instanceof Carbon ? $processingPauseUntil->copy()->startOfDay() : null;
        $processingPauseDate = $this->processingPauseDate($processingPauseUntil);

        $quote['processing_pause_until'] = $processingPauseDate;
        $quote['processing_pause_notice'] = ShopShippingSettings::processingPauseNotice();

        return $quote;
    }

    private function processingPauseDate(?Carbon $processingPauseUntil): ?string
    {
        if (! $processingPauseUntil instanceof Carbon) {
            return null;
        }

        return $processingPauseUntil->copy()->startOfDay()->toDateString();
    }

    private function laterDispatchDate(?string $existingDate, ?string $processingPauseDate): ?string
    {
        $existing = trim((string) $existingDate);
        $pause = trim((string) $processingPauseDate);

        if ($existing === '') {
            return $pause !== '' ? $pause : null;
        }

        if ($pause === '') {
            return $existing;
        }

        return $existing >= $pause ? $existing : $pause;
    }

    private function processingPauseTitleMeta(?StoreShippingMethod $method, ?string $processingPauseDate): ?string
    {
        $formattedDate = $this->formatDispatchDate($processingPauseDate);
        if ($formattedDate === null) {
            return null;
        }

        if ($method?->isPickup()) {
            return 'Available from '.$formattedDate;
        }

        return 'Processing from '.$formattedDate;
    }

    /**
     * @return list<string>
     */
    private function manualQuoteLineKeysForMissingShippingUnits(Collection $lines): array
    {
        return $lines
            ->filter(function ($line): bool {
                $shippingUnits = round(max(0, (float) ($line->unit_shipping_units ?? $line->shipping_units ?? 0)), 3);

                return $shippingUnits <= 0;
            })
            ->map(fn ($line): string => trim((string) ($line->key ?? '')))
            ->filter(fn (string $key): bool => $key !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function manualQuoteLineKeysForMissingSatchelUnits(Collection $lines): array
    {
        return $lines
            ->filter(function ($line): bool {
                $shippingUnits = round(max(0, (float) ($line->unit_shipping_units ?? $line->shipping_units ?? 0)), 3);
                $boxOnly = (bool) ($line->box_only ?? false);

                return ! $boxOnly && $shippingUnits <= 0;
            })
            ->map(fn ($line): string => trim((string) ($line->key ?? '')))
            ->filter(fn (string $key): bool => $key !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function manualQuoteLineKeysForBoxOnlyLines(Collection $lines): array
    {
        return $lines
            ->filter(fn ($line): bool => (bool) ($line->box_only ?? false))
            ->map(fn ($line): string => trim((string) ($line->key ?? '')))
            ->filter(fn (string $key): bool => $key !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function manualQuoteLineKeysForAllPhysicalLines(Collection $lines): array
    {
        return $lines
            ->map(fn ($line): string => trim((string) ($line->key ?? '')))
            ->filter(fn (string $key): bool => $key !== '')
            ->values()
            ->all();
    }

    private function availableNowQuantity(object $line, int $fallbackQuantity): int
    {
        if (isset($line->available_now_quantity)) {
            return max(0, min($fallbackQuantity, (int) $line->available_now_quantity));
        }

        $delayedQuantity = isset($line->delayed_quantity) ? max(0, (int) $line->delayed_quantity) : 0;

        return max(0, $fallbackQuantity - $delayedQuantity);
    }

    private function cloneLineForShipment(object $line, int $quantity, string $shipmentType): object
    {
        $shipmentLine = clone $line;
        $shipmentLine->quantity = $quantity;
        $shipmentLine->shipment_type = $shipmentType;
        $shipmentLine->line_price = round((float) ($shipmentLine->unit_price ?? 0) * $quantity, 2);
        $taxRate = (float) ($shipmentLine->product->tax_rate ?? 0);
        $shipmentLine->line_gst = $this->inclusiveTaxAmount((float) $shipmentLine->line_price, $taxRate);
        $shipmentLine->available_now_quantity = $shipmentType === 'delayed' ? 0 : $quantity;
        $shipmentLine->delayed_quantity = $shipmentType === 'delayed' ? $quantity : 0;

        if ($shipmentType !== 'delayed') {
            $shipmentLine->delayed_fulfilment_type = null;
            $shipmentLine->delayed_shipping_estimate = null;
            $shipmentLine->preorder_shipping_estimate = null;
        } elseif ((string) ($shipmentLine->delayed_fulfilment_type ?? '') !== 'preorder') {
            $shipmentLine->preorder_shipping_estimate = null;
        }

        return $shipmentLine;
    }

    private function latestDispatchDate(Collection $lines): ?string
    {
        $dates = $lines->map(function ($line): ?string {
            $date = $line->delayed_shipping_estimate ?? $line->preorder_shipping_estimate ?? null;

            if ($date instanceof Carbon) {
                return $date->toDateString();
            }

            $value = trim((string) $date);

            return $value !== '' ? $value : null;
        })->filter()->values();

        if ($dates->isEmpty()) {
            return null;
        }

        return (string) $dates->sort()->last();
    }

    private function formatDispatchDate(?string $date): ?string
    {
        $value = trim((string) $date);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('F jS Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function estimatedTitleDetail(?string $dispatchDate): ?string
    {
        $formattedDate = $this->formatDispatchDate($dispatchDate);

        if ($formattedDate === null) {
            return null;
        }

        return 'Estimated '.$formattedDate;
    }

    private function consolidationSavingsAmount(array $shipmentGroups, ?StoreShippingMethod $method): float
    {
        if (! (bool) ($shipmentGroups['offers_consolidation'] ?? false)) {
            return 0.0;
        }

        $immediateLines = collect($shipmentGroups['immediate_lines'] ?? []);
        $delayedLines = collect($shipmentGroups['delayed_lines'] ?? []);
        $allLines = collect($shipmentGroups['all_lines'] ?? []);

        if ($immediateLines->isEmpty() || $delayedLines->isEmpty() || $allLines->isEmpty()) {
            return 0.0;
        }

        $splitAmount = round(
            (float) ($this->singleShipmentQuote($immediateLines, $method)['amount'] ?? 0)
            + (float) ($this->singleShipmentQuote($delayedLines, $method)['amount'] ?? 0),
            2,
        );
        $consolidatedAmount = round((float) ($this->singleShipmentQuote($allLines, $method)['amount'] ?? 0), 2);

        return round(max(0, $splitAmount - $consolidatedAmount), 2);
    }

    private function shipmentLabel(?StoreShippingMethod $method): string
    {
        return $method?->shipmentLabel() ?? 'Shipment';
    }

    private function immediateStatusLabel(?StoreShippingMethod $method): string
    {
        return $method?->immediateStatusLabel() ?? 'Ships now';
    }

    private function delayedStatusLabel(?StoreShippingMethod $method): string
    {
        return $method?->delayedStatusLabel() ?? 'Ships later';
    }

    private function delayedDispatchLabel(?StoreShippingMethod $method, string $dispatchDate): string
    {
        $formattedDate = $this->formatDispatchDate($dispatchDate) ?? trim($dispatchDate);
        $delayedStatusLabel = Str::lower($this->delayedStatusLabel($method));

        if (Str::startsWith($delayedStatusLabel, 'available')) {
            return 'Expected to be available from '.$formattedDate.'.';
        }

        return 'Expected to ship from '.$formattedDate.'.';
    }

    private function inclusiveTaxAmount(float $amount, float $taxRate): float
    {
        if ($amount <= 0 || $taxRate <= 0) {
            return 0.0;
        }

        return round($amount - ($amount / (1 + $taxRate)), 2);
    }
}
