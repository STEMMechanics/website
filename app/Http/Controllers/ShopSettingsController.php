<?php

namespace App\Http\Controllers;

use App\Models\SiteOption;
use App\Support\ShopAvailability;
use App\Support\ShopShippingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopSettingsController extends Controller
{
    public function edit(ShopAvailability $availability): View
    {
        return view('admin.shop.settings', [
            'publicEnabled' => $availability->isPublicEnabled(),
            'satchels' => ShopShippingSettings::satchels()->values()->all(),
            'maxSatchelWeightGrams' => ShopShippingSettings::maxSatchelWeightGrams(),
            'boxedShipping' => ShopShippingSettings::boxedShipping(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'public_enabled' => ['required', 'boolean'],
            'max_satchel_weight_grams' => ['required', 'integer', 'min:0', 'max:50000'],
            'boxed_shipping_label' => ['required', 'string', 'max:120'],
            'boxed_shipping_message' => ['required', 'string', 'max:500'],
            'boxed_shipping_amount' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'satchels' => ['required', 'array', 'min:1'],
            'satchels.*.code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_-]+$/'],
            'satchels.*.label' => ['required', 'string', 'max:60'],
            'satchels.*.rank' => ['required', 'integer', 'min:1', 'max:99'],
            'satchels.*.capacity' => ['required', 'numeric', 'min:0.01', 'max:999'],
            'satchels.*.price' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'satchels.*.active' => ['nullable', 'boolean'],
        ], [
            'satchels.*.code.regex' => 'Satchel codes may only contain lowercase letters, numbers, hyphens, and underscores.',
        ]);

        $satchels = collect($validated['satchels'])
            ->map(function (array $satchel): array {
                return [
                    'code' => trim((string) $satchel['code']),
                    'label' => trim((string) $satchel['label']),
                    'rank' => (int) $satchel['rank'],
                    'capacity' => round((float) $satchel['capacity'], 2),
                    'price' => round((float) $satchel['price'], 2),
                    'active' => (bool) ($satchel['active'] ?? false),
                ];
            })
            ->sortBy('rank')
            ->values();

        $duplicateCodes = $satchels->duplicates('code')->filter()->values();
        if ($duplicateCodes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'satchels' => 'Satchel codes must be unique.',
            ]);
        }

        $duplicateRanks = $satchels->duplicates('rank')->filter()->values();
        if ($duplicateRanks->isNotEmpty()) {
            throw ValidationException::withMessages([
                'satchels' => 'Satchel ranks must be unique so the packing order stays predictable.',
            ]);
        }

        $this->storeOption(ShopAvailability::PUBLIC_ENABLED_OPTION, (string) ((int) $validated['public_enabled']));
        $this->storeOption(ShopShippingSettings::SATCHELS_OPTION, json_encode($satchels->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]');
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
}
