<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

class StoreCartService
{
    private const SESSION_KEY = 'store.cart';

    private const LEGACY_SESSION_KEY = 'shop.cart';

    public function __construct(
        private readonly StoreShippingService $shipping,
        private readonly StoreCouponService $coupons
    ) {}

    public function contents(): array
    {
        $raw = session()->get(self::SESSION_KEY);
        if ($raw === null) {
            $raw = session()->get(self::LEGACY_SESSION_KEY, []);

            if ($raw !== []) {
                session()->put(self::SESSION_KEY, $raw);
                session()->forget(self::LEGACY_SESSION_KEY);
            }
        }

        $normalized = $this->normalizeContents($raw);

        if ($normalized !== $raw) {
            session()->put(self::SESSION_KEY, $normalized);
        }

        return $normalized;
    }

    public function couponCode(): ?string
    {
        $couponCode = $this->contents()['coupon_code'] ?? null;
        $couponCode = trim((string) $couponCode);

        return $couponCode !== '' ? $couponCode : null;
    }

    /**
     * @return Collection<int, object{
     *     key: string,
     *     product: Product,
     *     variant: ProductVariant|null,
     *     quantity: int,
     *     display_title: string,
     *     sku: string,
     *     unit_price: float,
     *     line_price: float,
     *     line_gst: float,
     *     unit_shipping_units: float,
     *     unit_min_satchel_rank: int|null,
     *     box_only: bool,
     *     unit_weight_grams: int|null,
     *     available_inventory: int|null,
     *     tracks_inventory: bool,
     *     is_in_stock: bool
     * }>
     */
    public function lines(): Collection
    {
        $contents = $this->contents();
        $rawLines = (array) ($contents['lines'] ?? []);

        if ($rawLines === []) {
            return collect();
        }

        $productIds = collect($rawLines)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();

        $products = Product::query()
            ->with(['hero', 'variants'])
            ->whereIn('id', $productIds)
            ->where('status', Product::STATUS_ACTIVE)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->keyBy('id');

        $lines = collect();
        $normalizedLines = [];

        foreach ($rawLines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $variantId = isset($line['variant_id']) ? (int) $line['variant_id'] : null;
            $quantity = min(99, max(0, (int) ($line['quantity'] ?? 0)));

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            /** @var Product|null $product */
            $product = $products->get($productId);
            if (! $product instanceof Product) {
                continue;
            }

            $variant = $product->variantById($variantId);
            if ($product->hasVariants() && ! $variant instanceof ProductVariant) {
                continue;
            }

            if ($variant instanceof ProductVariant && ! $variant->is_active) {
                continue;
            }

            $availableInventory = $product->availableInventory($variant);
            if ($availableInventory !== null) {
                $quantity = min($quantity, max(0, $availableInventory));
                if ($quantity <= 0) {
                    continue;
                }
            }

            $unitPrice = $product->priceForVariant($variant);
            $linePrice = round($unitPrice * $quantity, 2);

            $normalizedKey = $this->lineKey($product->id, $variant?->id);
            $normalizedLines[$normalizedKey] = [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => $quantity,
            ];

            $lines->push((object) [
                'key' => $normalizedKey,
                'product' => $product,
                'variant' => $variant,
                'quantity' => $quantity,
                'display_title' => $variant instanceof ProductVariant
                    ? $product->title.' - '.$variant->name
                    : (string) $product->title,
                'sku' => (string) ($variant?->sku ?: $product->sku ?: ''),
                'unit_price' => round($unitPrice, 2),
                'line_price' => $linePrice,
                'line_gst' => $this->inclusiveTaxAmount($linePrice, (float) $product->tax_rate),
                'unit_shipping_units' => $product->isPhysical() ? $product->shippingUnitsForVariant($variant) : 0.0,
                'unit_min_satchel_rank' => $product->isPhysical() ? $product->minSatchelRankForVariant($variant) : null,
                'box_only' => $product->isPhysical() ? $product->boxOnlyForVariant($variant) : false,
                'unit_weight_grams' => $product->isPhysical() ? $product->weightGramsForVariant($variant) : null,
                'available_inventory' => $availableInventory,
                'tracks_inventory' => $product->tracksInventory($variant),
                'is_in_stock' => $product->isInStock($variant),
            ]);
        }

        if ($normalizedLines !== $rawLines) {
            session()->put(self::SESSION_KEY, [
                'lines' => $normalizedLines,
                'coupon_code' => $contents['coupon_code'] ?? null,
            ]);
        }

        return $lines->values();
    }

