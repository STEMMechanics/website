<?php

namespace App\Http\Controllers;

use App\Models\SiteOption;
use App\Models\StoreShippingMethod;
use App\Models\StoreShippingMethodPackage;
use App\Support\ShopAvailability;
use App\Support\ShopShippingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopSettingsController extends Controller
{
    public function edit(ShopAvailability $availability): View
    {
        return view('admin.shop.settings', [
            'publicEnabled' => $availability->isPublicEnabled(),
            'maxSatchelWeightGrams' => ShopShippingSettings::maxSatchelWeightGrams(),
            'boxedShipping' => ShopShippingSettings::boxedShipping(),
            'shippingMethods' => $this->shippingMethods(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $hasShippingMethodsTable = Schema::hasTable('store_shipping_methods');
        $hasShippingMethodPackagesTable = Schema::hasTable('store_shipping_method_packages');

        $rules = [
            'public_enabled' => ['required', 'boolean'],
            'max_satchel_weight_grams' => ['required', 'integer', 'min:0', 'max:50000'],
            'boxed_shipping_label' => ['required', 'string', 'max:120'],
            'boxed_shipping_message' => ['required', 'string', 'max:500'],
            'boxed_shipping_amount' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
        ];

        if ($hasShippingMethodsTable) {
            $rules = array_merge($rules, [
                'shipping_methods' => ['required', 'array', 'min:1'],
                'shipping_methods.*.id' => ['nullable', 'integer', Rule::exists('store_shipping_methods', 'id')],
                'shipping_methods.*.code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/'],
                'shipping_methods.*.name' => ['required', 'string', 'max:120'],
                'shipping_methods.*.description' => ['nullable', 'string', 'max:500'],
                'shipping_methods.*.shipment_label' => ['nullable', 'string', 'max:80'],
                'shipping_methods.*.immediate_status_label' => ['nullable', 'string', 'max:80'],
                'shipping_methods.*.delayed_status_label' => ['nullable', 'string', 'max:80'],
                'shipping_methods.*.delivery_estimate_min_days' => ['nullable', 'integer', 'min:0', 'max:365'],
                'shipping_methods.*.delivery_estimate_max_days' => ['nullable', 'integer', 'min:0', 'max:365'],
                'shipping_methods.*.is_default' => ['nullable', 'boolean'],
                'shipping_methods.*.is_active' => ['nullable', 'boolean'],
                'shipping_methods.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            ]);

            if ($hasShippingMethodPackagesTable) {
                $rules = array_merge($rules, [
                    'shipping_methods.*.packages' => ['nullable', 'array'],
                    'shipping_methods.*.packages.*.id' => ['nullable', 'integer', Rule::exists('store_shipping_method_packages', 'id')],
                    'shipping_methods.*.packages.*.code' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/'],
                    'shipping_methods.*.packages.*.label' => ['nullable', 'string', 'max:120'],
                    'shipping_methods.*.packages.*.sort_order' => ['nullable', 'integer', 'min:1', 'max:999'],
                    'shipping_methods.*.packages.*.capacity' => ['nullable', 'numeric', 'min:0.01', 'max:999'],
                    'shipping_methods.*.packages.*.price' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
                    'shipping_methods.*.packages.*.is_active' => ['nullable', 'boolean'],
                ]);
            }
        }

        $validated = $request->validate($rules, [
            'shipping_methods.*.code.regex' => 'Shipping channel codes may only contain lowercase letters, numbers, hyphens, and underscores.',
            'shipping_methods.*.packages.*.code.regex' => 'Package codes may only contain lowercase letters, numbers, hyphens, and underscores.',
        ]);

        if ($hasShippingMethodsTable) {
            $this->syncShippingMethods($this->normalizeShippingMethods($validated['shipping_methods'] ?? []));
        }

        $this->storeOption(ShopAvailability::PUBLIC_ENABLED_OPTION, (string) ((int) $validated['public_enabled']));
        $this->storeOption(ShopShippingSettings::MAX_WEIGHT_OPTION, (string) ((int) $validated['max_satchel_weight_grams']));
        $this->storeOption(ShopShippingSettings::BOXED_LABEL_OPTION, trim((string) $validated['boxed_shipping_label']));
        $this->storeOption(ShopShippingSettings::BOXED_MESSAGE_OPTION, trim((string) $validated['boxed_shipping_message']));
        $this->storeOption(
            ShopShippingSettings::BOXED_AMOUNT_OPTION,
            ($validated['boxed_shipping_amount'] ?? null) !== null
                ? number_format((float) $validated['boxed_shipping_amount'], 2, '.', '')
                : ''
        );

        session()->flash('message', 'Store settings updated.');
        session()->flash('message-title', 'Settings saved');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    private function storeOption(string $name, string $value): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => $name],
            ['value' => $value],
        );
    }

    private function shippingMethods(): array
    {
        if (! Schema::hasTable('store_shipping_methods')) {
            return [];
        }

        $query = StoreShippingMethod::query()
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id');

        if (Schema::hasTable('store_shipping_method_packages')) {
            $query->with([
                'packageOptions' => fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ]);
        }

        return $query
            ->get()
            ->map(function (StoreShippingMethod $method): array {
                $packages = collect($method->getRelation('packageOptions') ?? [])
                    ->map(function (StoreShippingMethodPackage $package): array {
                        return [
                            'id' => (int) $package->id,
                            'code' => (string) $package->code,
                            'label' => (string) $package->label,
                            'sort_order' => (int) $package->sort_order,
                            'capacity' => number_format((float) $package->capacity, 2, '.', ''),
                            'price' => number_format((float) $package->price, 2, '.', ''),
                            'is_active' => (bool) $package->is_active,
                        ];
                    })
                    ->values()
                    ->all();

                if ($packages === [] && ! $method->isPickup()) {
                    $packages = $this->fallbackPackageOptionsForMethod($method);
                }

                return [
                    'id' => (int) $method->id,
                    'code' => (string) $method->code,
                    'name' => (string) $method->name,
                    'description' => (string) ($method->description ?? ''),
                    'shipment_label' => (string) $method->shipmentLabel(),
                    'immediate_status_label' => (string) $method->immediateStatusLabel(),
                    'delayed_status_label' => (string) $method->delayedStatusLabel(),
                    'delivery_estimate_min_days' => $method->delivery_estimate_min_days !== null ? (string) $method->delivery_estimate_min_days : '',
                    'delivery_estimate_max_days' => $method->delivery_estimate_max_days !== null ? (string) $method->delivery_estimate_max_days : '',
                    'is_pickup' => $method->isPickup() || $packages === [],
                    'is_default' => (bool) $method->is_default,
                    'is_active' => (bool) $method->is_active,
                    'sort_order' => (int) $method->sort_order,
                    'packages' => $packages,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:null,code:string,label:string,sort_order:int,capacity:string,price:string,is_active:bool}>
     */
    private function fallbackPackageOptionsForMethod(StoreShippingMethod $method): array
    {
        return ShopShippingSettings::satchels()
            ->map(function (array $package) use ($method): array {
                return [
                    'id' => null,
                    'code' => (string) $package['code'],
                    'label' => (string) $package['label'],
                    'sort_order' => max(1, (int) $package['rank']),
                    'capacity' => number_format((float) $package['capacity'], 2, '.', ''),
                    'price' => number_format($method->adjustedAmount((float) $package['price']), 2, '.', ''),
                    'is_active' => (bool) $package['active'],
                ];
            })
            ->filter(fn (array $package): bool => trim((string) $package['code']) !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     id:int|null,
     *     code:string,
     *     name:string,
     *     description:string|null,
     *     shipment_label:string,
     *     immediate_status_label:string,
     *     delayed_status_label:string,
     *     calculator:string,
     *     flat_rate_amount:float|null,
     *     delivery_estimate_min_days:int|null,
     *     delivery_estimate_max_days:int|null,
     *     rate_multiplier:float,
     *     rate_adjustment_amount:float,
     *     is_pickup:bool,
     *     is_default:bool,
     *     is_active:bool,
     *     sort_order:int,
     *     packages:list<array{
     *         id:int|null,
     *         code:string,
     *         label:string,
     *         sort_order:int,
     *         capacity:float|null,
     *         price:float|null,
     *         is_active:bool
     *     }>
     * }>
     */
    private function normalizeShippingMethods(array $rawMethods): array
    {
        $methods = collect($rawMethods)
            ->map(function ($method, int $methodIndex): array {
                $method = is_array($method) ? $method : [];
                $packages = collect($method['packages'] ?? [])
                    ->map(function ($package, int $packageIndex): array {
                        $package = is_array($package) ? $package : [];

                        return [
                            'id' => isset($package['id']) && $package['id'] !== '' ? (int) $package['id'] : null,
                            'code' => Str::lower(trim((string) ($package['code'] ?? ''))),
                            'label' => trim((string) ($package['label'] ?? '')),
                            'sort_order' => ($package['sort_order'] ?? '') !== '' ? (int) $package['sort_order'] : $packageIndex + 1,
                            'capacity' => ($package['capacity'] ?? '') !== '' ? round((float) $package['capacity'], 2) : null,
                            'price' => ($package['price'] ?? '') !== '' ? round((float) $package['price'], 2) : null,
                            'is_active' => filter_var($package['is_active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                        ];
                    })
                    ->filter(function (array $package): bool {
                        return $package['code'] !== ''
                            || $package['label'] !== ''
                            || $package['capacity'] !== null
                            || $package['price'] !== null;
                    })
                    ->values()
                    ->all();
                $isPickup = $packages === [];
                $terminology = $this->defaultShippingTerminology($isPickup);

                return [
                    'id' => isset($method['id']) && $method['id'] !== '' ? (int) $method['id'] : null,
                    'code' => Str::lower(trim((string) ($method['code'] ?? ''))),
                    'name' => trim((string) ($method['name'] ?? '')),
                    'description' => trim((string) ($method['description'] ?? '')) ?: null,
                    'shipment_label' => trim((string) ($method['shipment_label'] ?? '')) ?: $terminology['shipment_label'],
                    'immediate_status_label' => trim((string) ($method['immediate_status_label'] ?? '')) ?: $terminology['immediate_status_label'],
                    'delayed_status_label' => trim((string) ($method['delayed_status_label'] ?? '')) ?: $terminology['delayed_status_label'],
                    'calculator' => $isPickup ? StoreShippingMethod::CALCULATOR_PICKUP : StoreShippingMethod::CALCULATOR_PACKAGES,
                    'flat_rate_amount' => $isPickup ? 0.00 : null,
                    'delivery_estimate_min_days' => ($method['delivery_estimate_min_days'] ?? '') !== '' ? (int) $method['delivery_estimate_min_days'] : null,
                    'delivery_estimate_max_days' => ($method['delivery_estimate_max_days'] ?? '') !== '' ? (int) $method['delivery_estimate_max_days'] : null,
                    'rate_multiplier' => 1.00,
                    'rate_adjustment_amount' => 0.00,
                    'is_pickup' => $isPickup,
                    'is_default' => (bool) ($method['is_default'] ?? false),
                    'is_active' => (bool) ($method['is_active'] ?? false),
                    'sort_order' => (int) ($method['sort_order'] ?? $methodIndex),
                    'packages' => $packages,
                ];
            })
            ->values();

        if ($methods->isEmpty()) {
            throw ValidationException::withMessages([
                'shipping_methods' => 'Add at least one delivery channel.',
            ]);
        }

        if (! $methods->contains(fn (array $method): bool => $method['is_active'])) {
            throw ValidationException::withMessages([
                'shipping_methods' => 'At least one delivery channel must be active.',
            ]);
        }

        $duplicateCodes = $methods->duplicates('code')->filter()->values();
        if ($duplicateCodes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'shipping_methods' => 'Delivery channel codes must be unique.',
            ]);
        }

        $errors = [];
        foreach ($methods as $index => $method) {
            if (
                $method['delivery_estimate_min_days'] !== null
                && $method['delivery_estimate_max_days'] !== null
                && $method['delivery_estimate_min_days'] > $method['delivery_estimate_max_days']
            ) {
                $errors['shipping_methods.'.$index.'.delivery_estimate_max_days'][] = 'The maximum delivery ETA must be the same as or greater than the minimum ETA.';
            }

            if ($method['packages'] === []) {
                continue;
            }

            $activePackages = collect($method['packages'])->filter(fn (array $package): bool => $package['is_active']);
            if ($activePackages->isEmpty()) {
                $errors['shipping_methods.'.$index.'.packages'][] = 'Each delivery channel needs at least one active package option.';
            }

            $duplicatePackageCodes = collect($method['packages'])->duplicates('code')->filter()->values();
            if ($duplicatePackageCodes->isNotEmpty()) {
                $errors['shipping_methods.'.$index.'.packages'][] = 'Package codes must be unique within a delivery channel.';
            }

            foreach ($method['packages'] as $packageIndex => $package) {
                if ($package['code'] === '') {
                    $errors['shipping_methods.'.$index.'.packages.'.$packageIndex.'.code'][] = 'Each package option needs a code.';
                }
                if ($package['label'] === '') {
                    $errors['shipping_methods.'.$index.'.packages.'.$packageIndex.'.label'][] = 'Each package option needs a label.';
                }
                if ($package['capacity'] === null) {
                    $errors['shipping_methods.'.$index.'.packages.'.$packageIndex.'.capacity'][] = 'Each package option needs a capacity.';
                }
                if ($package['price'] === null) {
                    $errors['shipping_methods.'.$index.'.packages.'.$packageIndex.'.price'][] = 'Each package option needs a price.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $defaultIndex = $methods->search(fn (array $method): bool => $method['is_active'] && $method['is_default']);
        $activeMethodCount = $methods->filter(fn (array $method): bool => $method['is_active'])->count();
        if ($defaultIndex === false && $activeMethodCount === 1) {
            $defaultIndex = $methods->search(fn (array $method): bool => $method['is_active']);
        }
        if ($defaultIndex === false) {
            $defaultIndex = $methods->search(fn (array $method): bool => $method['is_active']);
        }

        return $methods->values()->map(function (array $method, int $index) use ($defaultIndex): array {
            $method['is_default'] = $defaultIndex !== false && $index === $defaultIndex;

            return $method;
        })->all();
    }

    /**
     * @param  list<array{
     *     id:int|null,
     *     code:string,
     *     name:string,
     *     description:string|null,
     *     shipment_label:string,
     *     immediate_status_label:string,
     *     delayed_status_label:string,
     *     calculator:string,
     *     flat_rate_amount:float|null,
     *     delivery_estimate_min_days:int|null,
     *     delivery_estimate_max_days:int|null,
     *     rate_multiplier:float,
     *     rate_adjustment_amount:float,
     *     is_pickup:bool,
     *     is_default:bool,
     *     is_active:bool,
     *     sort_order:int,
     *     packages:list<array{
     *         id:int|null,
     *         code:string,
     *         label:string,
     *         sort_order:int,
     *         capacity:float|null,
     *         price:float|null,
     *         is_active:bool
     *     }>
     * }> $shippingMethods
     */
    private function syncShippingMethods(array $shippingMethods): void
    {
        $existingMethods = StoreShippingMethod::query()->with('packageOptions')->get()->keyBy('id');
        $submittedIds = [];

        foreach ($shippingMethods as $methodData) {
            $method = $methodData['id'] !== null
                ? $existingMethods->get($methodData['id'])
                : new StoreShippingMethod();

            if (! $method instanceof StoreShippingMethod) {
                continue;
            }

            $method->fill([
                'code' => $methodData['code'],
                'name' => $methodData['name'],
                'description' => $methodData['description'],
                'shipment_label' => $methodData['shipment_label'],
                'immediate_status_label' => $methodData['immediate_status_label'],
                'delayed_status_label' => $methodData['delayed_status_label'],
                'calculator' => $methodData['calculator'],
                'flat_rate_amount' => $methodData['flat_rate_amount'],
                'delivery_estimate_min_days' => $methodData['delivery_estimate_min_days'],
                'delivery_estimate_max_days' => $methodData['delivery_estimate_max_days'],
                'rate_multiplier' => $methodData['rate_multiplier'],
                'rate_adjustment_amount' => $methodData['rate_adjustment_amount'],
                'is_pickup' => $methodData['is_pickup'],
                'is_default' => $methodData['is_default'],
                'is_active' => $methodData['is_active'],
                'sort_order' => $methodData['sort_order'],
            ]);
            $method->save();

            $this->syncShippingMethodPackages($method, $methodData['packages']);

            $submittedIds[] = (int) $method->id;
        }

        StoreShippingMethod::query()
            ->when($submittedIds !== [], fn ($query) => $query->whereNotIn('id', $submittedIds))
            ->delete();
    }

    /**
     * @param  list<array{
     *     id:int|null,
     *     code:string,
     *     label:string,
     *     sort_order:int,
     *     capacity:float|null,
     *     price:float|null,
     *     is_active:bool
     * }> $packages
     */
    private function syncShippingMethodPackages(StoreShippingMethod $method, array $packages): void
    {
        if (! Schema::hasTable('store_shipping_method_packages')) {
            return;
        }

        $existingPackages = $method->packageOptions()->get()->keyBy('id');
        $existingPackagesByCode = $existingPackages
            ->filter(fn (StoreShippingMethodPackage $package): bool => trim((string) $package->code) !== '')
            ->keyBy(fn (StoreShippingMethodPackage $package): string => Str::lower(trim((string) $package->code)));
        $submittedPackageIds = [];

        if ($method->isPickup()) {
            $method->packageOptions()->delete();

            return;
        }

        foreach ($packages as $packageData) {
            $package = $packageData['id'] !== null
                ? $existingPackages->get($packageData['id'])
                : $existingPackagesByCode->get($packageData['code']);

            if (! $package instanceof StoreShippingMethodPackage) {
                $package = new StoreShippingMethodPackage();
            }

            $package->store_shipping_method_id = $method->id;
            $package->fill([
                'code' => $packageData['code'],
                'label' => $packageData['label'],
                'sort_order' => $packageData['sort_order'],
                'capacity' => round((float) ($packageData['capacity'] ?? 0), 2),
                'price' => round((float) ($packageData['price'] ?? 0), 2),
                'is_active' => $packageData['is_active'],
            ]);
            $package->save();

            $submittedPackageIds[] = (int) $package->id;
        }

        $method->packageOptions()
            ->when($submittedPackageIds !== [], fn ($query) => $query->whereNotIn('id', $submittedPackageIds))
            ->delete();
    }

    /**
     * @return array{shipment_label:string, immediate_status_label:string, delayed_status_label:string}
     */
    private function defaultShippingTerminology(bool $isPickup): array
    {
        if ($isPickup) {
            return [
                'shipment_label' => 'Collection',
                'immediate_status_label' => 'Available now',
                'delayed_status_label' => 'Available later',
            ];
        }

        return [
            'shipment_label' => 'Shipment',
            'immediate_status_label' => 'Ships now',
            'delayed_status_label' => 'Ships later',
        ];
    }
}
