<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreShippingMethod;
use Illuminate\Support\Collection;

class StoreCartService
{
    private const SESSION_KEY = 'store.cart';

    private const LEGACY_SESSION_KEY = 'shop.cart';

    private const CUSTOMER_MANUAL_QUOTE_MESSAGE = 'This item requires pickup or a manual shipping quote.';

    private const CUSTOMER_MANUAL_QUOTE_REASON = 'This item requires pickup or a manual shipping quote.';

    private ?Collection $resolvedLines = null;

    /**
     * @var list<array{
     *     key:string,
     *     type:string,
     *     message:string,
     *     requested_quantity:int,
     *     available_quantity:int
     * }>
     */
    private ?array $inventoryChangeNotices = null;

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
        $couponCode = trim((string) ($this->contents()['coupon_code'] ?? ''));

        return $couponCode !== '' ? $couponCode : null;
    }

    public function shippingMethodCode(): ?string
    {
        $code = trim((string) ($this->contents()['shipping_method_code'] ?? ''));

        return $code !== '' ? $code : null;
    }

    public function consolidateShipments(): bool
    {
        return (bool) ($this->contents()['consolidate_shipments'] ?? false);
    }

    /**
     * @return list<array{
     *     key:string,
     *     type:string,
     *     message:string,
     *     requested_quantity:int,
     *     available_quantity:int
     * }>
     */
    public function inventoryChangeNotices(): array
    {
        $this->lines();

        return $this->inventoryChangeNotices ?? [];
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
     *     available_now_inventory: int|null,
     *     available_now_quantity: int,
     *     delayed_quantity: int,
     *     delayed_fulfilment_type: string|null,
     *     delayed_shipping_estimate: string|null,
     *     tracks_inventory: bool,
     *     is_in_stock: bool,
     *     is_preorder: bool,
     *     allows_backorder: bool,
     *     preorder_shipping_estimate: string|null
     * }>
     */
    public function lines(): Collection
    {
        if ($this->resolvedLines instanceof Collection && $this->inventoryChangeNotices !== null) {
            return $this->resolvedLines;
        }

        $contents = $this->contents();
        $rawLines = (array) ($contents['lines'] ?? []);

        if ($rawLines === []) {
            $this->resolvedLines = collect();
            $this->inventoryChangeNotices = [];

            return $this->resolvedLines;
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
        $inventoryChangeNotices = [];

        foreach ($rawLines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $variantId = isset($line['variant_id']) ? (int) $line['variant_id'] : null;
            $requestedQuantity = min(99, max(0, (int) ($line['quantity'] ?? 0)));
            $quantity = $requestedQuantity;
            $lineKey = $this->lineKey($productId, $variantId);

            if ($productId <= 0 || $quantity <= 0) {
                continue;
            }

            /** @var Product|null $product */
            $product = $products->get($productId);
            if (! $product instanceof Product) {
                $inventoryChangeNotices[] = $this->inventoryChangeNotice(
                    $lineKey,
                    'removed',
                    'An item in your cart is no longer available and was removed.',
                    $requestedQuantity,
                    0,
                );
                continue;
            }

            $variant = $product->variantById($variantId);
            if ($variantId !== null && ! $variant instanceof ProductVariant) {
                $inventoryChangeNotices[] = $this->inventoryChangeNotice(
                    $lineKey,
                    'removed',
                    $product->title.' is no longer available in that option and was removed from your cart.',
                    $requestedQuantity,
                    0,
                );
                continue;
            }

            if ($variant instanceof ProductVariant && ! $variant->is_active) {
                $inventoryChangeNotices[] = $this->inventoryChangeNotice(
                    $lineKey,
                    'removed',
                    $product->displayTitle($variant).' is no longer available and was removed from your cart.',
                    $requestedQuantity,
                    0,
                );
                continue;
            }

            $availableInventory = $product->availableInventoryForPurchase($variant);
            if ($availableInventory !== null && $requestedQuantity > max(0, $availableInventory)) {
                $quantity = min($quantity, max(0, $availableInventory));
                if ($quantity <= 0) {
                    $inventoryChangeNotices[] = $this->inventoryChangeNotice(
                        $lineKey,
                        'removed',
                        $this->displayTitle($product, $variant).' has sold out and was removed from your cart.',
                        $requestedQuantity,
                        0,
                    );
                    continue;
                }

                $inventoryChangeNotices[] = $this->inventoryChangeNotice(
                    $lineKey,
                    'reduced',
                    'Quantity for '.$this->displayTitle($product, $variant).' was reduced from '.$requestedQuantity.' to '.$quantity.' because stock changed.',
                    $requestedQuantity,
                    $quantity,
                );
            }

            $fulfilment = $this->resolveFulfilmentDetails($product, $variant, $quantity);
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
                'display_title' => $product->displayTitle($variant),
                'sku' => (string) ($variant?->sku ?: $product->sku ?: ''),
                'unit_price' => round($unitPrice, 2),
                'line_price' => $linePrice,
                'line_gst' => $this->inclusiveTaxAmount($linePrice, (float) $product->tax_rate),
                'unit_shipping_units' => $product->isPhysical() ? $product->shippingUnitsForVariant($variant) : 0.0,
                'unit_min_satchel_rank' => $product->isPhysical() ? $product->minSatchelRankForVariant($variant) : null,
                'box_only' => $product->isPhysical() ? $product->boxOnlyForVariant($variant) : false,
                'unit_weight_grams' => $product->isPhysical() ? $product->weightGramsForVariant($variant) : null,
                'available_inventory' => $availableInventory,
                'available_now_inventory' => $fulfilment['available_now_inventory'],
                'available_now_quantity' => $fulfilment['available_now_quantity'],
                'delayed_quantity' => $fulfilment['delayed_quantity'],
                'delayed_fulfilment_type' => $fulfilment['delayed_fulfilment_type'],
                'delayed_shipping_estimate' => $fulfilment['delayed_shipping_estimate'],
                'tracks_inventory' => $product->tracksInventoryForPurchase($variant),
                'is_in_stock' => $product->isSelectionPurchasable($variant),
                'is_preorder' => $product->isPreorder($variant),
                'allows_backorder' => $product->allowsBackorder($variant),
                'preorder_shipping_estimate' => $product->preorderShippingEstimateLabel('F jS Y', $variant),
            ]);
        }

        if ($normalizedLines !== $rawLines) {
            $contents['lines'] = $normalizedLines;
            $this->persistContents($contents);
        }

        $this->resolvedLines = $this->sortCartLines($lines)->values();
        $this->inventoryChangeNotices = collect($inventoryChangeNotices)
            ->unique(fn (array $notice): string => $notice['type'].'|'.$notice['key'].'|'.$notice['requested_quantity'].'|'.$notice['available_quantity'])
            ->values()
            ->all();

        return $this->resolvedLines;
    }

    public function summary(array $options = []): array
    {
        /** @var Collection<int, object> $lines */
        $lines = $this->lines();
        $shippingCountry = trim((string) ($options['shipping_country'] ?? 'Australia')) ?: 'Australia';
        $consolidateShipments = (bool) ($options['consolidate_shipments'] ?? $this->consolidateShipments());
        $requestedShippingMethodCode = trim((string) ($options['shipping_method_code'] ?? $this->shippingMethodCode() ?? ''));
        $methodQuotes = $this->shipping->availableMethods($lines)
            ->map(function (StoreShippingMethod $method) use ($lines, $shippingCountry, $consolidateShipments): array {
                $methodQuote = $this->shipping->quote(
                    $lines,
                    $shippingCountry,
                    (string) $method->code,
                    $consolidateShipments,
                );

                return [
                    'code' => (string) $method->code,
                    'name' => (string) $method->name,
                    'description' => trim((string) ($method->description ?? '')) ?: null,
                    'delivery_estimate_label' => $method->deliveryEstimateLabel(),
                    'is_pickup' => $method->isPickup(),
                    'estimated_amount' => round((float) ($methodQuote['amount'] ?? 0), 2),
                    'can_checkout' => (bool) ($methodQuote['can_checkout'] ?? true),
                    'requires_manual_quote' => (bool) ($methodQuote['requires_manual_quote'] ?? false),
                    'reason' => trim((string) ($methodQuote['reason'] ?? '')) ?: null,
                    'manual_quote_line_keys' => array_values(array_filter(
                        array_map(
                            fn ($key): string => trim((string) $key),
                            is_array($methodQuote['manual_quote_line_keys'] ?? null) ? $methodQuote['manual_quote_line_keys'] : []
                        ),
                        fn (string $key): bool => $key !== ''
                    )),
                ];
            })
            ->values();

        /** @var Collection<int, array<string, mixed>> $methodQuotesForPresentation */
        $methodQuotesForPresentation = collect($methodQuotes->all());
        $shippingMethods = $this->presentShippingMethods($methodQuotesForPresentation)->values();
        $selectedShippingMethodCode = $this->resolveSelectedShippingMethodCode($shippingMethods, $requestedShippingMethodCode);
        $shippingQuote = $this->resolvePresentedShippingQuote(
            $lines,
            $shippingCountry,
            $consolidateShipments,
            $methodQuotesForPresentation,
            $shippingMethods,
            $selectedShippingMethodCode,
        );

        $shippingAmount = round((float) ($shippingQuote['amount'] ?? 0), 2);
        $requestedCouponCode = $options['coupon_code'] ?? $this->couponCode();
        $couponEvaluation = $this->coupons->evaluate(
            $requestedCouponCode,
            (float) $lines->sum('line_price'),
            $shippingAmount,
            $options['user'] ?? null,
            $options['billing_email'] ?? null,
            Coupon::CHECKOUT_CONTEXT_PRODUCTS,
            [
                'product_ids' => $lines->pluck('product.id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
            ],
        );
        $normalizedRequestedCouponCode = Coupon::normalizeCode($requestedCouponCode);
        $normalizedStoredCouponCode = Coupon::normalizeCode($this->couponCode());

        if (($couponEvaluation['error'] ?? null) !== null
            && $normalizedRequestedCouponCode !== ''
            && $normalizedRequestedCouponCode === $normalizedStoredCouponCode) {
            $this->clearCoupon();
        }

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
            'contains_preorder' => $lines->contains(fn ($line) => (bool) ($line->is_preorder ?? false)),
            'contains_backorder' => $lines->contains(fn ($line) => (int) ($line->delayed_quantity ?? 0) > 0 && ! (bool) ($line->is_preorder ?? false)),
            'has_delayed_items' => $lines->contains(fn ($line) => (int) ($line->delayed_quantity ?? 0) > 0),
            'shipping_tax_rate' => $shippingTaxRate,
            'discount_tax_rate' => $discountTaxRate,
            'coupon' => $couponEvaluation['coupon'] ?? null,
            'coupon_code' => $couponEvaluation['coupon_code'] ?? null,
            'coupon_error' => $couponEvaluation['error'] ?? null,
            'coupon_type' => $couponEvaluation['discount_type'] ?? null,
            'shipping_method_code' => $selectedShippingMethodCode !== '' ? $selectedShippingMethodCode : ($shippingQuote['selected_method_code'] ?? null),
            'consolidate_shipments' => $consolidateShipments && (bool) ($shippingQuote['offers_consolidation'] ?? false),
            'shipping_methods' => $shippingMethods->values()->all(),
        ];
    }

    public function payload(array $options = []): array
    {
        $shippingCountry = trim((string) ($options['shipping_country'] ?? 'Australia')) ?: 'Australia';
        $summary = $this->summary(array_merge($options, [
            'shipping_country' => $shippingCountry,
            'shipping_method_code' => $options['shipping_method_code'] ?? $this->shippingMethodCode(),
            'consolidate_shipments' => $options['consolidate_shipments'] ?? $this->consolidateShipments(),
        ]));
        $lines = $this->lines();

        return [
            'shipping_country' => $shippingCountry,
            'shipping_method_code' => $this->shippingMethodCode(),
            'consolidate_shipments' => $this->consolidateShipments(),
            'coupon_code' => $this->couponCode(),
            'inventory_change_notices' => $this->inventoryChangeNotices(),
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
                    'variant_name' => $line->product->variantDisplayName($line->variant),
                    'available_inventory' => $line->available_inventory,
                    'available_now_inventory' => $line->available_now_inventory,
                    'available_now_quantity' => (int) $line->available_now_quantity,
                    'delayed_quantity' => (int) $line->delayed_quantity,
                    'delayed_fulfilment_type' => $line->delayed_fulfilment_type,
                    'delayed_shipping_estimate' => $line->delayed_shipping_estimate,
                    'max_quantity' => $line->available_inventory !== null ? max(1, (int) $line->available_inventory) : 99,
                    'is_digital' => (bool) $line->product->isDigital(),
                    'box_only' => (bool) $line->box_only,
                    'is_preorder' => (bool) $line->is_preorder,
                    'allows_backorder' => (bool) $line->allows_backorder,
                    'preorder_shipping_estimate' => $line->preorder_shipping_estimate,
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
                'contains_digital' => (bool) ($summary['contains_digital'] ?? false),
                'contains_physical' => (bool) ($summary['contains_physical'] ?? false),
                'contains_preorder' => (bool) ($summary['contains_preorder'] ?? false),
                'contains_backorder' => (bool) ($summary['contains_backorder'] ?? false),
                'has_delayed_items' => (bool) ($summary['has_delayed_items'] ?? false),
                'shipping_method_code' => $summary['shipping_method_code'] ?? null,
                'consolidate_shipments' => (bool) ($summary['consolidate_shipments'] ?? false),
                'shipping_methods' => $summary['shipping_methods'] ?? [],
                'shipping_quote' => [
                    'boxed_shipping_required' => (bool) ($summary['shipping_quote']['boxed_shipping_required'] ?? false),
                    'method' => (string) ($summary['shipping_quote']['method'] ?? ''),
                    'reason' => $summary['shipping_quote']['reason'] ?? null,
                    'note' => $summary['shipping_quote']['note'] ?? null,
                    'requires_manual_quote' => (bool) ($summary['shipping_quote']['requires_manual_quote'] ?? false),
                    'manual_quote_line_keys' => $summary['shipping_quote']['manual_quote_line_keys'] ?? [],
                    'delivery_estimate_label' => $summary['shipping_quote']['delivery_estimate_label'] ?? null,
                    'package_summary' => $summary['shipping_quote']['package_summary'] ?? null,
                    'known_weight_grams' => (int) ($summary['shipping_quote']['known_weight_grams'] ?? 0),
                    'selected_method_code' => $summary['shipping_quote']['selected_method_code'] ?? null,
                    'is_pickup' => (bool) ($summary['shipping_quote']['is_pickup'] ?? false),
                    'shipment_count' => (int) ($summary['shipping_quote']['shipment_count'] ?? 0),
                    'split_shipments' => (bool) ($summary['shipping_quote']['split_shipments'] ?? false),
                    'offers_consolidation' => (bool) ($summary['shipping_quote']['offers_consolidation'] ?? false),
                    'consolidate_shipments' => (bool) ($summary['shipping_quote']['consolidate_shipments'] ?? false),
                    'second_shipment_charge_amount' => round((float) ($summary['shipping_quote']['second_shipment_charge_amount'] ?? 0), 2),
                    'consolidation_savings_amount' => round((float) ($summary['shipping_quote']['consolidation_savings_amount'] ?? 0), 2),
                    'shipments' => $summary['shipping_quote']['shipments'] ?? [],
                ],
            ],
        ];
    }

    public function add(Product $product, ?ProductVariant $variant = null, int $quantity = 1): void
    {
        $contents = $this->contents();
        $lines = (array) ($contents['lines'] ?? []);
        $key = $this->lineKey($product->id, $variant?->id);
        $current = (int) ($lines[$key]['quantity'] ?? 0);
        $maxQuantity = 99;

        $lines[$key] = [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => min($maxQuantity, max(1, $current + $quantity)),
        ];

        $contents['lines'] = $lines;
        $this->persistContents($contents);
    }

    public function update(array $quantities): void
    {
        $contents = $this->contents();
        $currentLines = (array) ($contents['lines'] ?? []);
        $updatedLines = [];
        $productIds = collect($currentLines)
            ->pluck('product_id')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        foreach ($quantities as $lineKey => $quantity) {
            $lineKey = trim((string) $lineKey);
            if ($lineKey === '' || ! isset($currentLines[$lineKey])) {
                continue;
            }

            $productId = (int) $currentLines[$lineKey]['product_id'];
            /** @var Product|null $product */
            $product = $products->get($productId);
            $maxQuantity = 99;
            $count = min($maxQuantity, max(0, (int) $quantity));
            if ($count <= 0) {
                continue;
            }

            $updatedLines[$lineKey] = [
                'product_id' => (int) $currentLines[$lineKey]['product_id'],
                'variant_id' => isset($currentLines[$lineKey]['variant_id']) ? (int) $currentLines[$lineKey]['variant_id'] : null,
                'quantity' => $count,
            ];
        }

        $contents['lines'] = $updatedLines;
        $this->persistContents($contents);
    }

    public function updatePreferences(?string $shippingMethodCode = null, bool $consolidateShipments = false): void
    {
        $contents = $this->contents();
        $contents['shipping_method_code'] = $this->normalizeShippingMethodCode($shippingMethodCode);
        $contents['consolidate_shipments'] = $consolidateShipments;
        $this->persistContents($contents);
    }

    public function removeLine(string $lineKey): void
    {
        $contents = $this->contents();
        $lines = (array) ($contents['lines'] ?? []);
        unset($lines[$lineKey]);
        $contents['lines'] = $lines;
        $this->persistContents($contents);
    }

    public function applyCoupon(?string $couponCode): void
    {
        $contents = $this->contents();
        $normalized = trim((string) $couponCode);
        $contents['coupon_code'] = $normalized !== '' ? strtoupper($normalized) : null;
        $this->persistContents($contents);
    }

    public function clearCoupon(): void
    {
        $contents = $this->contents();
        $contents['coupon_code'] = null;
        $this->persistContents($contents);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->resetResolvedState();
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
            return [
                'lines' => [],
                'coupon_code' => null,
                'shipping_method_code' => null,
                'consolidate_shipments' => false,
            ];
        }

        $couponCode = null;
        $shippingMethodCode = null;
        $consolidateShipments = false;
        $rawLines = $raw;

        if (array_key_exists('lines', $raw)) {
            $rawLines = is_array($raw['lines']) ? $raw['lines'] : [];
            $couponCode = isset($raw['coupon_code']) ? strtoupper(trim((string) $raw['coupon_code'])) : null;
            $shippingMethodCode = $this->normalizeShippingMethodCode($raw['shipping_method_code'] ?? null);
            $consolidateShipments = (bool) ($raw['consolidate_shipments'] ?? false);
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
            'shipping_method_code' => $shippingMethodCode,
            'consolidate_shipments' => $consolidateShipments,
        ];
    }

    private function resolveFulfilmentDetails(Product $product, ?ProductVariant $variant, int $quantity): array
    {
        $actualInventory = $product->availableInventory($variant);

        if ($product->isPreorder($variant)) {
            return [
                'available_now_inventory' => $actualInventory,
                'available_now_quantity' => 0,
                'delayed_quantity' => $quantity,
                'delayed_fulfilment_type' => 'preorder',
                'delayed_shipping_estimate' => $product->preorderShippingEstimateLabel('F jS Y', $variant),
            ];
        }

        if ($product->allowsBackorder($variant) && $actualInventory !== null && $quantity > $actualInventory) {
            return [
                'available_now_inventory' => $actualInventory,
                'available_now_quantity' => max(0, $actualInventory),
                'delayed_quantity' => max(0, $quantity - max(0, $actualInventory)),
                'delayed_fulfilment_type' => 'backorder',
                'delayed_shipping_estimate' => $product->backorderShippingEstimateLabel('F jS Y', $variant),
            ];
        }

        return [
            'available_now_inventory' => $actualInventory,
            'available_now_quantity' => $quantity,
            'delayed_quantity' => 0,
            'delayed_fulfilment_type' => null,
            'delayed_shipping_estimate' => null,
        ];
    }

    private function sortCartLines(Collection $lines): Collection
    {
        return $lines->sort(function (object $left, object $right): int {
            $productTitleComparison = strnatcasecmp(
                (string) ($left->product->title ?? ''),
                (string) ($right->product->title ?? ''),
            );
            if ($productTitleComparison !== 0) {
                return $productTitleComparison;
            }

            $productIdComparison = (int) ($left->product->id ?? 0) <=> (int) ($right->product->id ?? 0);
            if ($productIdComparison !== 0) {
                return $productIdComparison;
            }

            $leftVariantSortOrder = $left->variant instanceof ProductVariant ? $left->variant->sort_order : -1;
            $rightVariantSortOrder = $right->variant instanceof ProductVariant ? $right->variant->sort_order : -1;
            $variantSortOrderComparison = $leftVariantSortOrder <=> $rightVariantSortOrder;
            if ($variantSortOrderComparison !== 0) {
                return $variantSortOrderComparison;
            }

            $variantNameComparison = strnatcasecmp(
                (string) ($left->product->variantDisplayName($left->variant) ?? ''),
                (string) ($right->product->variantDisplayName($right->variant) ?? ''),
            );
            if ($variantNameComparison !== 0) {
                return $variantNameComparison;
            }

            $leftVariantId = $left->variant instanceof ProductVariant ? (int) $left->variant->id : 0;
            $rightVariantId = $right->variant instanceof ProductVariant ? (int) $right->variant->id : 0;

            return $leftVariantId <=> $rightVariantId;
        });
    }

    private function normalizeShippingMethodCode(mixed $code): ?string
    {
        $value = trim((string) $code);

        return $value !== '' ? $value : null;
    }

    /**
     * @param Collection<int, array<string, mixed>> $methodQuotes
     * @return Collection<int, array<string, mixed>>
     */
    private function presentShippingMethods(Collection $methodQuotes): Collection
    {
        $visibleMethods = $methodQuotes
            ->reject(fn (array $method): bool => ! (bool) ($method['is_pickup'] ?? false) && (bool) ($method['requires_manual_quote'] ?? false))
            ->values();

        $manualQuoteMethods = $methodQuotes
            ->filter(fn (array $method): bool => ! (bool) ($method['is_pickup'] ?? false) && (bool) ($method['requires_manual_quote'] ?? false))
            ->values();

        if ($manualQuoteMethods->isEmpty()) {
            return $visibleMethods;
        }

        $firstManualQuoteMethod = $manualQuoteMethods->first();
        $manualQuoteLineKeys = $manualQuoteMethods
            ->flatMap(fn (array $method): array => is_array($method['manual_quote_line_keys'] ?? null) ? $method['manual_quote_line_keys'] : [])
            ->map(fn ($key): string => trim((string) $key))
            ->filter(fn (string $key): bool => $key !== '')
            ->unique()
            ->values()
            ->all();

        $requestQuoteMethod = [
            'code' => StoreShippingService::REQUEST_QUOTE_METHOD_CODE,
            'name' => 'Request quote',
            'description' => self::CUSTOMER_MANUAL_QUOTE_MESSAGE,
            'delivery_estimate_label' => null,
            'is_pickup' => false,
            'estimated_amount' => 0.0,
            'can_checkout' => false,
            'requires_manual_quote' => true,
            'reason' => self::CUSTOMER_MANUAL_QUOTE_REASON,
            'manual_quote_line_keys' => $manualQuoteLineKeys,
        ];

        return collect([$requestQuoteMethod])
            ->concat($visibleMethods)
            ->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $shippingMethods
     */
    private function resolveSelectedShippingMethodCode(Collection $shippingMethods, string $requestedShippingMethodCode): ?string
    {
        $availableCodes = $shippingMethods
            ->map(fn (array $method): string => trim((string) ($method['code'] ?? '')))
            ->filter(fn (string $code): bool => $code !== '')
            ->values();

        if ($availableCodes->contains($requestedShippingMethodCode)) {
            return $requestedShippingMethodCode;
        }

        if ($availableCodes->contains(StoreShippingService::REQUEST_QUOTE_METHOD_CODE)) {
            return StoreShippingService::REQUEST_QUOTE_METHOD_CODE;
        }

        $firstCode = $availableCodes->first();

        return is_string($firstCode) && $firstCode !== '' ? $firstCode : null;
    }

    /**
     * @param Collection<int, object> $lines
     * @param Collection<int, array<string, mixed>> $methodQuotes
     * @param Collection<int, array<string, mixed>> $shippingMethods
     * @return array<string, mixed>
     */
    private function resolvePresentedShippingQuote(
        Collection $lines,
        string $shippingCountry,
        bool $consolidateShipments,
        Collection $methodQuotes,
        Collection $shippingMethods,
        ?string $selectedShippingMethodCode
    ): array {
        $selectedCode = trim((string) $selectedShippingMethodCode);

        if ($selectedCode === StoreShippingService::REQUEST_QUOTE_METHOD_CODE) {
            $manualQuoteMethod = $methodQuotes->first(
                fn (array $method): bool => ! (bool) ($method['is_pickup'] ?? false) && (bool) ($method['requires_manual_quote'] ?? false)
            );
            $requestQuoteOption = $shippingMethods->first(
                fn (array $method): bool => trim((string) ($method['code'] ?? '')) === StoreShippingService::REQUEST_QUOTE_METHOD_CODE
            );

            if (is_array($manualQuoteMethod)) {
                $shippingQuote = $this->shipping->quote($lines, $shippingCountry, (string) ($manualQuoteMethod['code'] ?? ''), $consolidateShipments);
                $shippingQuote['selected_method_code'] = StoreShippingService::REQUEST_QUOTE_METHOD_CODE;
                $shippingQuote['method'] = 'Request quote';
                $shippingQuote['note'] = is_array($requestQuoteOption) ? ($requestQuoteOption['description'] ?? null) : ($shippingQuote['reason'] ?? null);
                $shippingQuote['is_pickup'] = false;
                $shippingQuote['delivery_estimate_label'] = null;
                $shippingQuote['requires_manual_quote'] = true;
                $shippingQuote['can_checkout'] = false;
                $shippingQuote['amount'] = 0.0;
                $shippingQuote['reason'] = self::CUSTOMER_MANUAL_QUOTE_REASON;

                return $shippingQuote;
            }
        }

        if ($selectedCode !== '') {
            return $this->shipping->quote($lines, $shippingCountry, $selectedCode, $consolidateShipments);
        }

        return $this->shipping->quote($lines, $shippingCountry, null, $consolidateShipments);
    }

    private function persistContents(array $contents): void
    {
        session()->put(self::SESSION_KEY, [
            'lines' => $contents['lines'] ?? [],
            'coupon_code' => $contents['coupon_code'] ?? null,
            'shipping_method_code' => $this->normalizeShippingMethodCode($contents['shipping_method_code'] ?? null),
            'consolidate_shipments' => (bool) ($contents['consolidate_shipments'] ?? false),
        ]);

        $this->resetResolvedState();
    }

    private function resetResolvedState(): void
    {
        $this->resolvedLines = null;
        $this->inventoryChangeNotices = null;
    }

    private function displayTitle(Product $product, ?ProductVariant $variant = null): string
    {
        return $product->displayTitle($variant);
    }

    /**
     * @return array{
     *     key:string,
     *     type:string,
     *     message:string,
     *     requested_quantity:int,
     *     available_quantity:int
     * }
     */
    private function inventoryChangeNotice(
        string $key,
        string $type,
        string $message,
        int $requestedQuantity,
        int $availableQuantity,
    ): array {
        return [
            'key' => $key,
            'type' => $type,
            'message' => $message,
            'requested_quantity' => $requestedQuantity,
            'available_quantity' => $availableQuantity,
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