    public function summary(array $options = []): array
    {
        $lines = $this->lines();
        $shippingQuote = $this->shipping->quote($lines, $options['shipping_country'] ?? null);
        $shippingAmount = round((float) ($shippingQuote['amount'] ?? 0), 2);
        $couponEvaluation = $this->coupons->evaluate(
            $options['coupon_code'] ?? $this->couponCode(),
            (float) $lines->sum('line_price'),
            $shippingAmount,
            $options['user'] ?? null,
            $options['billing_email'] ?? null,
        );

        $subtotal = round((float) $lines->sum('line_price'), 2);
        $shipping = round((float) ($shippingQuote['amount'] ?? 0), 2);
        $discount = round(min($subtotal + $shipping, (float) ($couponEvaluation['discount_amount'] ?? 0)), 2);
        $itemGst = round((float) $lines->sum('line_gst'), 2);
        $shippingTaxRate = (float) ($lines->first(fn ($line) => $line->product->isPhysical())?->product->tax_rate ?? 0.1);
        $shippingGst = $this->inclusiveTaxAmount($shipping, $shippingTaxRate);
        $discountTaxRate = (string) ($couponEvaluation['discount_type'] ?? '') === Coupon::DISCOUNT_TYPE_FREE_SHIPPING
            ? $shippingTaxRate
            : $this->effectiveTaxRate($subtotal, $itemGst);
        $discountGst = $this->inclusiveTaxAmount($discount, $discountTaxRate);
        $canCheckout = (bool) ($shippingQuote['can_checkout'] ?? true);
        $total = $canCheckout
            ? round(max(0, $subtotal + $shipping - $discount), 2)
            : null;

        return [
            'line_count' => $lines->count(),
            'item_count' => (int) $lines->sum('quantity'),
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'shipping_quote' => $shippingQuote,
            'discount' => $discount,
            'gst' => round(max(0, $itemGst + $shippingGst - $discountGst), 2),
            'total' => $total,
            'can_checkout' => $canCheckout,
            'contains_digital' => $lines->contains(fn ($line) => $line->product->isDigital()),
            'contains_physical' => $lines->contains(fn ($line) => $line->product->isPhysical()),
            'shipping_tax_rate' => $shippingTaxRate,
            'discount_tax_rate' => $discountTaxRate,
            'coupon' => $couponEvaluation['coupon'] ?? null,
            'coupon_code' => $couponEvaluation['coupon_code'] ?? null,
            'coupon_error' => $couponEvaluation['error'] ?? null,
            'coupon_type' => $couponEvaluation['discount_type'] ?? null,
        ];
    }

    public function payload(array $options = []): array
    {
        $shippingCountry = trim((string) ($options['shipping_country'] ?? 'Australia')) ?: 'Australia';
        $summary = $this->summary(array_merge($options, [
            'shipping_country' => $shippingCountry,
        ]));
        $lines = $this->lines();

        return [
            'shipping_country' => $shippingCountry,
            'coupon_code' => $this->couponCode(),
            'is_empty' => $lines->isEmpty(),
            'checkout_url' => route('shop.checkout'),
            'cart_url' => route('shop.cart.show', ['shipping_country' => $shippingCountry]),
            'lines' => $lines->map(function ($line): array {
                return [
                    'key' => (string) $line->key,
                    'display_title' => (string) $line->display_title,
                    'quantity' => (int) $line->quantity,
                    'unit_price' => round((float) $line->unit_price, 2),
                    'line_price' => round((float) $line->line_price, 2),
                    'variant_name' => $line->variant?->name,
                    'available_inventory' => $line->available_inventory,
                    'max_quantity' => $line->available_inventory !== null ? max(1, (int) $line->available_inventory) : 99,
                    'box_only' => (bool) $line->box_only,
                    'shipping_label' => $line->product->isPhysical()
                        ? ($line->box_only
                            ? 'Boxed shipping required for this item.'
                            : $line->product->shippingModeLabel().'. Final shipping is based on the whole cart.')
                        : null,
                    'product' => [
                        'title' => (string) $line->product->title,
                        'url' => route('shop.product.show', $line->product),
                        'image_url' => $line->product->primaryImageUrl(),
                        'product_type_label' => Product::productTypeLabel((string) $line->product->product_type),
                    ],
                ];
            })->values()->all(),
            'summary' => [
                'item_count' => (int) ($summary['item_count'] ?? 0),
                'subtotal' => round((float) ($summary['subtotal'] ?? 0), 2),
                'shipping' => round((float) ($summary['shipping'] ?? 0), 2),
                'discount' => round((float) ($summary['discount'] ?? 0), 2),
                'gst' => round((float) ($summary['gst'] ?? 0), 2),
                'total' => $summary['total'] !== null ? round((float) $summary['total'], 2) : null,
                'can_checkout' => (bool) ($summary['can_checkout'] ?? false),
                'coupon_code' => $summary['coupon_code'] ?? null,
                'shipping_quote' => [
                    'boxed_shipping_required' => (bool) ($summary['shipping_quote']['boxed_shipping_required'] ?? false),
                    'method' => (string) ($summary['shipping_quote']['method'] ?? ''),
                    'reason' => $summary['shipping_quote']['reason'] ?? null,
                    'package_summary' => $summary['shipping_quote']['package_summary'] ?? null,
                    'known_weight_grams' => (int) ($summary['shipping_quote']['known_weight_grams'] ?? 0),
                ],
            ],
        ];
    }

