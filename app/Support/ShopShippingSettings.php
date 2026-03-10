<?php

namespace App\Support;

use App\Models\SiteOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ShopShippingSettings
{
    public const SATCHELS_OPTION = 'store.shipping.satchels';

    public const MAX_WEIGHT_OPTION = 'store.shipping.max-satchel-weight-grams';

    public const BOXED_LABEL_OPTION = 'store.shipping.boxed-shipping-label';

    public const BOXED_MESSAGE_OPTION = 'store.shipping.boxed-shipping-message';

    public const BOXED_AMOUNT_OPTION = 'store.shipping.boxed-shipping-amount';

    private const LEGACY_SATCHELS_OPTION = 'shop.shipping.satchels';

    private const LEGACY_MAX_WEIGHT_OPTION = 'shop.shipping.max-satchel-weight-grams';

    private const LEGACY_BOXED_LABEL_OPTION = 'shop.shipping.boxed-shipping-label';

    private const LEGACY_BOXED_MESSAGE_OPTION = 'shop.shipping.boxed-shipping-message';

    private const LEGACY_BOXED_AMOUNT_OPTION = 'shop.shipping.boxed-shipping-amount';

    /**
     * @return Collection<int, array{code:non-empty-string,label:string,rank:int,capacity:float,price:float,active:bool}>
     */
    public static function satchels(): Collection
    {
        $fallback = (array) config('store.shipping.satchels', []);
        $configured = self::jsonOption(self::SATCHELS_OPTION, $fallback, self::LEGACY_SATCHELS_OPTION);

        return collect($configured)
            ->map(function (array $satchel): array {
                return [
                    'code' => trim((string) ($satchel['code'] ?? '')),
                    'label' => trim((string) ($satchel['label'] ?? 'Satchel')) ?: 'Satchel',
                    'rank' => max(1, (int) ($satchel['rank'] ?? 1)),
                    'capacity' => round(max(0.01, (float) ($satchel['capacity'] ?? 0)), 2),
                    'price' => round(max(0, (float) ($satchel['price'] ?? 0)), 2),
                    'active' => filter_var($satchel['active'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                ];
            })
            ->filter(fn (array $satchel) => $satchel['code'] !== '')
            ->sortBy('rank')
            ->values();
    }

    public static function maxSatchelWeightGrams(): int
    {
        return max(0, self::integerOption(
            self::MAX_WEIGHT_OPTION,
            (int) config('store.shipping.max_satchel_weight_grams', 5000),
            self::LEGACY_MAX_WEIGHT_OPTION,
        ));
    }

    /**
     * @return array{label:string,message:string,amount:?float}
     */
    public static function boxedShipping(): array
    {
        $fallback = (array) config('store.shipping.boxed_shipping', []);
        $amount = trim(self::stringOption(
            self::BOXED_AMOUNT_OPTION,
            $fallback['amount'] !== null ? (string) $fallback['amount'] : '',
            self::LEGACY_BOXED_AMOUNT_OPTION,
        ));

        return [
            'label' => self::stringOption(
                self::BOXED_LABEL_OPTION,
                (string) ($fallback['label'] ?? 'Boxed shipping required'),
                self::LEGACY_BOXED_LABEL_OPTION,
            ),
            'message' => self::stringOption(
                self::BOXED_MESSAGE_OPTION,
                (string) ($fallback['message'] ?? 'This order requires boxed shipping.'),
                self::LEGACY_BOXED_MESSAGE_OPTION,
            ),
            'amount' => $amount !== '' && is_numeric($amount) ? round((float) $amount, 2) : null,
        ];
    }

    private static function stringOption(string $name, string $fallback = '', ?string $legacyName = null): string
    {
        if (! Schema::hasTable('site_options')) {
            return $fallback;
        }

        $value = trim((string) (SiteOption::value($name, '') ?? ''));
        if ($value === '' && $legacyName !== null) {
            $value = trim((string) (SiteOption::value($legacyName, $fallback) ?? $fallback));
        }

        return $value !== '' ? $value : $fallback;
    }

    private static function integerOption(string $name, int $fallback, ?string $legacyName = null): int
    {
        $value = self::stringOption($name, (string) $fallback, $legacyName);

        return is_numeric($value) ? (int) $value : $fallback;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fallback
     * @return array<int, array<string, mixed>>
     */
    private static function jsonOption(string $name, array $fallback, ?string $legacyName = null): array
    {
        $raw = self::stringOption($name, '', $legacyName);
        if ($raw === '') {
            return $fallback;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $fallback;
    }
}
