<?php

namespace App\Services;

use App\Models\StoreShippingMethod;
use App\Models\StoreShippingMethodPackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StoreShippingMethodService
{
    /**
     * @return Collection<int, StoreShippingMethod>
     */
    public function activeForLines(Collection $lines): Collection
    {
        $containsPhysical = $lines->contains(fn ($line) => $line->product->isPhysical());
        if (! $containsPhysical) {
            return collect();
        }

        if (! Schema::hasTable('store_shipping_methods')) {
            return $this->fallbackMethods();
        }

        $methods = StoreShippingMethod::query()
            ->where('is_active', true)
            ->with([
                'packageOptions' => fn ($query) => $query
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $methods->isNotEmpty() ? $methods : $this->fallbackMethods();
    }

    public function resolveForLines(Collection $lines, ?string $code = null): ?StoreShippingMethod
    {
        $methods = $this->activeForLines($lines);
        if ($methods->isEmpty()) {
            return null;
        }

        $normalizedCode = trim((string) $code);
        if ($normalizedCode !== '') {
            $selected = $methods->first(fn (StoreShippingMethod $method) => (string) $method->code === $normalizedCode);
            if ($selected instanceof StoreShippingMethod) {
                return $selected;
            }
        }

        return $this->defaultFromCollection($methods);
    }

    public function isValidForLines(Collection $lines, ?string $code = null): bool
    {
        $normalizedCode = trim((string) $code);
        if ($normalizedCode === '') {
            return true;
        }

        return $this->activeForLines($lines)
            ->contains(fn (StoreShippingMethod $method) => (string) $method->code === $normalizedCode);
    }

    private function defaultFromCollection(Collection $methods): ?StoreShippingMethod
    {
        $default = $methods->first(fn (StoreShippingMethod $method) => (bool) $method->is_default);

        return $default instanceof StoreShippingMethod ? $default : $methods->first();
    }

    /**
     * @return Collection<int, StoreShippingMethod>
     */
    private function fallbackMethods(): Collection
    {
        $regular = new StoreShippingMethod([
                'code' => StoreShippingMethod::CODE_REGULAR,
                'name' => 'Regular shipping',
                'description' => 'Standard delivery for in-stock items.',
                'shipment_label' => 'Shipment',
                'immediate_status_label' => 'Ships now',
                'delayed_status_label' => 'Ships later',
                'calculator' => StoreShippingMethod::CALCULATOR_PACKAGES,
                'flat_rate_amount' => null,
                'delivery_estimate_min_days' => 3,
                'delivery_estimate_max_days' => 7,
                'rate_multiplier' => 1,
                'rate_adjustment_amount' => 0,
                'is_pickup' => false,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 0,
            ]);
        $this->setFallbackPackages($regular, [
            ['code' => 'small', 'label' => 'Small', 'sort_order' => 1, 'capacity' => 1.00, 'price' => 9.95],
            ['code' => 'medium', 'label' => 'Medium', 'sort_order' => 2, 'capacity' => 2.00, 'price' => 12.95],
            ['code' => 'large', 'label' => 'Large', 'sort_order' => 3, 'capacity' => 3.00, 'price' => 15.95],
            ['code' => 'extra_large', 'label' => 'Extra Large', 'sort_order' => 4, 'capacity' => 4.00, 'price' => 18.95],
        ]);

        $express = new StoreShippingMethod([
                'code' => StoreShippingMethod::CODE_EXPRESS,
                'name' => 'Express shipping',
                'description' => 'Faster delivery once dispatched.',
                'shipment_label' => 'Shipment',
                'immediate_status_label' => 'Ships now',
                'delayed_status_label' => 'Ships later',
                'calculator' => StoreShippingMethod::CALCULATOR_PACKAGES,
                'flat_rate_amount' => null,
                'delivery_estimate_min_days' => 1,
                'delivery_estimate_max_days' => 3,
                'rate_multiplier' => 1.35,
                'rate_adjustment_amount' => 0,
                'is_pickup' => false,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 1,
            ]);
        $this->setFallbackPackages($express, [
            ['code' => 'small', 'label' => 'Small', 'sort_order' => 1, 'capacity' => 1.00, 'price' => 13.43],
            ['code' => 'medium', 'label' => 'Medium', 'sort_order' => 2, 'capacity' => 2.00, 'price' => 17.48],
            ['code' => 'large', 'label' => 'Large', 'sort_order' => 3, 'capacity' => 3.00, 'price' => 21.53],
            ['code' => 'extra_large', 'label' => 'Extra Large', 'sort_order' => 4, 'capacity' => 4.00, 'price' => 25.58],
        ]);

        $pickup = new StoreShippingMethod([
                'code' => StoreShippingMethod::CODE_PICKUP,
                'name' => 'Pick up',
                'description' => 'Free pickup. We will contact you when your order is available to collect.',
                'shipment_label' => 'Collection',
                'immediate_status_label' => 'Available now',
                'delayed_status_label' => 'Available later',
                'calculator' => StoreShippingMethod::CALCULATOR_PICKUP,
                'flat_rate_amount' => 0,
                'delivery_estimate_min_days' => null,
                'delivery_estimate_max_days' => null,
                'rate_multiplier' => 1,
                'rate_adjustment_amount' => 0,
                'is_pickup' => true,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 2,
            ]);
        $pickup->setRelation('packageOptions', collect());

        return collect([$regular, $express, $pickup]);
    }

    /**
     * @param  list<array{code:string,label:string,sort_order:int,capacity:float,price:float}>  $packages
     */
    private function setFallbackPackages(StoreShippingMethod $method, array $packages): void
    {
        $method->setRelation('packageOptions', collect($packages)->map(
            fn (array $package): StoreShippingMethodPackage => new StoreShippingMethodPackage([
                'code' => $package['code'],
                'label' => $package['label'],
                'sort_order' => $package['sort_order'],
                'capacity' => $package['capacity'],
                'price' => $package['price'],
                'is_active' => true,
            ])
        ));
    }
}