    public function add(Product $product, ?ProductVariant $variant = null, int $quantity = 1): void
    {
        $contents = $this->contents();
        $lines = (array) ($contents['lines'] ?? []);
        $key = $this->lineKey($product->id, $variant?->id);
        $current = (int) (($lines[$key]['quantity'] ?? 0));

        $lines[$key] = [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => min(99, max(1, $current + $quantity)),
        ];

        session()->put(self::SESSION_KEY, [
            'lines' => $lines,
            'coupon_code' => $contents['coupon_code'] ?? null,
        ]);
    }

    public function update(array $quantities): void
    {
        $contents = $this->contents();
        $currentLines = (array) ($contents['lines'] ?? []);
        $updatedLines = [];

        foreach ($quantities as $lineKey => $quantity) {
            $lineKey = trim((string) $lineKey);
            if ($lineKey === '' || ! isset($currentLines[$lineKey])) {
                continue;
            }

            $count = min(99, max(0, (int) $quantity));
            if ($count <= 0) {
                continue;
            }

            $updatedLines[$lineKey] = [
                'product_id' => (int) $currentLines[$lineKey]['product_id'],
                'variant_id' => isset($currentLines[$lineKey]['variant_id']) ? (int) $currentLines[$lineKey]['variant_id'] : null,
                'quantity' => $count,
            ];
        }

        session()->put(self::SESSION_KEY, [
            'lines' => $updatedLines,
            'coupon_code' => $contents['coupon_code'] ?? null,
        ]);
    }

    public function removeLine(string $lineKey): void
    {
        $contents = $this->contents();
        $lines = (array) ($contents['lines'] ?? []);
        unset($lines[$lineKey]);

        session()->put(self::SESSION_KEY, [
            'lines' => $lines,
            'coupon_code' => $contents['coupon_code'] ?? null,
        ]);
    }

    public function applyCoupon(?string $couponCode): void
    {
        $contents = $this->contents();
        $normalized = trim((string) $couponCode);

        session()->put(self::SESSION_KEY, [
            'lines' => $contents['lines'] ?? [],
            'coupon_code' => $normalized !== '' ? strtoupper($normalized) : null,
        ]);
    }

    public function clearCoupon(): void
    {
        $contents = $this->contents();

        session()->put(self::SESSION_KEY, [
            'lines' => $contents['lines'] ?? [],
            'coupon_code' => null,
        ]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function isEmpty(): bool
    {
        return $this->lines()->isEmpty();
    }

    public function lineKey(int $productId, ?int $variantId = null): string
    {
        return $productId.':'.($variantId ?? 0);
    }

    private function normalizeContents(mixed $raw): array
    {
        if (! is_array($raw)) {
            return ['lines' => [], 'coupon_code' => null];
        }

        $couponCode = null;
        $rawLines = $raw;

        if (array_key_exists('lines', $raw)) {
            $rawLines = is_array($raw['lines']) ? $raw['lines'] : [];
            $couponCode = isset($raw['coupon_code']) ? strtoupper(trim((string) $raw['coupon_code'])) : null;
        }

        $normalizedLines = [];

        foreach ($rawLines as $key => $line) {
            if (is_array($line)) {
                $productId = (int) ($line['product_id'] ?? 0);
                $variantId = isset($line['variant_id']) && (int) $line['variant_id'] > 0 ? (int) $line['variant_id'] : null;
                $quantity = min(99, max(0, (int) ($line['quantity'] ?? 0)));
            } else {
                $productId = (int) $key;
                $variantId = null;
                $quantity = min(99, max(0, (int) $line));
            }

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            $normalizedLines[$this->lineKey($productId, $variantId)] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
            ];
        }

        return [
            'lines' => $normalizedLines,
            'coupon_code' => $couponCode,
        ];
    }

    private function effectiveTaxRate(float $inclusiveAmount, float $taxAmount): float
    {
        $exclusiveAmount = round($inclusiveAmount - $taxAmount, 2);
        if ($inclusiveAmount <= 0 || $taxAmount <= 0 || $exclusiveAmount <= 0) {
            return 0.0;
        }

        return round($taxAmount / $exclusiveAmount, 4);
    }

    private function inclusiveTaxAmount(float $amount, float $taxRate): float
    {
        if ($amount <= 0 || $taxRate <= 0) {
            return 0.0;
        }

        return round($amount - ($amount / (1 + $taxRate)), 2);
    }
}
